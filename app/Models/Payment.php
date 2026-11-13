<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const CREATED = 'created';

    public const APPROVED = 'approved';

    public const PENDING = 'pending';

    public const CAPTURED = 'captured';

    public const DECLINED = 'declined';

    public const FAILED = 'failed';

    protected $fillable = [
        'registration_id', 'provider', 'provider_order_id', 'approval_url', 'payment_reference',
        'status', 'amount_cents', 'currency', 'last_message', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
