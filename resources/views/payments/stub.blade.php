@extends('layouts.app')
@section('title', 'Test payment stub')
@php use App\Support\Money; @endphp

@section('content')
    <div class="narrow">
        <h1>Test payment stub</h1>
        <p class="sub">Stands in for PayPal during testing. No money moves. Amount: {{ Money::format($registration->total_cents, $registration->currency) }}.</p>

        <form method="post" action="{{ route('payments.stub.submit', $registration) }}" data-busy-form>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <fieldset class="choices">
                <legend>Simulate this result</legend>
                @foreach ($outcomes as $value => $label)
                    <label><input type="radio" name="outcome" value="{{ $value }}" @checked($loop->first)> {{ $label }}</label>
                @endforeach
            </fieldset>
            <div class="actions">
                <button class="btn" data-busy-label="Sending result...">Send result to the portal</button>
                <a href="{{ route('payments.cancelled', $registration) }}">Leave without paying</a>
            </div>
        </form>
    </div>
@endsection
