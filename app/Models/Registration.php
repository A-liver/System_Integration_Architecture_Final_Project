<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PAYMENT_PENDING = 'payment_pending';

    public const ENROLLMENT_PENDING = 'enrollment_pending';

    public const ENROLLED = 'enrolled';

    public const NEEDS_STAFF = 'needs_staff';

    public const CANCELLED = 'cancelled';

    /** Statuses that stop a student from opening another registration. */
    public const OPEN = [self::AWAITING_PAYMENT, self::PAYMENT_PENDING, self::ENROLLMENT_PENDING, self::NEEDS_STAFF];

    protected $fillable = [
        'reference', 'student_number', 'student_name', 'term_code', 'status',
        'units', 'tuition_cents', 'misc_fee_cents', 'total_cents', 'currency',
    ];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function enrollmentUpdate(): HasOne
    {
        return $this->hasOne(EnrollmentUpdate::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAYMENT_PENDING => 'Payment processing',
            self::ENROLLMENT_PENDING => 'Paid, enrollment pending',
            self::ENROLLED => 'Enrolled',
            self::NEEDS_STAFF => 'Paid, with registrar',
            self::CANCELLED => 'Cancelled',
            default => $this->status,
        };
    }

    public function latestPayment(): ?Payment
    {
        return $this->payments->sortByDesc('id')->first();
    }

    public function confirmedPayment(): ?Payment
    {
        return $this->payments->firstWhere('status', Payment::CAPTURED);
    }
}
