<?php

namespace App\Services\Catalog;

use App\Exceptions\RegistrationRejected;
use App\Models\Course;
use App\Services\Records\LegacyStudentRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CourseCatalog
{
    public function search(?string $term = null): Collection
    {
        return Course::with('prerequisites')
            ->when($term, function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->orderBy('code')
            ->get();
    }

    /**
     * Why a student cannot take this course, or null when they can.
     *
     * @param  list<string>  $completedCodes  codes in the legacy format
     */
    public function ineligibilityReason(Course $course, array $completedCodes): ?string
    {
        $missing = $course->prerequisites
            ->reject(fn (Course $p) => in_array(LegacyStudentRecords::legacyCourseCode($p->code), $completedCodes, true))
            ->pluck('code');

        if ($missing->isNotEmpty()) {
            return "{$course->code}: prerequisite not met ({$missing->implode(', ')}).";
        }

        if ($course->seatsLeft() === 0) {
            return "{$course->code}: no seats left.";
        }

        return null;
    }

    /**
     * Claims one seat per course, or none. The conditional update means two
     * students racing for the last seat cannot both win it.
     *
     * @throws RegistrationRejected naming the first course with no seat
     */
    public function reserveSeats(Collection $courses): void
    {
        DB::transaction(function () use ($courses) {
            foreach ($courses as $course) {
                $claimed = Course::whereKey($course->id)
                    ->whereColumn('seats_taken', '<', 'capacity')
                    ->increment('seats_taken');

                if ($claimed === 0) {
                    throw new RegistrationRejected(["{$course->code}: no seats left."]);
                }
            }
        });
    }

    public function releaseSeats(Collection $courses): void
    {
        foreach ($courses as $course) {
            Course::whereKey($course->id)->where('seats_taken', '>', 0)->decrement('seats_taken');
        }
    }
}
