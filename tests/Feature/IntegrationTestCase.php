<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rebuilt per test so the suite also runs against real MySQL databases.
        $this->artisan('migrate:fresh');
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
    }

    protected function gateway(): array
    {
        return ['X-Gateway-Key' => 'test-gateway-key'];
    }

    protected function course(string $code): Course
    {
        return Course::where('code', $code)->firstOrFail();
    }

    protected function studentUser(string $number = '2024-00001'): User
    {
        return User::where('student_number', $number)->firstOrFail();
    }

    protected function staffUser(): User
    {
        return User::where('role', User::ROLE_STAFF)->firstOrFail();
    }
}
