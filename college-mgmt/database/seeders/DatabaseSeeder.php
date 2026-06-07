<?php

namespace Database\Seeders;

use App\Models\{User, Department, Course, Subject, Classroom, AcademicYear, Semester,
    Teacher, Student, TimetableSlot, TimetableEntry, Notice,
    Enrollment, FeeStructure, FeePayment, Exam, ExamResult, Attendance};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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

        // Timetable entries
        $ttDefs = [
            ['course' => 'BTCS', 'subject' => 'CS101', 'teacher' => 'TCH001', 'room' => 'LH-A', 'slot' => 1, 'day' => 1],
            ['course' => 'BTCS', 'subject' => 'CS102', 'teacher' => 'TCH001', 'room' => 'LH-A', 'slot' => 2, 'day' => 1],
            ['course' => 'BTCS', 'subject' => 'CS103', 'teacher' => 'TCH002', 'room' => 'LH-B', 'slot' => 1, 'day' => 2],
            ['course' => 'BTCS', 'subject' => 'CS105', 'teacher' => 'TCH002', 'room' => 'LH-B', 'slot' => 3, 'day' => 2],
            ['course' => 'BTCS', 'subject' => 'CS104', 'teacher' => 'TCH001', 'room' => 'CL-1', 'slot' => 6, 'day' => 3],
            ['course' => 'BTEC', 'subject' => 'EC101', 'teacher' => 'TCH003', 'room' => 'SR-1', 'slot' => 1, 'day' => 1],
            ['course' => 'BTEC', 'subject' => 'EC102', 'teacher' => 'TCH003', 'room' => 'SR-1', 'slot' => 2, 'day' => 2],
        ];
        $timetableEntries = [];
        foreach ($ttDefs as $tt) {
            $timetableEntries[] = TimetableEntry::firstOrCreate([
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

        // 10 Students
        $studentDefs = [
            ['name'=>'Aarav Sharma',  'email'=>'aarav@college.com',   'dept'=>'CS','course'=>'BTCS','sem'=>5,'enr'=>'ENR2025001','roll'=>'CS-5-01','year'=>'2022-08-01'],
            ['name'=>'Priya Patel',   'email'=>'priya@college.com',   'dept'=>'CS','course'=>'BTCS','sem'=>5,'enr'=>'ENR2025002','roll'=>'CS-5-02','year'=>'2022-08-01'],
            ['name'=>'Rohan Mehta',   'email'=>'rohan@college.com',   'dept'=>'CS','course'=>'BTCS','sem'=>3,'enr'=>'ENR2024001','roll'=>'CS-3-01','year'=>'2023-08-01'],
            ['name'=>'Sneha Gupta',   'email'=>'sneha@college.com',   'dept'=>'CS','course'=>'BTCS','sem'=>1,'enr'=>'ENR2026001','roll'=>'CS-1-01','year'=>'2025-08-01'],
            ['name'=>'Arjun Singh',   'email'=>'arjun.s@college.com', 'dept'=>'EC','course'=>'BTEC','sem'=>5,'enr'=>'ENR2025003','roll'=>'EC-5-01','year'=>'2022-08-01'],
            ['name'=>'Divya Nair',    'email'=>'divya@college.com',   'dept'=>'EC','course'=>'BTEC','sem'=>5,'enr'=>'ENR2025004','roll'=>'EC-5-02','year'=>'2022-08-01'],
            ['name'=>'Kiran Reddy',   'email'=>'kiran@college.com',   'dept'=>'ME','course'=>'BTME','sem'=>5,'enr'=>'ENR2025005','roll'=>'ME-5-01','year'=>'2022-08-01'],
            ['name'=>'Meera Joshi',   'email'=>'meera@college.com',   'dept'=>'CS','course'=>'BTCS','sem'=>7,'enr'=>'ENR2023001','roll'=>'CS-7-01','year'=>'2021-08-01'],
            ['name'=>'Vikram Das',    'email'=>'vikram@college.com',  'dept'=>'CS','course'=>'BTCS','sem'=>7,'enr'=>'ENR2023002','roll'=>'CS-7-02','year'=>'2021-08-01'],
            ['name'=>'Neha Verma',    'email'=>'neha@college.com',    'dept'=>'ME','course'=>'BTME','sem'=>3,'enr'=>'ENR2024002','roll'=>'ME-3-01','year'=>'2023-08-01'],
        ];

        $students = [];
        foreach ($studentDefs as $sd) {
            $u = User::firstOrCreate(['email' => $sd['email']], [
                'name'     => $sd['name'],
                'password' => Hash::make('password'),
            ]);
            $u->assignRole('student');
            $students[$sd['enr']] = Student::firstOrCreate(['enrollment_number' => $sd['enr']], [
                'user_id'           => $u->id,
                'department_id'     => $departments[$sd['dept']]->id,
                'course_id'         => $courses[$sd['course']]->id,
                'enrollment_number' => $sd['enr'],
                'roll_number'       => $sd['roll'],
                'current_semester'  => $sd['sem'],
                'admission_date'    => $sd['year'],
            ]);
        }

        // Enrollments
        $enrollmentMap = [
            'ENR2025001' => ['CS101','CS102','CS103','CS104','CS105'],
            'ENR2025002' => ['CS101','CS102','CS103','CS104','CS105'],
            'ENR2024001' => ['CS101','CS102','CS103'],
            'ENR2026001' => ['CS101'],
            'ENR2025003' => ['EC101','EC102'],
            'ENR2025004' => ['EC101','EC102'],
            'ENR2025005' => ['ME101','ME102'],
            'ENR2023001' => ['CS102','CS103','CS105'],
            'ENR2023002' => ['CS102','CS103','CS105'],
            'ENR2024002' => ['CS101','CS102','CS103'],
        ];

        foreach ($enrollmentMap as $enr => $subjectCodes) {
            $student = $students[$enr];
            foreach ($subjectCodes as $code) {
                Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subjects[$code]->id, 'semester_id' => $semester->id],
                    ['status' => 'active']
                );
            }
        }

        // Fee Structures
        $feeStructDefs = [
            ['course'=>'BTCS','type'=>'tuition','amount'=>85000,'description'=>'B.Tech CS Annual Tuition'],
            ['course'=>'BTCS','type'=>'lab',    'amount'=>15000,'description'=>'CS Lab Fee'],
            ['course'=>'BTEC','type'=>'tuition','amount'=>82000,'description'=>'B.Tech EC Annual Tuition'],
            ['course'=>'BTME','type'=>'tuition','amount'=>80000,'description'=>'B.Tech ME Annual Tuition'],
        ];
        $feeStructures = [];
        foreach ($feeStructDefs as $fs) {
            $feeStructures[$fs['course'].'_'.$fs['type']] = FeeStructure::firstOrCreate(
                ['course_id' => $courses[$fs['course']]->id, 'academic_year_id' => $year->id, 'fee_type' => $fs['type']],
                ['amount' => $fs['amount'], 'description' => $fs['description']]
            );
        }

        // Fee Payments (skip Sneha ENR2026001 and Neha ENR2024002)
        $tuitionMap = [
            'BTCS' => $feeStructures['BTCS_tuition'],
            'BTEC' => $feeStructures['BTEC_tuition'],
            'BTME' => $feeStructures['BTME_tuition'],
        ];
        $skipPayment = ['ENR2026001', 'ENR2024002'];
        $i = 1;
        foreach ($studentDefs as $sd) {
            if (!in_array($sd['enr'], $skipPayment)) {
                $student   = $students[$sd['enr']];
                $feeStruct = $tuitionMap[$sd['course']];
                FeePayment::firstOrCreate(
                    ['student_id' => $student->id, 'fee_structure_id' => $feeStruct->id, 'receipt_number' => 'RCP' . str_pad($i, 5, '0', STR_PAD_LEFT)],
                    [
                        'amount_paid'    => $feeStruct->amount,
                        'payment_date'   => '2025-08-15',
                        'payment_method' => 'online',
                        'status'         => 'paid',
                    ]
                );
            }
            $i++;
        }

        // Exams
        $examDefs = [
            ['name'=>'Internal Assessment 1','subject'=>'CS101','type'=>'internal','date'=>'2025-09-15','max_marks'=>30],
            ['name'=>'Internal Assessment 1','subject'=>'CS102','type'=>'internal','date'=>'2025-09-16','max_marks'=>30],
            ['name'=>'Internal Assessment 1','subject'=>'CS103','type'=>'internal','date'=>'2025-09-17','max_marks'=>30],
            ['name'=>'Midterm Exam',          'subject'=>'CS101','type'=>'midterm', 'date'=>'2025-10-15','max_marks'=>50],
            ['name'=>'Midterm Exam',          'subject'=>'CS102','type'=>'midterm', 'date'=>'2025-10-16','max_marks'=>50],
        ];
        $exams = [];
        foreach ($examDefs as $ed) {
            $exams[] = Exam::firstOrCreate(
                ['name' => $ed['name'], 'subject_id' => $subjects[$ed['subject']]->id],
                [
                    'semester_id'   => $semester->id,
                    'type'          => $ed['type'],
                    'exam_date'     => $ed['date'],
                    'total_marks'   => $ed['max_marks'],
                    'passing_marks' => (int) round($ed['max_marks'] * 0.4),
                ]
            );
        }

        // Exam Results for Aarav and Priya
        $aarav      = $students['ENR2025001'];
        $priya      = $students['ENR2025002'];
        $aaravMarks = [25, 27, 22, 42, 44];
        $priyaMarks = [20, 18, 24, 35, 38];

        foreach ($exams as $idx => $exam) {
            ExamResult::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $aarav->id],
                ['marks_obtained' => $aaravMarks[$idx], 'is_absent' => false]
            );
            ExamResult::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $priya->id],
                ['marks_obtained' => $priyaMarks[$idx], 'is_absent' => false]
            );
        }

        // Attendance (3 weeks for timetable entries)
        // day_of_week: 1=Mon, 2=Tue, 3=Wed
        $attendanceDates = [
            1 => ['2025-09-01', '2025-09-08', '2025-09-15'],
            2 => ['2025-09-02', '2025-09-09', '2025-09-16'],
            3 => ['2025-09-03', '2025-09-10', '2025-09-17'],
        ];

        $counter = 0;
        foreach ($timetableEntries as $entry) {
            $dayDates         = $attendanceDates[$entry->day_of_week] ?? [];
            $enrolledStudents = Student::where('course_id', $entry->course_id)->get();

            foreach ($enrolledStudents as $student) {
                foreach ($dayDates as $date) {
                    if ($counter % 10 === 0) {
                        $status = 'absent';
                    } elseif ($counter % 20 === 0) {
                        $status = 'late';
                    } else {
                        $status = 'present';
                    }
                    Attendance::firstOrCreate(
                        ['student_id' => $student->id, 'timetable_entry_id' => $entry->id, 'date' => $date],
                        ['status' => $status]
                    );
                    $counter++;
                }
            }
        }

        $this->call([DemoDataSeeder::class]);
        $this->call([RoleFeatureAccessSeeder::class]);

        $this->command->info('College Management System seeded successfully!');
        $this->command->info('Admin: admin@college.com / password');
        $this->command->info('Teacher: ravi@college.com / password');
        $this->command->info('Students: aarav@college.com, priya@college.com, rohan@college.com, ... / password');
    }
}
