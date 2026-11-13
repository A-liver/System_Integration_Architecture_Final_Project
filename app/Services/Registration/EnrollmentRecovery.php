<?php

namespace App\Services\Registration;

use App\Exceptions\LegacyUnavailable;
use App\Models\EnrollmentUpdate;
use App\Models\IntegrationLog;
use App\Models\Notice;
use App\Models\Registration;
use App\Models\User;
use App\Services\Records\StudentRecords;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Writes a paid enrollment to the legacy system and recovers when it is down.
 * Works only from the stored payment reference, so it can never charge anyone.
 */
class EnrollmentRecovery
{
    public function __construct(private StudentRecords $records) {}

    public function attempt(EnrollmentUpdate $update): EnrollmentUpdate
    {
        return Cache::lock("enrollment-update:{$update->id}", 30)->block(15, function () use ($update) {
            $update->refresh();

            if (in_array($update->status, [EnrollmentUpdate::SUCCEEDED, EnrollmentUpdate::RECONCILED], true)) {
                return $update;
            }

            $registration = $update->registration;

            try {
                $this->records->writeEnrollment(
                    $registration->student_number,
                    $registration->courses->pluck('code')->all(),
                    $registration->term_code,
                    $update->payment_reference,
                );
            } catch (LegacyUnavailable $e) {
                return $this->recordFailure($update, $e->getMessage());
            }

            DB::transaction(function () use ($update, $registration) {
                $update->update(['status' => EnrollmentUpdate::SUCCEEDED, 'last_error' => null, 'next_attempt_at' => null, 'attempts' => $update->attempts + 1]);
                $registration->update(['status' => Registration::ENROLLED]);
                $this->notify($registration, "You are enrolled. Registration {$registration->reference}, payment reference {$update->payment_reference}.");
            });

            IntegrationLog::record('registration', 'enrollment_update', 'ok', 'Written to legacy records', $registration->reference);

            return $update->refresh();
        });
    }

    /** @return int how many due updates were attempted */
    public function retryDue(): int
    {
        $due = EnrollmentUpdate::where('status', EnrollmentUpdate::PENDING)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->get();

        $due->each(fn (EnrollmentUpdate $update) => $this->attempt($update));

        return $due->count();
    }

    public function staffRetry(EnrollmentUpdate $update): EnrollmentUpdate
    {
        if ($update->status === EnrollmentUpdate::NEEDS_STAFF) {
            $update->update(['status' => EnrollmentUpdate::PENDING, 'attempts' => 0]);
            $update->registration->update(['status' => Registration::ENROLLMENT_PENDING]);
        }

        return $this->attempt($update);
    }

    public function reconcile(EnrollmentUpdate $update, User $staff, string $note): void
    {
        DB::transaction(function () use ($update, $staff, $note) {
            $update->update([
                'status' => EnrollmentUpdate::RECONCILED,
                'resolved_by' => $staff->id,
                'resolution_note' => $note,
                'next_attempt_at' => null,
            ]);
            $update->registration->update(['status' => Registration::ENROLLED]);
            $this->notify($update->registration, "The registrar confirmed your enrollment for {$update->registration->reference}.");
        });

        IntegrationLog::record('registration', 'reconcile', 'ok', "Resolved by {$staff->login_id}", $update->registration->reference);
    }

    private function recordFailure(EnrollmentUpdate $update, string $error): EnrollmentUpdate
    {
        $attempts = $update->attempts + 1;
        $exhausted = $attempts >= config('registration.max_enrollment_attempts');

        $update->update([
            'attempts' => $attempts,
            'last_error' => $error,
            'status' => $exhausted ? EnrollmentUpdate::NEEDS_STAFF : EnrollmentUpdate::PENDING,
            'next_attempt_at' => $exhausted ? null : now()->addSeconds($attempts * config('registration.retry_backoff_seconds')),
        ]);

        if ($exhausted) {
            $update->registration->update(['status' => Registration::NEEDS_STAFF]);
            $this->notify($update->registration, "Your payment for {$update->registration->reference} is confirmed. The registrar is finishing your enrollment by hand. You will not be charged again.");
        }

        IntegrationLog::record('registration', 'enrollment_update', $exhausted ? 'needs_staff' : 'pending', "Attempt {$attempts}: {$error}", $update->registration->reference);

        return $update->refresh();
    }

    private function notify(Registration $registration, string $body): void
    {
        Notice::create(['student_number' => $registration->student_number, 'registration_id' => $registration->id, 'body' => $body]);
    }
}
