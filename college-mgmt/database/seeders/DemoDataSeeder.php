<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Student, Teacher, Department, Course, Program, Batch, Term, Subject, AcademicYear, Semester, Classroom, TimetableSlot, TimetableEntry, Notice, FeeStructure, FeePayment, Enrollment, AdmissionFormConfig, RequiredDocument, SelectionProcessStep, ScoringParameter, AdmissionFeeInstallment, Applicant, CounsellingLog, SelectionSession, SessionApplicant, ApplicantScore, MeritListEntry};
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        // Ensure roles exist
        foreach (['admin', 'teacher', 'student', 'parent', 'applicant', 'admission_officer', 'admission_head'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Admission team users
        $officer = User::firstOrCreate(['email' => 'officer@college.com'], [
            'name' => 'Sunita Sharma',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $officer->assignRole('admission_officer');

        $admHead = User::firstOrCreate(['email' => 'head@college.com'], [
            'name' => 'Rajesh Kumar',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admHead->assignRole('admission_head');

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

        // 3. Courses (2) — legacy, kept for backward compat
        $pgdmCourse = Course::firstOrCreate(['code' => 'PGDM'], [
            'name' => 'Post Graduate Diploma in Management',
            'department_id' => $depts[0]->id,
            'duration_years' => 2,
            'total_semesters' => 4,
            'description' => 'AICTE approved PGDM program',
            'is_active' => true,
        ]);
        $pgdmFinCourse = Course::firstOrCreate(['code' => 'PGDM-FIN'], [
            'name' => 'PGDM Finance',
            'department_id' => $depts[1]->id,
            'duration_years' => 2,
            'total_semesters' => 4,
            'description' => 'Finance specialization',
            'is_active' => true,
        ]);

        // 3b. Programs (new academic structure)
        $pgdm = Program::firstOrCreate(['code' => 'PGDM'], [
            'name' => 'Post Graduate Diploma in Management',
            'department_id' => $depts[0]->id,
            'abbreviation' => 'PGDM',
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
            'description' => 'AICTE approved PGDM program',
            'default_intake_capacity' => 60,
            'is_active' => true,
        ]);
        $pgdmFin = Program::firstOrCreate(['code' => 'PGDM-FIN'], [
            'name' => 'PGDM Finance',
            'department_id' => $depts[1]->id,
            'abbreviation' => 'PGDM-F',
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
            'description' => 'Finance specialization',
            'default_intake_capacity' => 60,
            'is_active' => true,
        ]);

        // 4. Semesters (legacy)
        $sems = [];
        foreach ([
            ['name' => 'Semester I (2024-25)', 'number' => 1, 'academic_year_id' => $ay2->id, 'start_date' => '2024-07-01', 'end_date' => '2024-11-30', 'is_current' => false],
            ['name' => 'Semester II (2024-25)', 'number' => 2, 'academic_year_id' => $ay2->id, 'start_date' => '2025-01-01', 'end_date' => '2025-05-31', 'is_current' => true],
        ] as $s) {
            $sems[] = Semester::firstOrCreate(['name' => $s['name']], $s);
        }
        $currentSem = $sems[1];
        $sem1 = $sems[0];

        // 4b. Batches + Terms (new structure)
        $pgdmBatch = Batch::firstOrCreate(['code' => 'PGDM-24'], [
            'program_id' => $pgdm->id,
            'academic_year_id' => $ay2->id,
            'name' => 'PGDM Batch 2024-26',
            'start_date' => '2024-07-01',
            'end_date' => '2026-06-30',
            'intake_capacity' => 60,
            'status' => 'active',
        ]);
        $termLabels = ['Semester I', 'Semester II', 'Semester III', 'Semester IV'];
        $termDates  = [
            ['2024-07-01','2024-11-30',false],
            ['2025-01-01','2025-05-31',true],
            ['2025-07-01','2025-11-30',false],
            ['2026-01-01','2026-05-31',false],
        ];
        $terms = [];
        foreach ($termLabels as $i => $label) {
            $terms[] = Term::firstOrCreate(['batch_id' => $pgdmBatch->id, 'term_number' => $i + 1], [
                'program_id'  => $pgdm->id,
                'name'        => $label,
                'start_date'  => $termDates[$i][0],
                'end_date'    => $termDates[$i][1],
                'is_current'  => $termDates[$i][2],
                'sort_order'  => $i + 1,
            ]);
        }
        $currentTerm = $terms[1]; // Semester II

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
            $prog = $i < 10 ? $pgdm : $pgdmFin;
            $student = Student::firstOrCreate(['enrollment_number' => $enroll], [
                'user_id' => $user->id,
                'department_id' => $prog->department_id,
                'course_id' => $i < 10 ? $pgdmCourse->id : $pgdmFinCourse->id,
                'program_id' => $prog->id,
                'batch_id' => $pgdmBatch->id,
                'roll_number' => 'R' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'current_semester' => 2,
                'current_term' => 2,
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
                ], ['status' => 'active', 'term_id' => $currentTerm->id]);
            }
        }

        // 11. Timetable Entries (Mon-Fri schedule for PGDM)
        $nonBreakSlots = $slots->where('is_break', false)->values();
        if (TimetableEntry::where('program_id', $pgdm->id)->count() === 0 && $nonBreakSlots->count() >= 4) {
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
                    'course_id' => $pgdmCourse->id,
                    'subject_id' => $subjects[$subIdx]->id,
                ], [
                    'teacher_id' => $teachers[$tchIdx]->id,
                    'classroom_id' => $rooms[$roomIdx]->id,
                    'semester_id' => $currentSem->id,
                    'program_id' => $pgdm->id,
                    'term_id' => $currentTerm->id,
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
                'course_id' => $pgdmCourse->id,
                'fee_type' => $ft['fee_type'],
                'semester_number' => $ft['semester_number'],
            ], array_merge($ft, ['course_id' => $pgdmCourse->id, 'program_id' => $pgdm->id, 'academic_year_id' => $ay2->id]));
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
                'program_id' => $pgdm->id,
                'term_id' => $terms[0]->id,
                'type' => 'final',
                'total_marks' => 100,
                'passing_marks' => 40,
                'exam_date' => '2024-11-20',
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

        // 15b. Admission configuration for PGDM
        // Default documents
        foreach (RequiredDocument::defaults() as $doc) {
            RequiredDocument::firstOrCreate(['program_id' => $pgdm->id, 'name' => $doc['name']], array_merge($doc, ['program_id' => $pgdm->id]));
        }

        // Selection process: WAT → GD → PI
        $wat = SelectionProcessStep::firstOrCreate(['program_id' => $pgdm->id, 'step_order' => 1], [
            'name' => 'Written Ability Test', 'type' => 'wat', 'max_score' => 30,
            'weightage' => 20, 'instructions' => 'Candidates write a 300-word essay on a given topic in 20 minutes.',
        ]);
        $gd = SelectionProcessStep::firstOrCreate(['program_id' => $pgdm->id, 'step_order' => 2], [
            'name' => 'Group Discussion', 'type' => 'gd', 'max_score' => 40,
            'weightage' => 30, 'instructions' => 'Groups of 8-10 candidates. 15-minute topic discussion.',
        ]);
        $pi = SelectionProcessStep::firstOrCreate(['program_id' => $pgdm->id, 'step_order' => 3], [
            'name' => 'Personal Interview', 'type' => 'pi', 'max_score' => 100,
            'weightage' => 50, 'instructions' => 'Individual 20-minute interview with 2 evaluators.',
        ]);

        // GD scoring parameters
        foreach ([
            ['name' => 'Communication Skills', 'max_score' => 10],
            ['name' => 'Leadership Qualities', 'max_score' => 10],
            ['name' => 'Subject Knowledge', 'max_score' => 10],
            ['name' => 'Body Language & Confidence', 'max_score' => 10],
        ] as $i => $p) {
            ScoringParameter::firstOrCreate(['selection_process_step_id' => $gd->id, 'name' => $p['name']], array_merge($p, ['selection_process_step_id' => $gd->id, 'sort_order' => $i + 1]));
        }

        // PI scoring parameters
        foreach ([
            ['name' => 'Subject Knowledge', 'max_score' => 20],
            ['name' => 'Problem Solving', 'max_score' => 20],
            ['name' => 'Communication', 'max_score' => 20],
            ['name' => 'Career Clarity', 'max_score' => 20],
            ['name' => 'Overall Impression', 'max_score' => 20],
        ] as $i => $p) {
            ScoringParameter::firstOrCreate(['selection_process_step_id' => $pi->id, 'name' => $p['name']], array_merge($p, ['selection_process_step_id' => $pi->id, 'sort_order' => $i + 1]));
        }

        // Admission fee installments
        AdmissionFeeInstallment::firstOrCreate(['program_id' => $pgdm->id, 'installment_number' => 1], [
            'name' => 'Registration Fee', 'amount' => 10000, 'due_date' => '2024-03-31',
            'description' => 'Non-refundable registration fee to confirm your seat',
        ]);
        AdmissionFeeInstallment::firstOrCreate(['program_id' => $pgdm->id, 'installment_number' => 2], [
            'name' => 'First Semester Fee', 'amount' => 140000, 'due_date' => '2024-06-15',
            'description' => 'First semester tuition and other fees',
        ]);

        // Form config
        AdmissionFormConfig::firstOrCreate(['program_id' => $pgdm->id], [
            'form_sections' => AdmissionFormConfig::getDefaultSections(),
            'is_active' => true,
        ]);

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
                'course_id' => $pgdmCourse->id,
                'program_id' => $pgdm->id,
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

        // ── Sample Applicants ──────────────────────────────────────────────────
        Role::firstOrCreate(['name' => 'applicant']);

        $applicantData = [
            [
                'name'   => 'Priya Sharma',
                'email'  => 'priya.sharma@applicant.demo',
                'status' => 'submitted',
                'applied_at' => Carbon::now()->subDays(10),
                'personal_data' => ['father_name' => 'Ramesh Sharma', 'mother_name' => 'Sunita Sharma', 'date_of_birth' => '1999-03-15', 'gender' => 'Female', 'category' => 'General', 'phone' => '9876543210', 'address' => '12 MG Road, Mumbai', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'pincode' => '400001'],
                'academic_data' => ['graduation_college' => 'Mumbai University', 'graduation_degree' => 'B.Com', 'graduation_percentage' => '78.5', 'graduation_year' => '2022', 'work_experience' => '12', 'entrance_exam' => 'CAT', 'entrance_score' => '85.2'],
                'family_data'   => ['father_occupation' => 'Business', 'father_income' => '8 LPA', 'mother_occupation' => 'Homemaker'],
                'additional_data' => ['how_did_you_hear' => 'Google', 'why_this_program' => 'I want to build leadership skills and accelerate my career in business management.'],
            ],
            [
                'name'   => 'Rahul Verma',
                'email'  => 'rahul.verma@applicant.demo',
                'status' => 'submitted',
                'applied_at' => Carbon::now()->subDays(7),
                'personal_data' => ['father_name' => 'Suresh Verma', 'mother_name' => 'Kavita Verma', 'date_of_birth' => '1998-07-22', 'gender' => 'Male', 'category' => 'OBC', 'phone' => '9812345678', 'address' => '45 Civil Lines, Delhi', 'city' => 'Delhi', 'state' => 'Delhi', 'pincode' => '110001'],
                'academic_data' => ['graduation_college' => 'Delhi University', 'graduation_degree' => 'B.Tech', 'graduation_percentage' => '72.0', 'graduation_year' => '2021', 'work_experience' => '24', 'entrance_exam' => 'XAT', 'entrance_score' => '78.4'],
                'family_data'   => ['father_occupation' => 'Government Employee', 'father_income' => '6 LPA', 'mother_occupation' => 'Teacher'],
                'additional_data' => ['how_did_you_hear' => 'Friend/Family', 'why_this_program' => 'Looking to transition from engineering to management and this program has excellent placement record.'],
            ],
            [
                'name'   => 'Sneha Patel',
                'email'  => 'sneha.patel@applicant.demo',
                'status' => 'shortlisted',
                'applied_at' => Carbon::now()->subDays(20),
                'reviewed_at' => Carbon::now()->subDays(5),
                'personal_data' => ['father_name' => 'Kiran Patel', 'mother_name' => 'Meena Patel', 'date_of_birth' => '1999-11-08', 'gender' => 'Female', 'category' => 'General', 'phone' => '9898765432', 'address' => '78 Satellite Road, Ahmedabad', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'pincode' => '380001'],
                'academic_data' => ['graduation_college' => 'Gujarat University', 'graduation_degree' => 'BBA', 'graduation_percentage' => '82.3', 'graduation_year' => '2022', 'work_experience' => '18', 'entrance_exam' => 'CAT', 'entrance_score' => '92.1'],
                'family_data'   => ['father_occupation' => 'Doctor', 'father_income' => '15 LPA', 'mother_occupation' => 'Professor'],
                'additional_data' => ['how_did_you_hear' => 'Social Media', 'extracurricular' => 'National level debate champion, NSS volunteer', 'why_this_program' => 'Strong brand value and industry partnerships align perfectly with my career goals in marketing.'],
            ],
            [
                'name'   => 'Arjun Singh',
                'email'  => 'arjun.singh2@applicant.demo',
                'status' => 'draft',
                'personal_data' => ['father_name' => 'Gurpreet Singh', 'date_of_birth' => '2000-01-14', 'gender' => 'Male', 'phone' => '9765432109'],
                'academic_data' => null,
                'family_data'   => null,
                'additional_data' => null,
            ],
            [
                'name'   => 'Meenakshi Rao',
                'email'  => 'meenakshi.rao@applicant.demo',
                'status' => 'selected',
                'applied_at' => Carbon::now()->subDays(30),
                'reviewed_at' => Carbon::now()->subDays(3),
                'personal_data' => ['father_name' => 'Venkat Rao', 'mother_name' => 'Lakshmi Rao', 'date_of_birth' => '1998-05-25', 'gender' => 'Female', 'category' => 'General', 'phone' => '9654321098', 'address' => '23 Anna Salai, Chennai', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'pincode' => '600002'],
                'academic_data' => ['graduation_college' => 'Madras University', 'graduation_degree' => 'B.Sc Statistics', 'graduation_percentage' => '88.0', 'graduation_year' => '2021', 'work_experience' => '30', 'entrance_exam' => 'CAT', 'entrance_score' => '96.5'],
                'family_data'   => ['father_occupation' => 'Engineer', 'father_income' => '12 LPA', 'mother_occupation' => 'Accountant'],
                'additional_data' => ['how_did_you_hear' => 'Education Fair', 'achievements' => 'University Gold Medalist, Published research paper', 'why_this_program' => 'This institute has produced industry leaders and I aspire to follow that path in analytics.'],
            ],
        ];

        foreach ($applicantData as $data) {
            $user = User::firstOrCreate(['email' => $data['email']], [
                'name'     => $data['name'],
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('applicant');

            Applicant::firstOrCreate(['user_id' => $user->id], [
                'program_id'      => $pgdm->id,
                'status'          => $data['status'],
                'personal_data'   => $data['personal_data'],
                'academic_data'   => $data['academic_data'] ?? null,
                'family_data'     => $data['family_data'] ?? null,
                'additional_data' => $data['additional_data'] ?? null,
                'applied_at'      => $data['applied_at'] ?? null,
                'reviewed_at'     => $data['reviewed_at'] ?? null,
                'reviewed_by'     => isset($data['reviewed_at']) ? $admin->id : null,
            ]);
        }

        // ── Counselling Logs for sample applicants ─────────────────────────────
        $officerUser = User::where('email', 'officer@college.com')->first();
        if ($officerUser) {
            $applicants = Applicant::all();
            $logData = [
                [
                    'interaction_type' => 'call',
                    'outcome' => 'interested',
                    'notes' => 'Spoke with the applicant. Very interested in the PGDM program. Asked about scholarship options.',
                    'next_followup_date' => Carbon::now()->addDays(3)->toDateString(),
                    'duration_minutes' => 15,
                    'created_at' => Carbon::now()->subDays(8),
                ],
                [
                    'interaction_type' => 'email',
                    'outcome' => 'callback',
                    'notes' => 'Sent program brochure and fee structure. Applicant requested a callback to discuss further.',
                    'next_followup_date' => Carbon::now()->addDays(1)->toDateString(),
                    'duration_minutes' => null,
                    'created_at' => Carbon::now()->subDays(5),
                ],
                [
                    'interaction_type' => 'whatsapp',
                    'outcome' => 'follow_up',
                    'notes' => 'Shared testimonials and placement report. Applicant said they will confirm after discussing with parents.',
                    'next_followup_date' => Carbon::now()->addDays(5)->toDateString(),
                    'duration_minutes' => null,
                    'created_at' => Carbon::now()->subDays(2),
                ],
                [
                    'interaction_type' => 'walk_in',
                    'outcome' => 'interested',
                    'notes' => 'Applicant visited campus. Gave campus tour. Very positive about facilities.',
                    'next_followup_date' => null,
                    'duration_minutes' => 45,
                    'created_at' => Carbon::now()->subDays(6),
                ],
            ];

            foreach ($applicants as $i => $applicant) {
                $logsForApplicant = array_slice($logData, 0, ($i % 3) + 2);
                foreach ($logsForApplicant as $log) {
                    CounsellingLog::firstOrCreate(
                        [
                            'applicant_id' => $applicant->id,
                            'logged_by' => $officerUser->id,
                            'notes' => $log['notes'],
                        ],
                        [
                            'interaction_type' => $log['interaction_type'],
                            'outcome' => $log['outcome'],
                            'next_followup_date' => $log['next_followup_date'],
                            'duration_minutes' => $log['duration_minutes'],
                            'created_at' => $log['created_at'],
                            'updated_at' => $log['created_at'],
                        ]
                    );
                }
            }
        }

        // ── P4: Selection Sessions ─────────────────────────────────────────────
        $shortlisted = Applicant::where('program_id', $pgdm->id)->where('status', 'shortlisted')->get();
        $selected    = Applicant::where('program_id', $pgdm->id)->where('status', 'selected')->get();

        // WAT Session — scheduled, with 3 shortlisted applicants
        $watSession = SelectionSession::firstOrCreate(
            ['session_name' => 'WAT Round 1 — Morning Batch', 'selection_process_step_id' => $wat->id],
            [
                'program_id'  => $pgdm->id,
                'session_name' => 'WAT Round 1 — Morning Batch',
                'scheduled_date' => Carbon::now()->addDays(7)->toDateString(),
                'start_time'  => '09:00:00',
                'end_time'    => '10:30:00',
                'venue'       => 'Seminar Hall A',
                'max_candidates' => 20,
                'instructions' => 'Candidates must bring their own pen. Essay topic will be revealed 5 minutes before start.',
                'status'      => 'scheduled',
                'created_by'  => $admHead->id,
            ]
        );

        foreach ($shortlisted->take(3) as $applicant) {
            SessionApplicant::firstOrCreate([
                'selection_session_id' => $watSession->id,
                'applicant_id'         => $applicant->id,
            ], ['assigned_at' => now(), 'attendance_status' => 'pending']);
        }

        // GD Session — completed, 2 applicants with attendance recorded
        $gdSession = SelectionSession::firstOrCreate(
            ['session_name' => 'GD Round 1 — Batch A', 'selection_process_step_id' => $gd->id],
            [
                'program_id'  => $pgdm->id,
                'session_name' => 'GD Round 1 — Batch A',
                'scheduled_date' => Carbon::now()->subDays(5)->toDateString(),
                'start_time'  => '11:00:00',
                'end_time'    => '13:00:00',
                'venue'       => 'Conference Room 2',
                'max_candidates' => 10,
                'instructions' => 'Groups of 8-10 candidates. 15-minute topic discussion with 2 evaluators.',
                'status'      => 'completed',
                'conducted_by' => $officer->id,
                'created_by'  => $admHead->id,
            ]
        );

        $gdApplicants = $shortlisted->merge($selected)->take(2);
        foreach ($gdApplicants as $i => $applicant) {
            SessionApplicant::firstOrCreate([
                'selection_session_id' => $gdSession->id,
                'applicant_id'         => $applicant->id,
            ], [
                'assigned_at'       => Carbon::now()->subDays(6),
                'attendance_status' => $i === 0 ? 'present' : 'present',
                'panel_number'      => 1,
            ]);
        }

        // ── P5: Scoring & Decision ─────────────────────────────────────────────
        $gdApplicantsScored = $shortlisted->merge($selected)->take(2);
        $gdStep = $gd; // SelectionProcessStep (gd)
        $gdParams = $gdStep->scoringParameters()->orderBy('sort_order')->get();

        foreach ($gdApplicantsScored as $idx => $applicant) {
            $paramScores = [];
            $totalScore = 0;
            $maxPossible = 0;

            foreach ($gdParams as $pi => $param) {
                // Realistic score: first applicant scores higher
                $baseScore = $idx === 0 ? $param->max_score * 0.88 : $param->max_score * 0.72;
                $score = round($baseScore + ($pi % 2 === 0 ? 1 : -0.5), 1);
                $score = min($score, $param->max_score);
                $score = max($score, 0);
                $paramScores[$param->id] = $score;
                $totalScore += $score;
                $maxPossible += $param->max_score;
            }

            $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 2) : 0;

            ApplicantScore::firstOrCreate(
                [
                    'applicant_id'         => $applicant->id,
                    'selection_session_id' => $gdSession->id,
                ],
                [
                    'selection_process_step_id' => $gdSession->selection_process_step_id,
                    'scored_by'                 => $admHead->id,
                    'parameter_scores'          => $paramScores,
                    'total_score'               => $totalScore,
                    'max_possible_score'        => $maxPossible,
                    'percentage'                => $percentage,
                    'remarks'                   => $idx === 0 ? 'Very articulate. Strong communication skills.' : 'Good potential. Needs to work on confidence.',
                    'is_final'                  => true,
                ]
            );
        }

        // Generate MeritListEntries for scored applicants
        $allApplicants = Applicant::where('program_id', $pgdm->id)
            ->whereIn('status', ['shortlisted', 'under_review', 'selected'])
            ->with(['scores.step'])
            ->get();

        $ranked = [];
        foreach ($allApplicants as $applicant) {
            $stepScores = [];
            $selectionWeightedTotal = 0;

            foreach ($applicant->scores as $score) {
                $step = $score->step;
                if (!$step) continue;
                $stepScores[$step->id] = [
                    'total'      => $score->total_score,
                    'percentage' => $score->percentage,
                    'weightage'  => $step->weightage,
                ];
                $selectionWeightedTotal += $score->percentage * ($step->weightage / 100);
            }

            $academicData = $applicant->academic_data ?? [];
            $academicScore = null;
            if (!empty($academicData['graduation_percentage'])) {
                $academicScore = min((float) $academicData['graduation_percentage'], 100);
            } elseif (!empty($academicData['cgpa'])) {
                $academicScore = min((float) $academicData['cgpa'] * 10, 100);
            }

            $compositeScore = ($selectionWeightedTotal * 0.8) + (($academicScore ?? 0) * 0.2);

            $ranked[] = [
                'applicant'            => $applicant,
                'total_weighted_score' => round($selectionWeightedTotal, 2),
                'step_scores'          => $stepScores,
                'academic_score'       => $academicScore,
                'composite_score'      => round($compositeScore, 2),
            ];
        }

        usort($ranked, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        foreach ($ranked as $rank => $data) {
            MeritListEntry::firstOrCreate(
                [
                    'program_id'   => $pgdm->id,
                    'applicant_id' => $data['applicant']->id,
                ],
                [
                    'batch_id'             => $data['applicant']->batch_id,
                    'rank'                 => $rank + 1,
                    'total_weighted_score' => $data['total_weighted_score'],
                    'step_scores'          => $data['step_scores'],
                    'academic_score'       => $data['academic_score'],
                    'composite_score'      => $data['composite_score'],
                    'merit_list_version'   => 1,
                    'decision'             => $rank === 0 ? 'selected' : 'pending',
                    'decided_by'           => $rank === 0 ? $admHead->id : null,
                    'decided_at'           => $rank === 0 ? now() : null,
                ]
            );
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('  Admin: admin@demo.edu / password123');
        $this->command->info('  Teacher: anjali@demo.edu / password123');
        $this->command->info('  Student: arjun.k@demo.edu / password123');
        $this->command->info('  Applicants: priya.sharma@applicant.demo / password123 (and 4 more)');
        $this->command->info('  Admission Officer: officer@college.com / password');
        $this->command->info('  Admission Head: head@college.com / password');
    }
}
