@php
    $variant = match ($registration->status) {
        'enrolled' => 'solid',
        'needs_staff', 'cancelled' => 'bad',
        'awaiting_payment' => '',
        default => 'wait',
    };
@endphp
<span class="stamp {{ $variant }}">{{ $registration->statusLabel() }}</span>
