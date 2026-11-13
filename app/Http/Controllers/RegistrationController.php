<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStudent;
use App\Exceptions\LegacyUnavailable;
use App\Exceptions\RegistrationRejected;
use App\Models\Notice;
use App\Models\Registration;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $number = $request->user()->student_number;

        return view('registrations.index', [
            'registrations' => Registration::where('student_number', $number)->latest('id')->get(),
            'notices' => Notice::where('student_number', $number)->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request, RegistrationService $service): RedirectResponse
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer'],
        ], ['course_ids.required' => 'Select at least one course.']);

        try {
            $registration = $service->submit($request->user()->student_number, $data['course_ids']);
        } catch (RegistrationRejected $e) {
            return back()->with('problems', $e->reasons);
        } catch (InvalidStudent|LegacyUnavailable $e) {
            return back()->with('problems', [$e->getMessage().' Your registration was not submitted.']);
        }

        return redirect()->route('registrations.show', $registration)
            ->with('status', 'Your seats are reserved. Pay to finish enrolling.');
    }

    public function show(Request $request, Registration $registration): View
    {
        $this->authorizeOwner($request, $registration);
        $registration->load('courses', 'payments', 'enrollmentUpdate');

        return view('registrations.show', ['registration' => $registration, 'latestPayment' => $registration->latestPayment()]);
    }

    public function cancel(Request $request, Registration $registration, RegistrationService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        try {
            $service->cancel($registration);
        } catch (RegistrationRejected $e) {
            return back()->with('problems', $e->reasons);
        }

        return redirect()->route('courses.index')->with('status', "Registration {$registration->reference} is cancelled and its seats are released.");
    }

    private function authorizeOwner(Request $request, Registration $registration): void
    {
        abort_unless($registration->student_number === $request->user()->student_number, 403);
    }
}
