<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStudent;
use App\Exceptions\LegacyUnavailable;
use App\Models\Registration;
use App\Services\Catalog\CourseCatalog;
use App\Services\Records\StudentRecords;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, CourseCatalog $catalog, StudentRecords $records): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q')) ?: null;
        $problem = null;
        $student = null;

        try {
            $student = $records->verify($user->student_number);
        } catch (LegacyUnavailable) {
            $problem = 'The student records system is unavailable, so prerequisites cannot be checked. You can browse courses and register once it is back.';
        } catch (InvalidStudent $e) {
            $problem = $e->getMessage();
        }

        $term = config('registration.term_code');
        $openRegistration = Registration::where('student_number', $user->student_number)
            ->where('term_code', $term)->whereIn('status', Registration::OPEN)->latest('id')->first();

        $enrolledCourseIds = Registration::where('student_number', $user->student_number)
            ->where('term_code', $term)->where('status', Registration::ENROLLED)
            ->with('courses:id')->get()->flatMap->courses->pluck('id')->all();

        $rows = $catalog->search($search)->map(function ($course) use ($catalog, $student, $enrolledCourseIds) {
            $enrolled = in_array($course->id, $enrolledCourseIds, true);
            $reason = $student && ! $enrolled ? $catalog->ineligibilityReason($course, $student->completedCourseCodes) : null;

            return [
                'course' => $course,
                'enrolled' => $enrolled,
                'blocked_reason' => $reason === null ? null : substr($reason, strlen($course->code) + 2),
                'selectable' => $student !== null && ! $enrolled && $reason === null,
            ];
        });

        return view('courses.index', [
            'rows' => $rows,
            'search' => $search,
            'problem' => $problem,
            'student' => $student,
            'openRegistration' => $openRegistration,
            'termLabel' => config('registration.term_label'),
            'currency' => config('registration.currency'),
            'tuitionPerUnit' => config('registration.tuition_per_unit_cents'),
            'miscFee' => config('registration.misc_fee_cents'),
        ]);
    }
}
