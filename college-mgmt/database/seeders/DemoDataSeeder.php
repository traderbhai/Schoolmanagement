<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Student, Teacher, Department, Course, Subject, AcademicYear, Semester, Classroom, TimetableSlot, TimetableEntry, Notice, FeeStructure, FeePayment, Enrollment};
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        // Ensure roles exist
        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Admin user
        $admin = User::firstOrCreate(['email' => 'admin@demo.edu'], [
            'name' => 'Admin User',
            'password' => Hash::make('password123'),
            'email_verified_at' => now()
        ]);
        $admin->assignRole('admin');

        // 1. Academic Years (2 years)
        $ay1 = AcademicYear::firstOrCreate(['name' => '2023-24'], [
            'start_date' => '2023-06-01', 'end_date' => '2024-05-31', 'is_current' => false,
            'start_year' => 2023, 'end_year' => 2024,
        ]);
        $ay2 = AcademicYear::firstOrCreate(['name' => '2024-25'], [
            'start_date' => '2024-06-01', 'end_date' => '2025-05-31', 'is_current' => true,
            'start_year' => 2024, 'end_year' => 2025,
        ]);

        // 2. Departments (3)
        $depts = [];
        foreach ([
            ['name' => 'Management Studies', 'code' => 'MGT', 'description' => 'PGDM & MBA programs'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN', 'description' => 'Finance specialization'],
            ['name' => 'Marketing & Sales', 'code' => 'MKT', 'description' => 'Marketing specialization'],
        ] as $d) {
            $depts[] = Department::firstOrCreate(['code' => $d['code']], $d);
        }

        // 3. Courses (2)
        $pgdm = Course::firstOrCreate(['code' => 'PGDM'], [
            'name' => 'Post Graduate Diploma in Management',
            'department_id' => $depts[0]->id,
            'duration_years' => 2,
            'total_semesters' => 4,
            'description' => 'AICTE approved PGDM program',
            'is_active' => true,
        ]);
        $pgdmFin = Course::firstOrCreate(['code' => 'PGDM-FIN'], [
            'name' => 'PGDM Finance',
            'department_id' => $depts[1]->id,
            'duration_years' => 2,
            'total_semesters' => 4,
            'description' => 'Finance specialization',
            'is_active' => true,
        ]);

        // 4. Semesters
        $sems = [];
        foreach ([
            ['name' => 'Semester I (2024-25)', 'number' => 1, 'academic_year_id' => $ay2->id, 'start_date' => '2024-07-01', 'end_date' => '2024-11-30', 'is_current' => false],
            ['name' => 'Semester II (2024-25)', 'number' => 2, 'academic_year_id' => $ay2->id, 'start_date' => '2025-01-01', 'end_date' => '2025-05-31', 'is_current' => true],
        ] as $s) {
            $sems[] = Semester::firstOrCreate(['name' => $s['name']], $s);
        }
        $currentSem = $sems[1];
        $sem1 = $sems[0];

        // 5. Subjects (8 subjects)
        $subjects = [];
        $subjectData = [
            ['name' => 'Management Principles', 'code' => 'MGT101', 'credits' => 4, 'type' => 'theory', 'department_id' => $depts[0]->id],
            ['name' => 'Organizational Behaviour', 'code' => 'MGT102', 'credits' => 3, 'type' => 'theory', 'department_id' => $depts[0]->id],
            ['name' => 'Business Statistics', 'code' => 'MGT103', 'credits' => 3, 'type' => 'theory', 'department_id' => $depts[0]->id],
            ['name' => 'Financial Accounting', 'code' => 'FIN101', 'credits' => 4, 'type' => 'theory', 'department_id' => $depts[1]->id],
            ['name' => 'Marketing Management', 'code' => 'MKT101', 'credits' => 4, 'type' => 'theory', 'department_id' => $depts[2]->id],
            ['name' => 'Business Communication', 'code' => 'MGT201', 'credits' => 2, 'type' => 'theory', 'department_id' => $depts[0]->id],
            ['name' => 'Case Study Workshop', 'code' => 'MGT202', 'credits' => 2, 'type' => 'practical', 'department_id' => $depts[0]->id],
            ['name' => 'Corporate Finance', 'code' => 'FIN201', 'credits' => 4, 'type' => 'theory', 'department_id' => $depts[1]->id],
        ];
        foreach ($subjectData as $s) {
            $subjects[] = Subject::firstOrCreate(['code' => $s['code']], $s);
        }

        // 6. Classrooms (3)
        $rooms = [];
        foreach ([
            ['room_number' => 'LH-101', 'name' => 'Lecture Hall 101', 'type' => 'lecture', 'capacity' => 60, 'building' => 'Main Block', 'floor' => '1'],
            ['room_number' => 'LH-201', 'name' => 'Lecture Hall 201', 'type' => 'lecture', 'capacity' => 60, 'building' => 'Main Block', 'floor' => '2'],
            ['room_number' => 'CR-Lab', 'name' => 'Computer Lab', 'type' => 'lab', 'capacity' => 40, 'building' => 'Tech Block', 'floor' => '1'],
        ] as $r) {
            $rooms[] = Classroom::firstOrCreate(['room_number' => $r['room_number']], $r);
        }

        // 7. Timetable Slots (read existing or create)
        $slots = TimetableSlot::orderBy('sort_order')->get();
        if ($slots->isEmpty()) {
            $slotData = [
                ['name' => '1st Period', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1],
                ['name' => '2nd Period', 'start_time' => '10:00', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 2],
                ['name' => 'Break', 'start_time' => '11:00', 'end_time' => '11:15', 'is_break' => true, 'sort_order' => 3],
                ['name' => '3rd Period', 'start_time' => '11:15', 'end_time' => '12:15', 'is_break' => false, 'sort_order' => 4],
                ['name' => '4th Period', 'start_time' => '12:15', 'end_time' => '13:15', 'is_break' => false, 'sort_order' => 5],
                ['name' => 'Lunch', 'start_time' => '13:15', 'end_time' => '14:00', 'is_break' => true, 'sort_order' => 6],
                ['name' => '5th Period', 'start_time' => '14:00', 'end_time' => '15:00', 'is_break' => false, 'sort_order' => 7],
                ['name' => '6th Period', 'start_time' => '15:00', 'end_time' => '16:00', 'is_break' => false, 'sort_order' => 8],
            ];
            foreach ($slotData as $s) {
                TimetableSlot::create($s);
            }
            $slots = TimetableSlot::orderBy('sort_order')->get();
        }

        // 8. Teachers (4 teachers)
        $teacherData = [
            ['name' => 'Dr. Anjali Sharma', 'email' => 'anjali@demo.edu', 'employee_id' => 'TCH001', 'designation' => 'Professor', 'qualification' => 'Ph.D Management', 'department_id' => $depts[0]->id],
            ['name' => 'Prof. Rakesh Verma', 'email' => 'rakesh@demo.edu', 'employee_id' => 'TCH002', 'designation' => 'Associate Professor', 'qualification' => 'MBA Finance', 'department_id' => $depts[1]->id],
            ['name' => 'Ms. Priya Nair', 'email' => 'priya.n@demo.edu', 'employee_id' => 'TCH003', 'designation' => 'Assistant Professor', 'qualification' => 'MBA Marketing', 'department_id' => $depts[2]->id],
            ['name' => 'Dr. Suresh Menon', 'email' => 'suresh@demo.edu', 'employee_id' => 'TCH004', 'designation' => 'Professor', 'qualification' => 'Ph.D Finance', 'department_id' => $depts[1]->id],
        ];
        $teachers = [];
        foreach ($teacherData as $td) {
            $user = User::firstOrCreate(['email' => $td['email']], [
                'name' => $td['name'], 'password' => Hash::make('password123'), 'email_verified_at' => now()
            ]);
            $user->assignRole('teacher');
            $teacher = Teacher::firstOrCreate(['employee_id' => $td['employee_id']], [
                'user_id' => $user->id,
                'department_id' => $td['department_id'],
                'designation' => $td['designation'],
                'qualification' => $td['qualification'],
                'employment_type' => 'full_time',
                'status' => 'active',
                'date_of_joining' => '2020-06-01',
            ]);
            $teachers[] = $teacher;
        }

        // 9. Students (12 students)
        $studentNames = [
            ['Arjun Kapoor', 'arjun.k@demo.edu', 'PG24001'],
            ['Sneha Reddy', 'sneha.r@demo.edu', 'PG24002'],
            ['Vikram Singh', 'vikram.s@demo.edu', 'PG24003'],
            ['Pooja Mehta', 'pooja.m@demo.edu', 'PG24004'],
            ['Rahul Joshi', 'rahul.j@demo.edu', 'PG24005'],
            ['Neha Patel', 'neha.p@demo.edu', 'PG24006'],
            ['Aditya Kumar', 'aditya.k@demo.edu', 'PG24007'],
            ['Divya Iyer', 'divya.i@demo.edu', 'PG24008'],
            ['Sanjay Gupta', 'sanjay.g@demo.edu', 'PG24009'],
            ['Ritika Shah', 'ritika.s@demo.edu', 'PG24010'],
            ['Manish Rao', 'manish.r@demo.edu', 'PG24011'],
            ['Ananya Mishra', 'ananya.m@demo.edu', 'PG24012'],
        ];
        $students = [];
        foreach ($studentNames as $i => [$name, $email, $enroll]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => Hash::make('password123'), 'email_verified_at' => now()
            ]);
            $user->assignRole('student');
            $course = $i < 10 ? $pgdm : $pgdmFin;
            $student = Student::firstOrCreate(['enrollment_number' => $enroll], [
                'user_id' => $user->id,
                'department_id' => $course->department_id,
                'course_id' => $course->id,
                'roll_number' => 'R' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'current_semester' => 2,
                'status' => 'active',
                'admission_date' => '2024-06-15',
                'gender' => $i % 2 === 0 ? 'male' : 'female',
            ]);
            $students[] = $student;
        }

        // 10. Enrollments (all students in current semester subjects)
        $sem2SubjectCodes = ['FIN101', 'MKT101', 'MGT201', 'MGT202'];
        $sem2Subjects = Subject::whereIn('code', $sem2SubjectCodes)->get()->keyBy('code');
        foreach ($students as $student) {
            foreach ($sem2Subjects as $subject) {
                Enrollment::firstOrCreate([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'semester_id' => $currentSem->id,
                ], ['status' => 'active']);
            }
        }

        // 11. Timetable Entries (Mon-Fri schedule for PGDM)
        $nonBreakSlots = $slots->where('is_break', false)->values();
        if (TimetableEntry::where('course_id', $pgdm->id)->count() === 0 && $nonBreakSlots->count() >= 4) {
            $schedule = [
                // [day, slot_index, subject_index, teacher_index, room_index]
                [1, 0, 0, 0, 0], [1, 1, 1, 2, 0], [1, 2, 2, 1, 1], [1, 3, 3, 1, 1],
                [2, 0, 4, 2, 0], [2, 1, 5, 0, 0], [2, 2, 0, 0, 1], [2, 3, 1, 2, 1],
                [3, 0, 2, 1, 0], [3, 1, 3, 1, 0], [3, 2, 4, 2, 1], [3, 3, 6, 0, 2],
                [4, 0, 0, 0, 0], [4, 1, 5, 0, 0], [4, 2, 2, 1, 1], [4, 3, 4, 2, 1],
                [5, 0, 1, 2, 0], [5, 1, 3, 1, 0], [5, 2, 6, 0, 2], [5, 3, 5, 0, 1],
            ];
            foreach ($schedule as [$day, $slotIdx, $subIdx, $tchIdx, $roomIdx]) {
                if (!isset($nonBreakSlots[$slotIdx]) || !isset($subjects[$subIdx]) || !isset($teachers[$tchIdx])) continue;
                TimetableEntry::firstOrCreate([
                    'day_of_week' => $day,
                    'timetable_slot_id' => $nonBreakSlots[$slotIdx]->id,
                    'course_id' => $pgdm->id,
                    'subject_id' => $subjects[$subIdx]->id,
                ], [
                    'teacher_id' => $teachers[$tchIdx]->id,
                    'classroom_id' => $rooms[$roomIdx]->id,
                    'semester_id' => $currentSem->id,
                    'is_active' => true,
                ]);
            }
        }

        // 12. Fee Structures
        $feeTypes = [
            ['fee_type' => 'tuition_fee', 'amount' => 150000, 'description' => 'Annual tuition fee', 'semester_number' => null],
            ['fee_type' => 'library_fee', 'amount' => 5000, 'description' => 'Library and study material', 'semester_number' => null],
            ['fee_type' => 'exam_fee', 'amount' => 3000, 'description' => 'Examination fee', 'semester_number' => 2],
            ['fee_type' => 'activity_fee', 'amount' => 8000, 'description' => 'Sports and cultural activities', 'semester_number' => null],
        ];
        $feeStructures = [];
        foreach ($feeTypes as $ft) {
            $feeStructures[] = FeeStructure::firstOrCreate([
                'course_id' => $pgdm->id,
                'fee_type' => $ft['fee_type'],
                'semester_number' => $ft['semester_number'],
            ], array_merge($ft, ['course_id' => $pgdm->id, 'academic_year_id' => $ay2->id]));
        }

        // 13. Fee Payments (8 of 12 students have paid tuition)
        $receiptCount = FeePayment::count();
        foreach (array_slice($students, 0, 8) as $i => $student) {
            FeePayment::firstOrCreate(['student_id' => $student->id, 'fee_structure_id' => $feeStructures[0]->id], [
                'amount_paid' => 150000,
                'payment_date' => Carbon::parse('2025-01-15')->addDays($i * 2),
                'payment_method' => ['cash', 'online', 'cheque', 'dd'][$i % 4],
                'status' => 'paid',
                'receipt_number' => 'DRCP' . str_pad($receiptCount + $i + 1, 5, '0', STR_PAD_LEFT),
            ]);
        }

        // 14. Exams and Results for semester 1 subjects
        $examSubjects = array_slice($subjects, 0, 3); // First 3 are sem 1 subjects
        foreach ($examSubjects as $subject) {
            $exam = \App\Models\Exam::firstOrCreate([
                'name' => 'End Semester - ' . $subject->name,
                'subject_id' => $subject->id,
            ], [
                'semester_id' => $sem1->id,
                'type' => 'external',
                'total_marks' => 100,
                'passing_marks' => 40,
                'exam_date' => '2024-11-20',
                'course_id' => $pgdm->id,
            ]);

            // Results for first 10 PGDM students
            $marks = [85, 78, 92, 65, 71, 88, 55, 76, 90, 62];
            foreach (array_slice($students, 0, 10) as $i => $student) {
                \App\Models\ExamResult::firstOrCreate([
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                ], [
                    'marks_obtained' => $marks[$i],
                    'is_absent' => false,
                    'grade' => $marks[$i] >= 80 ? 'A' : ($marks[$i] >= 60 ? 'B' : 'C'),
                ]);
            }
        }

        // 15. Notices (4 notices)
        foreach ([
            ['title' => 'Welcome to Semester II 2024-25', 'content' => 'Dear students, Semester II has commenced from January 10, 2025. Please collect your timetables from the academic office.', 'audience' => 'all', 'priority' => 'important'],
            ['title' => 'Fee Payment Reminder', 'content' => 'This is a reminder that the last date for fee payment is January 31, 2025. Students who have not paid are requested to do so immediately.', 'audience' => 'students', 'priority' => 'urgent'],
            ['title' => 'Campus Placement Drive — InfoSys', 'content' => 'InfoSys will be conducting a campus placement drive on February 15, 2025. Eligible students (CGPA >= 6.0) should register by February 10.', 'audience' => 'students', 'priority' => 'important'],
            ['title' => 'Faculty Development Program', 'content' => 'A Faculty Development Program on "Modern Pedagogy in Management Education" will be held on January 25-26, 2025. All faculty members are requested to attend.', 'audience' => 'teachers', 'priority' => 'normal'],
        ] as $n) {
            Notice::firstOrCreate(['title' => $n['title']], array_merge($n, [
                'user_id' => $admin->id,
                'is_published' => true,
                'publish_date' => now()->subDays(rand(1, 10))->format('Y-m-d'),
                'published_at' => now()->subDays(rand(1, 10)),
            ]));
        }

        // 16. Admission enquiries
        $admissionData = [
            ['applicant_name' => 'Karan Malhotra', 'email' => 'karan.m@gmail.com', 'phone' => '9876543210', 'status' => 'applied'],
            ['applicant_name' => 'Preethi Sundaram', 'email' => 'preethi.s@gmail.com', 'phone' => '9876543211', 'status' => 'shortlisted'],
            ['applicant_name' => 'Rohan Bajaj', 'email' => 'rohan.b@gmail.com', 'phone' => '9876543212', 'status' => 'enquiry'],
            ['applicant_name' => 'Nisha Agarwal', 'email' => 'nisha.a@gmail.com', 'phone' => '9876543213', 'status' => 'enquiry'],
            ['applicant_name' => 'Dhruv Saxena', 'email' => 'dhruv.s@gmail.com', 'phone' => '9876543214', 'status' => 'applied'],
        ];
        foreach ($admissionData as $a) {
            \App\Models\Admission::firstOrCreate(['email' => $a['email']], array_merge($a, [
                'course_id' => $pgdm->id,
                'application_date' => Carbon::now()->subDays(rand(5, 30)),
                'last_qualification' => 'B.Com',
                'last_percentage' => rand(60, 85),
            ]));
        }

        // 17. Companies and placement drives
        $company1 = \App\Models\Company::firstOrCreate(['name' => 'InfoSys Ltd'], [
            'industry' => 'IT / Consulting',
            'contact_person' => 'Ms. Ritu Sharma',
            'contact_email' => 'campus@infosys.com',
            'is_active' => true,
        ]);
        $company2 = \App\Models\Company::firstOrCreate(['name' => 'HDFC Bank'], [
            'industry' => 'Banking & Finance',
            'contact_person' => 'Mr. Vijay Menon',
            'contact_email' => 'campus@hdfc.com',
            'is_active' => true,
        ]);
        $drive1 = \App\Models\PlacementDrive::firstOrCreate(['title' => 'InfoSys Campus Drive 2025'], [
            'company_id' => $company1->id,
            'job_role' => 'Management Trainee',
            'package' => '6.5 LPA',
            'min_cgpa' => 6.0,
            'drive_date' => '2025-02-15',
            'last_apply_date' => '2025-02-10',
            'location' => 'Bangalore',
            'status' => 'upcoming',
            'vacancies' => 10,
        ]);
        \App\Models\PlacementDrive::firstOrCreate(['title' => 'HDFC Bank PGDM Drive'], [
            'company_id' => $company2->id,
            'job_role' => 'Relationship Manager',
            'package' => '7 LPA',
            'min_cgpa' => 6.5,
            'drive_date' => '2025-03-01',
            'last_apply_date' => '2025-02-25',
            'location' => 'Mumbai',
            'status' => 'upcoming',
            'vacancies' => 5,
        ]);

        // 3 students applied to drive1
        foreach (array_slice($students, 0, 3) as $i => $s) {
            \App\Models\Placement::firstOrCreate(['drive_id' => $drive1->id, 'student_id' => $s->id], [
                'application_status' => ['applied', 'shortlisted', 'applied'][$i],
            ]);
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('  Admin: admin@demo.edu / password123');
        $this->command->info('  Teacher: anjali@demo.edu / password123');
        $this->command->info('  Student: arjun.k@demo.edu / password123');
    }
}
