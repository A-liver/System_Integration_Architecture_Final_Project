<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for demonstrating the midterm design. Every person and course
 * here is a placeholder. Sign-in details are in README.md.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'sia-demo-2026';

    public function run(): void
    {
        foreach ([
            ['Demo Student One', '2024-00001'],
            ['Demo Student Two', '2024-00002'],
        ] as [$name, $number]) {
            User::create(['name' => $name, 'login_id' => $number, 'role' => User::ROLE_STUDENT, 'student_number' => $number, 'password' => self::DEMO_PASSWORD]);
        }

        User::create(['name' => 'Demo Registrar', 'login_id' => 'registrar', 'role' => User::ROLE_STAFF, 'password' => self::DEMO_PASSWORD]);

        $legacy = DB::connection('legacy');
        $legacy->table('STU_MASTER')->insert([
            ['STU_NO' => '202400001', 'NM_FULL' => 'ONE,DEMO STUDENT', 'STAT_CD' => 'A'],
            ['STU_NO' => '202400002', 'NM_FULL' => 'TWO,DEMO STUDENT', 'STAT_CD' => 'A'],
            ['STU_NO' => '202400009', 'NM_FULL' => 'INACTIVE,DEMO STUDENT', 'STAT_CD' => 'I'],
        ]);
        $legacy->table('STU_CRS_HIST')->insert([
            ['STU_NO' => '202400001', 'CRS_CD' => 'CS101', 'GRADE' => '1.50'],
            ['STU_NO' => '202400001', 'CRS_CD' => 'MATH101', 'GRADE' => '2.00'],
            ['STU_NO' => '202400002', 'CRS_CD' => 'CS101', 'GRADE' => 'F'],
        ]);

        $courses = [];
        foreach ([
            ['CS 101', 'Introduction to Computing', 3, 'Mon, Wed 09:00 to 10:30', 40, 12],
            ['MATH 101', 'College Algebra', 3, 'Tue, Thu 09:00 to 10:30', 40, 20],
            ['CS 102', 'Computer Programming', 3, 'Mon, Wed 10:30 to 12:00', 35, 18],
            ['CS 201', 'Data Structures', 3, 'Tue, Thu 13:00 to 14:30', 30, 11],
            ['CS 202', 'Database Systems', 3, 'Mon, Wed 13:00 to 14:30', 30, 29],
            ['IT 301', 'Systems Integration and Architecture', 3, 'Fri 08:00 to 11:00', 30, 14],
            ['IT 302', 'Information Assurance', 3, 'Fri 13:00 to 16:00', 2, 2],
            ['MATH 201', 'Discrete Mathematics', 3, 'Tue, Thu 10:30 to 12:00', 35, 9],
            ['GE 101', 'Purposive Communication', 3, 'Sat 08:00 to 11:00', 45, 30],
            ['PE 101', 'Physical Fitness', 2, 'Sat 13:00 to 15:00', 50, 22],
        ] as [$code, $title, $units, $schedule, $capacity, $taken]) {
            $courses[$code] = Course::create(compact('code', 'title', 'units', 'schedule', 'capacity') + ['seats_taken' => $taken]);
        }

        foreach ([
            'CS 102' => ['CS 101'],
            'CS 201' => ['CS 102'],
            'CS 202' => ['CS 101'],
            'IT 301' => ['CS 202', 'CS 201'],
            'IT 302' => ['CS 101'],
            'MATH 201' => ['MATH 101'],
        ] as $code => $prerequisites) {
            $courses[$code]->prerequisites()->attach(collect($prerequisites)->map(fn ($p) => $courses[$p]->id));
        }
    }
}
