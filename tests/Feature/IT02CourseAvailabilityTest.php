<?php

namespace Tests\Feature;

class IT02CourseAvailabilityTest extends IntegrationTestCase
{
    private function submit(string $student, string $code)
    {
        return $this->postJson('/api/v1/registrations', [
            'student_number' => $student,
            'course_ids' => [$this->course($code)->id],
        ], $this->gateway());
    }

    public function test_eligible_request_proceeds_and_reserves_a_seat(): void
    {
        $before = $this->course('CS 102')->seats_taken;

        $this->submit('2024-00001', 'CS 102')->assertCreated();

        $this->assertSame($before + 1, $this->course('CS 102')->seats_taken);
    }

    public function test_unmet_prerequisite_is_rejected_with_explanation(): void
    {
        // Student two failed CS 101, so the record must not count as completed.
        $this->submit('2024-00002', 'CS 102')
            ->assertStatus(422)
            ->assertJsonPath('reasons.0', 'CS 102: prerequisite not met (CS 101).');
    }

    public function test_full_course_is_rejected_with_explanation(): void
    {
        $this->submit('2024-00001', 'IT 302')
            ->assertStatus(422)
            ->assertJsonPath('reasons.0', 'IT 302: no seats left.');
    }

    public function test_last_seat_cannot_be_taken_twice(): void
    {
        $this->course('GE 101')->update(['capacity' => 30, 'seats_taken' => 29]);

        $this->submit('2024-00001', 'GE 101')->assertCreated();
        $this->submit('2024-00002', 'GE 101')
            ->assertStatus(422)
            ->assertJsonPath('reasons.0', 'GE 101: no seats left.');

        $this->assertSame(30, $this->course('GE 101')->seats_taken);
    }

    public function test_second_open_registration_is_rejected(): void
    {
        $this->submit('2024-00001', 'GE 101')->assertCreated();

        $this->submit('2024-00001', 'PE 101')
            ->assertStatus(422)
            ->assertJsonPath('reasons.0', 'You already have an open registration this term. Finish or cancel it first.');
    }
}
