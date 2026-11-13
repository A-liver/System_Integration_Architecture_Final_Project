@extends('layouts.app')
@section('title', 'Reconciliation')

@section('content')
    <div class="page-head">
        <div>
            <h1>Paid enrollments to reconcile</h1>
            <p class="sub">Payment is confirmed on every row. Retrying only rewrites the enrollment, never the charge.</p>
        </div>
        <form method="post" action="{{ route('staff.run-due') }}" data-busy-form>
            @csrf
            <button class="btn quiet" data-busy-label="Running...">Run due retries now</button>
        </form>
    </div>

    <div class="outage">
        <div>
            <strong>Legacy student records: {{ $legacyOnline ? 'online' : 'simulated outage' }}</strong>
            <p class="sub">For demonstrating test case IT-03.</p>
        </div>
        <form method="post" action="{{ route('staff.legacy') }}" data-busy-form>
            @csrf
            <button class="btn quiet" data-busy-label="Switching...">{{ $legacyOnline ? 'Simulate an outage' : 'Restore the system' }}</button>
        </form>
    </div>

    @if ($updates->isEmpty())
        <div class="state-empty">
            <p>Nothing to reconcile. Every paid enrollment has been recorded.</p>
        </div>
    @else
        <table class="ledger">
            <thead>
                <tr>
                    <th scope="col">Registration</th>
                    <th scope="col">Student</th>
                    <th scope="col">Status</th>
                    <th scope="col">Payment reference</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($updates as $update)
                    <tr>
                        <td class="code">{{ $update->registration->reference }}</td>
                        <td data-label="Student">{{ $update->registration->student_name }}<div class="why">{{ $update->registration->student_number }}</div></td>
                        <td data-label="Status">
                            <span class="stamp {{ $update->status === 'needs_staff' ? 'bad' : 'wait' }}">{{ $update->status === 'needs_staff' ? 'Needs staff' : 'Retrying' }}</span>
                            <div class="why">Attempts: {{ $update->attempts }}@if ($update->next_attempt_at), next at {{ $update->next_attempt_at->format('g:i:s A') }}@endif</div>
                            @if ($update->last_error)<div class="why stop">{{ $update->last_error }}</div>@endif
                        </td>
                        <td data-label="Payment reference" style="overflow-wrap:anywhere">{{ $update->payment_reference }}</td>
                        <td>
                            <div class="row-actions">
                                <form method="post" action="{{ route('staff.retry', $update) }}" data-busy-form>
                                    @csrf
                                    <button class="btn" data-busy-label="Retrying...">Retry now</button>
                                </form>
                                <details>
                                    <summary>Resolve by hand</summary>
                                    <form method="post" action="{{ route('staff.reconcile', $update) }}" data-busy-form>
                                        @csrf
                                        <label class="field">
                                            What did you check?
                                            <textarea name="note" rows="2" required maxlength="500"></textarea>
                                        </label>
                                        <button class="btn quiet" data-busy-label="Saving...">Mark as enrolled</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
