<?php

namespace App\Services\Registration;

use App\Exceptions\RegistrationRejected;
use App\Models\Course;
use App\Models\IntegrationLog;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Catalog\CourseCatalog;
use App\Services\Records\StudentRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private StudentRecords $records,
        private CourseCatalog $catalog,
    ) {}

    /**
     * @param  list<int>  $courseIds
     *
     * @throws \App\Exceptions\InvalidStudent
     * @throws \App\Exceptions\LegacyUnavailable
     * @throws RegistrationRejected
     */
    public function submit(string $studentNumber, array $courseIds): Registration
    {
        $student = $this->records->verify($studentNumber);

        $courses = Course::with('prerequisites')->whereIn('id', array_unique($courseIds))->get();
        if ($courses->isEmpty()) {
            $this->reject($studentNumber, ['Select at least one course.']);
        }

        $reasons = $courses
            ->map(fn (Course $c) => $this->catalog->ineligibilityReason($c, $student->completedCourseCodes))
            ->filter()
            ->values()
            ->all();

        if (Registration::where('student_number', $studentNumber)->where('term_code', config('registration.term_code'))->whereIn('status', Registration::OPEN)->exists()) {
            $reasons[] = 'You already have an open registration this term. Finish or cancel it first.';
        }

        $alreadyEnrolled = Registration::where('student_number', $studentNumber)
            ->where('term_code', config('registration.term_code'))
            ->where('status', Registration::ENROLLED)
            ->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $courses->modelKeys()))
            ->with('courses')
            ->get()
            ->flatMap->courses
            ->whereIn('id', $courses->modelKeys())
            ->pluck('code')
            ->unique();

        foreach ($alreadyEnrolled as $code) {
            $reasons[] = "{$code}: you are already enrolled in this course.";
        }

        if ($reasons !== []) {
            $this->reject($studentNumber, $reasons);
        }

        $this->catalog->reserveSeats($courses);

        $units = (int) $courses->sum('units');
        $tuition = $units * config('registration.tuition_per_unit_cents');
        $misc = config('registration.misc_fee_cents');

        $registration = DB::transaction(function () use ($student, $units, $tuition, $misc, $courses) {
            $registration = Registration::create([
                'reference' => 'REG-'.Str::upper(Str::random(8)),
                'student_number' => $student->studentNumber,
                'student_name' => $student->fullName,
                'term_code' => config('registration.term_code'),
                'status' => Registration::AWAITING_PAYMENT,
                'units' => $units,
                'tuition_cents' => $tuition,
                'misc_fee_cents' => $misc,
                'total_cents' => $tuition + $misc,
                'currency' => config('registration.currency'),
            ]);
            $registration->courses()->attach($courses->modelKeys());

            return $registration;
        });

        IntegrationLog::record('registration', 'submit', 'ok', "{$courses->count()} courses, {$units} units", $registration->reference);

        return $registration;
    }

    /**
     * @throws RegistrationRejected when a payment is already in flight
     */
    public function cancel(Registration $registration): void
    {
        $inFlight = $registration->payments()->whereIn('status', [Payment::APPROVED, Payment::PENDING, Payment::CAPTURED])->exists();

        if ($registration->status !== Registration::AWAITING_PAYMENT || $inFlight) {
            throw new RegistrationRejected(['This registration can no longer be cancelled because a payment is in progress.']);
        }

        DB::transaction(function () use ($registration) {
            $registration->update(['status' => Registration::CANCELLED]);
            $this->catalog->releaseSeats($registration->courses);
        });

        IntegrationLog::record('registration', 'cancel', 'ok', 'Seats released', $registration->reference);
    }

    /** @param list<string> $reasons */
    private function reject(string $studentNumber, array $reasons): never
    {
        IntegrationLog::record('course_catalog', 'validate', 'rejected', implode(' ', $reasons), $studentNumber);

        throw new RegistrationRejected($reasons);
    }
}
