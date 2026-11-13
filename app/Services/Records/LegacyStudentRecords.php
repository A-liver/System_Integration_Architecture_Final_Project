<?php

namespace App\Services\Records;

use App\Exceptions\InvalidStudent;
use App\Exceptions\LegacyUnavailable;
use App\Models\IntegrationLog;
use App\Services\SystemSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Data management middleware: translates portal requests into the legacy
 * system's formats (STU_NO without the dash, "LAST,FIRST" names, CRS_CD
 * without spaces, YYYYMMDD integer dates).
 */
class LegacyStudentRecords implements StudentRecords
{
    public function __construct(private SystemSettings $settings) {}

    public function isOnline(): bool
    {
        return $this->settings->legacyOnline();
    }

    public function verify(string $studentNumber): StudentRecord
    {
        if (! preg_match('/^\d{4}-\d{5}$/', $studentNumber)) {
            IntegrationLog::record('student_records', 'verify', 'rejected', 'Malformed student number');
            throw new InvalidStudent('That student number is not in the correct format.');
        }

        return $this->guard('verify', $studentNumber, function () use ($studentNumber) {
            $legacyNumber = str_replace('-', '', $studentNumber);
            $student = DB::connection('legacy')->table('STU_MASTER')->where('STU_NO', $legacyNumber)->first();

            if ($student === null) {
                throw new InvalidStudent('No student record exists for that student number.');
            }

            if ($student->STAT_CD !== 'A') {
                throw new InvalidStudent('This student record is not active. Contact the registrar.');
            }

            $completed = DB::connection('legacy')->table('STU_CRS_HIST')
                ->where('STU_NO', $legacyNumber)
                ->whereNotIn('GRADE', ['F', 'INC', 'DRP'])
                ->pluck('CRS_CD')
                ->all();

            return new StudentRecord($studentNumber, $this->portalName($student->NM_FULL), $completed);
        });
    }

    public function writeEnrollment(string $studentNumber, array $courseCodes, string $termCode, string $paymentReference): void
    {
        $this->guard('write_enrollment', $paymentReference, function () use ($studentNumber, $courseCodes, $termCode, $paymentReference) {
            $legacyNumber = str_replace('-', '', $studentNumber);

            DB::connection('legacy')->transaction(function () use ($legacyNumber, $courseCodes, $termCode, $paymentReference) {
                foreach ($courseCodes as $code) {
                    DB::connection('legacy')->table('ENRL_REC')->updateOrInsert(
                        ['STU_NO' => $legacyNumber, 'CRS_CD' => self::legacyCourseCode($code), 'TERM_CD' => str_replace('-', '', $termCode)],
                        ['PAY_REF' => $paymentReference, 'ENRL_DT' => (int) now()->format('Ymd')],
                    );
                }
            });
        });
    }

    public static function legacyCourseCode(string $code): string
    {
        return str_replace(' ', '', $code);
    }

    private function portalName(string $legacyName): string
    {
        [$last, $first] = array_pad(explode(',', $legacyName, 2), 2, '');

        return trim(ucwords(strtolower(trim($first.' '.$last))));
    }

    private function guard(string $action, string $reference, callable $operation): mixed
    {
        $started = hrtime(true);

        if (! $this->isOnline()) {
            IntegrationLog::record('student_records', $action, 'unavailable', 'Legacy records system is offline', $reference, 0);
            throw new LegacyUnavailable('The student records system is unavailable.');
        }

        try {
            $result = $operation();
            IntegrationLog::record('student_records', $action, 'ok', null, $reference, $this->elapsed($started));

            return $result;
        } catch (InvalidStudent $e) {
            IntegrationLog::record('student_records', $action, 'rejected', $e->getMessage(), $reference, $this->elapsed($started));
            throw $e;
        } catch (QueryException|PDOException $e) {
            IntegrationLog::record('student_records', $action, 'unavailable', 'Legacy database error', $reference, $this->elapsed($started));
            throw new LegacyUnavailable('The student records system is unavailable.', previous: $e);
        }
    }

    private function elapsed(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
