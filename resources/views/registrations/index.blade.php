@extends('layouts.app')
@section('title', 'My registrations')
@php use App\Support\Money; @endphp

@section('content')
    <div class="page-head">
        <h1>My registrations</h1>
    </div>

    <section class="block">
        @if ($registrations->isEmpty())
            <div class="state-empty">
                <p>You have no registrations yet.</p>
                <a href="{{ route('courses.index') }}">Choose your courses</a>
            </div>
        @else
            <table class="ledger">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Units</th>
                        <th scope="col" class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registrations as $registration)
                        <tr>
                            <td><a class="ref" href="{{ route('registrations.show', $registration) }}">{{ $registration->reference }}</a></td>
                            <td>@include('registrations._stamp')</td>
                            <td class="num" data-label="Units">{{ $registration->units }}</td>
                            <td class="num" data-label="Total">{{ Money::format($registration->total_cents, $registration->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="block">
        <h2>Notices</h2>
        @if ($notices->isEmpty())
            <p class="sub">Updates about your enrollment will appear here.</p>
        @else
            <ul class="notices">
                @foreach ($notices as $notice)
                    <li>{{ $notice->body }}<time datetime="{{ $notice->created_at->toIso8601String() }}">{{ $notice->created_at->format('M j, Y g:i A') }}</time></li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
