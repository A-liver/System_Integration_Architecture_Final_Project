<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentUpdate;
use App\Models\IntegrationLog;
use App\Services\Registration\EnrollmentRecovery;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(SystemSettings $settings): View
    {
        return view('staff.reconciliation', [
            'legacyOnline' => $settings->legacyOnline(),
            'updates' => EnrollmentUpdate::with('registration.courses')
                ->whereIn('status', [EnrollmentUpdate::NEEDS_STAFF, EnrollmentUpdate::PENDING])
                ->orderByRaw("case when status = 'needs_staff' then 0 else 1 end")
                ->oldest('id')
                ->get(),
        ]);
    }

    public function retry(EnrollmentUpdate $update, EnrollmentRecovery $recovery): RedirectResponse
    {
        $update = $recovery->staffRetry($update);

        return back()->with(...($update->status === EnrollmentUpdate::SUCCEEDED
            ? ['status', "{$update->registration->reference} is now enrolled."]
            : ['problems', ["{$update->registration->reference} could not be written: {$update->last_error}"]]));
    }

    public function reconcile(Request $request, EnrollmentUpdate $update, EnrollmentRecovery $recovery): RedirectResponse
    {
        $data = $request->validate(
            ['note' => ['required', 'string', 'max:500']],
            ['note.required' => 'Write what you checked before marking this as resolved.'],
        );

        $recovery->reconcile($update, $request->user(), $data['note']);

        return back()->with('status', "{$update->registration->reference} is marked as enrolled.");
    }

    public function runDue(EnrollmentRecovery $recovery): RedirectResponse
    {
        return back()->with('status', 'Attempted '.$recovery->retryDue().' due update(s).');
    }

    public function toggleLegacy(Request $request, SystemSettings $settings): RedirectResponse
    {
        $online = ! $settings->legacyOnline();
        $settings->setLegacyOnline($online);
        IntegrationLog::record('student_records', 'simulated_availability', $online ? 'online' : 'offline', "Changed by {$request->user()->login_id}");

        return back()->with('status', $online ? 'Legacy records system is back online.' : 'Legacy records system is now simulating an outage.');
    }
}
