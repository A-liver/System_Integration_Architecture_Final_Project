<?php

namespace App\Services\Records;

final readonly class StudentRecord
{
    /** @param list<string> $completedCourseCodes */
    public function __construct(
        public string $studentNumber,
        public string $fullName,
        public array $completedCourseCodes,
    ) {}
}
