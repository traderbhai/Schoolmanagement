<?php

namespace Database\Seeders;

use App\Models\{User, Department, Course, Subject, Classroom, AcademicYear, Semester,
    Teacher, Student, TimetableSlot, TimetableEntry, Notice};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $roles = ['admin', 'teacher', 'student'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Admin user
        $admin = User::firstOrCreate(['email' => 'admin@college.com'], [
            'name'     => 'Admin User',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        // Departments
        $depts = [
            ['name' => 'Computer Science',  'code' => 'CS',  'head_name' => 'Dr. Alice Kumar'],
            ['name' => 'Electronics',        'code' => 'EC',  'head_name' => 'Dr. Bob Sharma'],
            ['name' => 'Mechanical',         'code' => 'ME',  'head_name' => 'Dr. Carol Verma'],
            ['name' => 'Civil Engineering',  'code' => 'CE',  'head_name' => 'Dr. David Singh'],
        ];
        $departments = [];
        foreach ($depts as $d) {
            $departments[$d['code']] = Department::firstOrCreate(['code' => $d['code']], $d);
        }

        // Courses
        $courses = [];
        $courseDefs = [
            ['dept' => 'CS', 'name' => 'B.Tech Computer Science', 'code' => 'BTCS', 'duration_years' => 4, 'total_semesters' => 8],
            ['dept' => 'EC', 'name' => 'B.Tech Electronics',      'code' => 'BTEC', 'duration_years' => 4, 'total_semesters' => 8],
            ['dept' => 'ME', 'name' => 'B.Tech Mechanical',        'code' => 'BTME', 'duration_years' => 4, 'total_semesters' => 8],
        ];
        foreach ($courseDefs as $c) {
            $courses[$c['code']] = Course::firstOrCreate(['code' => $c['code']], [
                'department_id'   => $departments[$c['dept']]->id,
                'name'            => $c['name'],
                'code'            => $c['code'],
                'duration_years'  => $c['duration_years'],
                'total_semesters' => $c['total_semesters'],
            ]);
        }

        // Subjects
        $subjectDefs = [
            ['dept' => 'CS', 'name' => 'Data Structures',        'code' => 'CS101', 'type' => 'theory',    'credits' => 4],
            ['dept' => 'CS', 'name' => 'Algorithms',             'code' => 'CS102', 'type' => 'theory',    'credits' => 4],
            ['dept' => 'CS', 'name' => 'Database Systems',       'code' => 'CS103', 'type' => 'theory',    'credits' => 3],
            ['dept' => 'CS', 'name' => 'OS Lab',                 'code' => 'CS104', 'type' => 'practical', 'credits' => 2],
            ['dept' => 'CS', 'name' => 'Web Technologies',       'code' => 'CS105', 'type' => 'theory',    'credits' => 3],
            ['dept' => 'EC', 'name' => 'Digital Electronics',    'code' => 'EC101', 'type' => 'theory',    'credits' => 4],
            ['dept' => 'EC', 'name' => 'Signals & Systems',      'code' => 'EC102', 'type' => 'theory',    'credits' => 4],
            ['dept' => 'ME', 'name' => 'Thermodynamics',         'code' => 'ME101', 'type' => 'theory',    'credits' => 4],
            ['dept' => 'ME', 'name' => 'Fluid Mechanics',        'code' => 'ME102', 'type' => 'theory',    'credits' => 3],
        ];
        $subjects = [];
        foreach ($subjectDefs as $s) {
            $subjects[$s['code']] = Subject::firstOrCreate(['code' => $s['code']], [
                'department_id'  => $departments[$s['dept']]->id,
                'name'           => $s['name'],
                'code'           => $s['code'],
                'type'           => $s['type'],
                'credits'        => $s['credits'],
                'hours_per_week' => $s['credits'],
            ]);
        }

        // Classrooms
        $roomDefs = [
            ['name' => 'Lecture Hall A', 'room_number' => 'LH-A', 'capacity' => 80,  'type' => 'lecture', 'building' => 'Main Block', 'has_projector' => true],
            ['name' => 'Lecture Hall B', 'room_number' => 'LH-B', 'capacity' => 60,  'type' => 'lecture', 'building' => 'Main Block', 'has_projector' => true],
            ['name' => 'CS Lab 1',       'room_number' => 'CL-1', 'capacity' => 40,  'type' => 'lab',     'building' => 'CS Block',  'has_lab' => true],
            ['name' => 'Seminar Room',   'room_number' => 'SR-1', 'capacity' => 30,  'type' => 'seminar', 'building' => 'Main Block'],
        ];
        $rooms = [];
        foreach ($roomDefs as $r) {
            $rooms[$r['room_number']] = Classroom::firstOrCreate(['room_number' => $r['room_number']], $r);
        }

        // Academic Year
        $year = AcademicYear::firstOrCreate(['name' => '2025-2026'], [
            'start_year' => 2025,
            'end_year'   => 2026,
            'start_date' => '2025-08-01',
            'end_date'   => '2026-06-30',
            'is_current' => true,
        ]);

        // Semester
        $semester = Semester::firstOrCreate(['name' => 'Semester 5 (2025-26)'], [
            'academic_year_id' => $year->id,
            'number'           => 5,
            'start_date'       => '2025-08-01',
            'end_date'         => '2025-12-31',
            'is_current'       => true,
        ]);

        // Timetable Slots
        $slotDefs = [
            ['name' => '1st Period',  'start_time' => '08:00', 'end_time' => '09:00', 'sort_order' => 1],
            ['name' => '2nd Period',  'start_time' => '09:00', 'end_time' => '10:00', 'sort_order' => 2],
            ['name' => '3rd Period',  'start_time' => '10:00', 'end_time' => '11:00', 'sort_order' => 3],
            ['name' => 'Break',       'start_time' => '11:00', 'end_time' => '11:15', 'sort_order' => 4, 'is_break' => true],
            ['name' => '4th Period',  'start_time' => '11:15', 'end_time' => '12:15', 'sort_order' => 5],
            ['name' => '5th Period',  'start_time' => '12:15', 'end_time' => '13:15', 'sort_order' => 6],
            ['name' => 'Lunch',       'start_time' => '13:15', 'end_time' => '14:00', 'sort_order' => 7, 'is_break' => true],
            ['name' => '6th Period',  'start_time' => '14:00', 'end_time' => '15:00', 'sort_order' => 8],
            ['name' => '7th Period',  'start_time' => '15:00', 'end_time' => '16:00', 'sort_order' => 9],
        ];
        $slots = [];
        foreach ($slotDefs as $s) {
            $slots[$s['sort_order']] = TimetableSlot::firstOrCreate(
                ['name' => $s['name']],
                array_merge($s, ['is_break' => $s['is_break'] ?? false])
            );
        }

        // Teachers
        $teacherDefs = [
            ['name' => 'Prof. Ravi Mehta',   'email' => 'ravi@college.com',   'dept' => 'CS', 'employee_id' => 'TCH001', 'designation' => 'Associate Professor'],
            ['name' => 'Prof. Sunita Patel', 'email' => 'sunita@college.com', 'dept' => 'CS', 'employee_id' => 'TCH002', 'designation' => 'Assistant Professor'],
            ['name' => 'Prof. Arjun Roy',    'email' => 'arjun@college.com',  'dept' => 'EC', 'employee_id' => 'TCH003', 'designation' => 'Professor'],
        ];
        $teachers = [];
        foreach ($teacherDefs as $t) {
            $user = User::firstOrCreate(['email' => $t['email']], [
                'name'     => $t['name'],
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('teacher');
            $teachers[$t['employee_id']] = Teacher::firstOrCreate(['employee_id' => $t['employee_id']], [
                'user_id'         => $user->id,
                'department_id'   => $departments[$t['dept']]->id,
                'employee_id'     => $t['employee_id'],
                'designation'     => $t['designation'],
                'employment_type' => 'full_time',
            ]);
        }

        // Sample student
        $studentUser = User::firstOrCreate(['email' => 'student@college.com'], [
            'name'     => 'John Student',
            'password' => Hash::make('password'),
        ]);
        $studentUser->assignRole('student');
        Student::firstOrCreate(['enrollment_number' => 'ENR2025001'], [
            'user_id'           => $studentUser->id,
            'department_id'     => $departments['CS']->id,
            'course_id'         => $courses['BTCS']->id,
            'enrollment_number' => 'ENR2025001',
            'roll_number'       => 'CS-5-01',
            'current_semester'  => 5,
            'admission_date'    => '2022-08-01',
        ]);

        // Sample timetable entries (no conflicts guaranteed by unique slots)
        $ttDefs = [
            ['course' => 'BTCS', 'subject' => 'CS101', 'teacher' => 'TCH001', 'room' => 'LH-A', 'slot' => 1, 'day' => 1],
            ['course' => 'BTCS', 'subject' => 'CS102', 'teacher' => 'TCH001', 'room' => 'LH-A', 'slot' => 2, 'day' => 1],
            ['course' => 'BTCS', 'subject' => 'CS103', 'teacher' => 'TCH002', 'room' => 'LH-B', 'slot' => 1, 'day' => 2],
            ['course' => 'BTCS', 'subject' => 'CS105', 'teacher' => 'TCH002', 'room' => 'LH-B', 'slot' => 3, 'day' => 2],
            ['course' => 'BTCS', 'subject' => 'CS104', 'teacher' => 'TCH001', 'room' => 'CL-1', 'slot' => 6, 'day' => 3],
            ['course' => 'BTEC', 'subject' => 'EC101', 'teacher' => 'TCH003', 'room' => 'SR-1', 'slot' => 1, 'day' => 1],
            ['course' => 'BTEC', 'subject' => 'EC102', 'teacher' => 'TCH003', 'room' => 'SR-1', 'slot' => 2, 'day' => 2],
        ];
        foreach ($ttDefs as $tt) {
            TimetableEntry::firstOrCreate([
                'semester_id'       => $semester->id,
                'course_id'         => $courses[$tt['course']]->id,
                'subject_id'        => $subjects[$tt['subject']]->id,
                'timetable_slot_id' => $slots[$tt['slot']]->id,
                'day_of_week'       => $tt['day'],
            ], [
                'teacher_id'   => $teachers[$tt['teacher']]->id,
                'classroom_id' => $rooms[$tt['room']]->id,
                'is_active'    => true,
            ]);
        }

        // Notice
        Notice::firstOrCreate(['title' => 'Welcome to the New Academic Year!'], [
            'user_id'      => $admin->id,
            'content'      => 'We welcome all students and faculty to the 2025-26 academic year. Classes begin August 1st.',
            'audience'     => 'all',
            'publish_date' => '2025-08-01',
            'is_published' => true,
        ]);

        $this->command->info('College Management System seeded successfully!');
        $this->command->info('Admin: admin@college.com / password');
        $this->command->info('Teacher: ravi@college.com / password');
        $this->command->info('Student: student@college.com / password');
    }
}
