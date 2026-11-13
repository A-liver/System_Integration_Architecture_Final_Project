<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    protected $fillable = ['code', 'title', 'units', 'schedule', 'capacity', 'seats_taken'];

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'course_prerequisites', 'course_id', 'prerequisite_id');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seats_taken);
    }
}
