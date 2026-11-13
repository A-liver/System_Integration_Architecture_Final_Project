<?php

namespace App\Services\Records;

interface StudentRecords
{
    /**
     * @throws \App\Exceptions\InvalidStudent
     * @throws \App\Exceptions\LegacyUnavailable
     */
    public function verify(string $studentNumber): StudentRecord;

    /**
     * Safe to repeat: the same student, course and term never produce a second row.
     *
     * @param  list<string>  $courseCodes
     *
     * @throws \App\Exceptions\LegacyUnavailable
     */
    public function writeEnrollment(string $studentNumber, array $courseCodes, string $termCode, string $paymentReference): void;

    public function isOnline(): bool;
}
