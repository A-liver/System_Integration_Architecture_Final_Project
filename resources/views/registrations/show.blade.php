@extends('layouts.app')
@section('title', $registration->reference)
@php
    use App\Models\Registration as R;
    use App\Support\Money;

    $status = $registration->status;
    $paid = in_array($status, [R::ENROLLMENT_PENDING, R::ENROLLED, R::NEEDS_STAFF], true);
    $confirmed = $registration->confirmedPayment();
    $canCheck = $latestPayment && in_array($latestPayment->status, ['approved', 'pending'], true);
    $steps = [
        ['Seats reserved', 'Held for you while you pay.', 'done'],
        ['Payment confirmed', $confirmed ? "Reference {$confirmed->payment_reference}" : 'Verified with the payment provider.', $paid ? 'done' : ($status === R::CANCELLED ? '' : 'now')],
        ['Recorded in student records', match ($status) {
            R::ENROLLMENT_PENDING => 'The records system has not responded yet. This retries by itself and will not charge you again.',
            R::NEEDS_STAFF => 'The registrar is finishing this by hand. You will not be charged again.',
            default => 'Written once your payment is confirmed.',
        }, $status === R::ENROLLED ? 'done' : ($paid ? 'now' : '')],
    ];
@endphp

@section('content')
    <div class="desk flip">
        <section class="slip" aria-label="Enrollment slip">
            <div class="slip-head">
                <h1 style="font-size:1.3rem">Enrollment slip</h1>
                <p class="ref">{{ $registration->reference }}</p>
                @include('registrations._stamp')
            </div>
            <div class="slip-body">
                <div class="slip-line"><span class="label">Student</span><span>{{ $registration->student_name }}</span></div>
                <div class="slip-line"><span class="label">Student number</span><span>{{ $registration->student_number }}</span></div>
                <hr class="tear">
                <ul class="slip-list">
                    @foreach ($registration->courses->sortBy('code') as $course)
                        <li><span><strong>{{ $course->code }}</strong> {{ $course->title }}</span><span>{{ $course->units }}</span></li>
                    @endforeach
                </ul>
                <div class="slip-line"><span class="label">Tuition, {{ $registration->units }} units</span><span>{{ Money::format($registration->tuition_cents, $registration->currency) }}</span></div>
                <div class="slip-line"><span class="label">Miscellaneous fee</span><span>{{ Money::format($registration->misc_fee_cents, $registration->currency) }}</span></div>
                <hr class="tear">
                <div class="total"><span>{{ $paid ? 'Paid' : 'Total due' }}</span><strong>{{ Money::format($registration->total_cents, $registration->currency) }}</strong></div>
            </div>
        </section>

        <section>
            <h2>Progress</h2>
            <ol class="steps">
                @foreach ($steps as [$title, $detail, $state])
                    <li class="{{ $state }}">
                        <span class="state">{{ ['done' => 'Done', 'now' => 'Now', '' => 'Later'][$state] }}</span>
                        <span><strong>{{ $title }}</strong><br>{{ $detail }}</span>
                    </li>
                @endforeach
            </ol>

            @if ($latestPayment?->last_message && ! $paid)
                <p class="sub" style="margin-bottom:16px">Last message from the provider: {{ $latestPayment->last_message }}</p>
            @endif

            <div class="actions">
                @if ($status === R::AWAITING_PAYMENT)
                    <form method="post" action="{{ route('payments.start', $registration) }}" data-busy-form>
                        @csrf
                        <button class="btn" data-busy-label="Opening PayPal...">Pay {{ Money::format($registration->total_cents, $registration->currency) }} with PayPal</button>
                    </form>
                    <form method="post" action="{{ route('registrations.cancel', $registration) }}" data-busy-form>
                        @csrf
                        <button class="btn quiet" data-busy-label="Cancelling...">Cancel and release seats</button>
                    </form>
                @elseif ($status === R::PAYMENT_PENDING && $canCheck)
                    <form method="post" action="{{ route('payments.check', $registration) }}" data-busy-form>
                        @csrf
                        <button class="btn" data-busy-label="Checking...">Check payment status</button>
                    </form>
                @elseif ($status === R::PAYMENT_PENDING)
                    <p>We could not verify this payment. Contact the registrar before paying again, and give them reference {{ $registration->reference }}.</p>
                @elseif ($status === R::ENROLLED)
                    <a class="btn quiet" href="{{ route('courses.index') }}">Back to courses</a>
                @elseif ($status === R::CANCELLED)
                    <a class="btn" href="{{ route('courses.index') }}">Choose courses again</a>
                @else
                    <a class="btn quiet" href="{{ route('registrations.show', $registration) }}">Refresh status</a>
                @endif
            </div>
        </section>
    </div>
@endsection
