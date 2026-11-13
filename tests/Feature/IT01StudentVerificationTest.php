<?php

namespace Tests\Feature;

use App\Services\SystemSettings;

class IT01StudentVerificationTest extends IntegrationTestCase
{
    public function test_valid_student_proceeds_to_course_validation(): void
    {
        $this->postJson('/api/v1/registrations', [
            'student_number' => '2024-00001',
            'course_ids' => [$this->course('GE 101')->id],
        ], $this->gateway())
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment');
    }

    public function test_invalid_student_is_denied_with_a_message(): void
    {
        $this->postJson('/api/v1/registrations', [
            'student_number' => '2099-99999',
            'course_ids' => [$this->course('GE 101')->id],
        ], $this->gateway())
            ->assertNotFound()
            ->assertJsonPath('error', 'invalid_student')
            ->assertJsonPath('message', 'No student record exists for that student number.');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_inactive_and_malformed_students_are_denied(): void
    {
        $this->getJson('/api/v1/students/2024-00009', $this->gateway())->assertNotFound();
        $this->getJson('/api/v1/students/not-a-number', $this->gateway())->assertNotFound();
    }

    public function test_gateway_rejects_a_missing_key(): void
    {
        $this->getJson('/api/v1/courses')->assertUnauthorized();
    }

    public function test_records_outage_returns_503_and_creates_nothing(): void
    {
        app(SystemSettings::class)->setLegacyOnline(false);

        $this->postJson('/api/v1/registrations', [
            'student_number' => '2024-00001',
            'course_ids' => [$this->course('GE 101')->id],
        ], $this->gateway())->assertStatus(503);

        $this->assertDatabaseCount('registrations', 0);
    }
}
