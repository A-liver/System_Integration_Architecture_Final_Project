@extends('layouts.app')
@section('title', 'Courses')
@php use App\Support\Money; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Choose your courses</h1>
            <p class="sub">{{ $termLabel }}</p>
        </div>
        <form class="search" method="get" action="{{ route('courses.index') }}" role="search">
            <label class="field" style="flex:1;margin:0">
                Search by code or title
                <input type="search" name="q" value="{{ $search }}">
            </label>
            <button class="btn quiet">Search</button>
        </form>
    </div>

    @if ($problem)
        <div class="msg bad" role="alert">{{ $problem }}</div>
    @endif

    @if ($openRegistration)
        <div class="msg" role="status">
            You have an open registration, <a href="{{ route('registrations.show', $openRegistration) }}">{{ $openRegistration->reference }}</a> ({{ $openRegistration->statusLabel() }}). Finish or cancel it before starting another.
        </div>
    @endif

    <form method="post" action="{{ route('registrations.store') }}" class="desk" data-busy-form
          data-slip data-rate="{{ $tuitionPerUnit }}" data-misc="{{ $miscFee }}" data-currency="{{ $currency }}">
        @csrf
        <div>
            @if ($rows->isEmpty())
                <div class="state-empty">
                    @if ($search)
                        <p>No courses match "{{ $search }}".</p>
                        <a href="{{ route('courses.index') }}">Show all courses</a>
                    @else
                        <p>No courses have been published for this term yet.</p>
                    @endif
                </div>
            @else
                <table class="ledger">
                    <caption class="sub" style="margin-bottom:8px">Courses you cannot take yet say why.</caption>
                    <thead>
                        <tr>
                            <th scope="col"><span class="skip-label">Pick</span></th>
                            <th scope="col">Code</th>
                            <th scope="col">Title and schedule</th>
                            <th scope="col" class="num">Units</th>
                            <th scope="col" class="num">Seats left</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $course = $row['course'];
                                $canPick = $row['selectable'] && ! $openRegistration;
                            @endphp
                            <tr class="has-pick {{ $row['selectable'] || $row['enrolled'] ? '' : 'blocked' }}">
                                <td class="pick-cell">
                                    <label class="pick">
                                        <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                                               aria-label="Select {{ $course->code }} {{ $course->title }}"
                                               data-code="{{ $course->code }}" data-units="{{ $course->units }}"
                                               @checked(in_array($course->id, old('course_ids', [])))
                                               @disabled(! $canPick)>
                                    </label>
                                </td>
                                <td class="code">{{ $course->code }}</td>
                                <td>
                                    {{ $course->title }}
                                    <div class="why">{{ $course->schedule }}</div>
                                    @if ($row['enrolled'])
                                        <div class="why">You are enrolled in this course.</div>
                                    @elseif ($row['blocked_reason'])
                                        <div class="why stop">{{ ucfirst($row['blocked_reason']) }}</div>
                                    @elseif ($course->prerequisites->isNotEmpty())
                                        <div class="why">Requires {{ $course->prerequisites->pluck('code')->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td class="num" data-label="Units">{{ $course->units }}</td>
                                <td class="num" data-label="Seats left">{{ $course->seatsLeft() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <aside class="slip" aria-label="Enrollment slip">
            <div class="slip-head">
                <h2>Enrollment slip</h2>
                <p>{{ $student?->fullName ?? auth()->user()->name }}, {{ auth()->user()->student_number }}</p>
            </div>
            <div class="slip-body">
                <ul class="slip-list" data-slip-list></ul>
                <p class="empty-note" data-slip-empty>No courses selected yet.</p>
                <div class="slip-line"><span class="label">Units</span><span data-slip-units>0</span></div>
                <div class="slip-line"><span class="label">Tuition, {{ Money::format($tuitionPerUnit, $currency) }} per unit</span><span data-slip-tuition>{{ Money::format(0, $currency) }}</span></div>
                <div class="slip-line"><span class="label">Miscellaneous fee</span><span>{{ Money::format($miscFee, $currency) }}</span></div>
                <hr class="tear">
                <div class="total"><span>Total due</span><strong data-slip-total aria-live="polite">{{ Money::format(0, $currency) }}</strong></div>
                <button class="btn wide" style="margin-top:14px" data-busy-label="Reserving seats..." @disabled(! $student || $openRegistration)>Reserve seats and continue</button>
            </div>
        </aside>
    </form>
@endsection
