<?php

return [
    'term_code' => env('REGISTRATION_TERM_CODE', '2026-2'),
    'term_label' => env('REGISTRATION_TERM_LABEL', 'Sample term 2026-2'),

    'currency' => env('REGISTRATION_CURRENCY', 'PHP'),
    'tuition_per_unit_cents' => (int) env('REGISTRATION_TUITION_PER_UNIT_CENTS', 150000),
    'misc_fee_cents' => (int) env('REGISTRATION_MISC_FEE_CENTS', 250000),

    'payment_driver' => env('PAYMENT_DRIVER', 'paypal'),

    'max_enrollment_attempts' => (int) env('ENROLLMENT_MAX_ATTEMPTS', 5),
    'retry_backoff_seconds' => (int) env('ENROLLMENT_RETRY_BACKOFF_SECONDS', 60),

    'gateway_key' => env('API_GATEWAY_KEY'),
];
