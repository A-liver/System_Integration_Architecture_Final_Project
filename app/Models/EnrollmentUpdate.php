<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentUpdate extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const NEEDS_STAFF = 'needs_staff';

    public const RECONCILED = 'reconciled';

    protected $fillable = [
        'registration_id', 'payment_reference', 'status', 'attempts',
        'next_attempt_at', 'last_error', 'resolved_by', 'resolution_note',
    ];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
