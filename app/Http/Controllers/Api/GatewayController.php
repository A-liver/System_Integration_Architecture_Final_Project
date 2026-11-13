<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStudent;
use App\Exceptions\LegacyUnavailable;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\RegistrationRejected;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Registration;
use App\Services\Catalog\CourseCatalog;
use App\Services\Payments\PaymentIntegration;
use App\Services\Records\StudentRecords;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function courses(CourseCatalog $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->search()->map(fn (Course $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'title' => $c->title,
            'units' => $c->units,
            'schedule' => $c->schedule,
            'seats_left' => $c->seatsLeft(),
            'prerequisites' => $c->prerequisites->pluck('code'),
        ])]);
    }

    public function student(string $studentNumber, StudentRecords $records): JsonResponse
    {
        return $this->guarded(function () use ($studentNumber, $records) {
            $student = $records->verify($studentNumber);

            return response()->json(['data' => ['student_number' => $student->studentNumber, 'name' => $student->fullName]]);
        });
    }

    public function register(Request $request, RegistrationService $service): JsonResponse
    {
        $data = $request->validate([
            'student_number' => ['required', 'string'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer'],
        ]);

        return $this->guarded(fn () => response()->json(
            ['data' => $this->present($service->submit($data['student_number'], $data['course_ids']))],
            201,
        ));
    }

    public function registration(Registration $registration): JsonResponse
    {
        return response()->json(['data' => $this->present($registration)]);
    }

    public function payment(Registration $registration, PaymentIntegration $payments): JsonResponse
    {
        return $this->guarded(function () use ($registration, $payments) {
            $payment = $payments->start($registration);

            return response()->json(['data' => [
                'order_id' => $payment->provider_order_id,
                'approval_url' => $payment->approval_url,
                'status' => $payment->status,
            ]], 201);
        });
    }

    private function present(Registration $registration): array
    {
        return [
            'reference' => $registration->reference,
            'student_number' => $registration->student_number,
            'status' => $registration->status,
            'units' => $registration->units,
            'total_cents' => $registration->total_cents,
            'currency' => $registration->currency,
            'courses' => $registration->courses()->pluck('code'),
        ];
    }

    private function guarded(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (InvalidStudent $e) {
            return response()->json(['error' => 'invalid_student', 'message' => $e->getMessage()], 404);
        } catch (RegistrationRejected $e) {
            return response()->json(['error' => 'registration_rejected', 'reasons' => $e->reasons], 422);
        } catch (LegacyUnavailable $e) {
            return response()->json(['error' => 'records_unavailable', 'message' => $e->getMessage()], 503);
        } catch (PaymentGatewayException $e) {
            return response()->json(['error' => 'payment_unavailable', 'message' => $e->getMessage()], 502);
        }
    }
}
