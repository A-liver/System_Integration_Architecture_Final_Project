<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    public const UPDATED_AT = null;

    public const COMPONENTS = ['api_gateway', 'registration', 'course_catalog', 'payment', 'student_records'];

    protected $fillable = ['component', 'action', 'outcome', 'message', 'reference', 'duration_ms'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(string $component, string $action, string $outcome, ?string $message = null, ?string $reference = null, ?int $durationMs = null): void
    {
        self::create([
            'component' => $component,
            'action' => $action,
            'outcome' => $outcome,
            'message' => $message === null ? null : mb_substr($message, 0, 250),
            'reference' => $reference,
            'duration_ms' => $durationMs,
        ]);
    }
}
