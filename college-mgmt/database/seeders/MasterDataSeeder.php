<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\StudentSubjectEnrollment;
use App\Models\Attendance;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeeDemand;
use App\Models\FeeStructure;
use App\Models\FeePaymentRequest;
use App\Models\LeaveApplication;
use App\Models\StudyMaterial;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SubjectAnnouncement;
use App\Models\CourseFeedback;
use App\Models\MentorMeeting;
use App\Models\MentorMessage;
use App\Models\StudentGrievance;
use App\Models\AttendanceCondonation;
use App\Models\MarksAppeal;
use App\Models\CareerEvent;
use App\Models\CareerEventRegistration;
use App\Models\Internship;
use App\Models\SubjectDiscussion;
use App\Models\SubjectDiscussionReply;
use App\Models\AlumniProfile;
use App\Models\ApprovalWorkflow;
use App\Models\TeacherAvailability;
use App\Models\ExamRegistration;
use App\Models\StudentResume;
use App\Models\Applicant;
use App\Models\AssessmentComponent;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting MasterDataSeeder...');

        // SECTION 1 — Core Infrastructure
        $this->call(DemoDataSeeder::class);
        $this->command->info('✓ Section 1 (DemoDataSeeder) done');

        // Load core entities created by DemoDataSeeder
        $students  = Student::with('user')->where('status', 'active')->orderBy('id')->get();
        $teachers  = Teacher::with('user')->orderBy('id')->get();
        $pgdm      = Program::where('code', 'PGDM')->first();
        $currentTerm = Term::where('is_current', true)->first();
        $allTerms  = Term::orderBy('term_number')->get();
        $subjects  = Subject::orderBy('id')->get();
        $slots     = TimetableSlot::where('is_break', false)->orderBy('sort_order')->get();
        $adminUser = User::where('email', 'admin@demo.edu')->first();

        if (!$currentTerm || $students->isEmpty() || $teachers->isEmpty()) {
            $this->command->error('DemoDataSeeder did not create required data. Aborting.');
            return;
        }

        // SECTION 2 - Rich Attendance Data
        $this->call(MasterAttendanceDemoSeeder::class);

        // SECTION 3 - Exam Results (multiple exams per subject)
        $this->call(MasterExamResultDemoSeeder::class);

        // SECTION 4 — Fee Demands & Payments
        try {
            $this->command->info('Seeding Section 4: Fee Demands...');
            $feeStructure = FeeStructure::where('fee_type', 'tuition_fee')->first();
            $libFeeStruct = FeeStructure::where('fee_type', 'library_fee')->first();

            if ($feeStructure) {
                foreach ($students as $idx => $student) {
                    // fee_demands status: pending | partially_paid | fully_paid | overdue
                    if ($idx < 8) {
                        $status   = 'fully_paid';
                        $dueDate  = Carbon::today()->subDays(30);
                    } elseif ($idx < 11) {
                        $status   = 'pending';
                        $dueDate  = Carbon::today()->subDays(15);
                    } else {
                        $status   = 'overdue';
                        $dueDate  = Carbon::today()->subDays(45);
                    }

                    FeeDemand::firstOrCreate(
                        ['student_id' => $student->id, 'term_id' => $currentTerm->id],
                        [
                            'total_amount'          => 150000,
                            'scholarship_deduction' => 0,
                            'final_amount'          => 150000,
                            'due_date'              => $dueDate->toDateString(),
                            'penalty_amount'        => $status === 'overdue' ? 500 : 0,
                            'status'                => $status,
                        ]
                    );
                }
            }
            $this->command->info('✓ Section 4 (Fee Demands) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 4 failed: ' . $e->getMessage());
        }

        // SECTION 5 — Leave Applications
        // Note: leave_applications table is for teacher leaves (teacher_id FK).
        // Student leave uses a separate mechanism — seed teacher leave applications here.
        try {
            $this->command->info('Seeding Section 5: Leave Applications (teacher)...');
            $leaveTypes  = ['casual', 'medical', 'earned', 'duty'];
            $leaveReasons = [
                'Medical emergency — need to visit hospital for treatment',
                'Family function — sister\'s wedding ceremony',
                'Personal work — out of station for urgent family matter',
                'Medical appointment — scheduled specialist consultation',
                'Bereavement — loss of close family member',
            ];
            $statuses = ['pending', 'approved', 'rejected'];

            foreach ($teachers as $tIdx => $teacher) {
                for ($l = 0; $l < 3; $l++) {
                    $fromDate = Carbon::today()->subDays(rand(5, 40))->addDays($l * 5);
                    $toDate   = (clone $fromDate)->addDays(rand(1, 3));
                    $days     = $fromDate->diffInDays($toDate) + 1;
                    $status   = $statuses[($tIdx + $l) % 3];

                    LeaveApplication::firstOrCreate(
                        ['teacher_id' => $teacher->id, 'from_date' => $fromDate->toDateString()],
                        [
                            'leave_type'    => $leaveTypes[($tIdx + $l) % count($leaveTypes)],
                            'to_date'       => $toDate->toDateString(),
                            'days'          => $days,
                            'reason'        => $leaveReasons[($tIdx + $l) % count($leaveReasons)],
                            'status'        => $status,
                            'admin_remarks' => $status === 'rejected' ? 'Insufficient leave balance.' : ($status === 'approved' ? 'Approved.' : null),
                            'approved_by'   => $status !== 'pending' ? $adminUser?->id : null,
                            'approved_at'   => $status !== 'pending' ? now()->subDays(rand(1, 5)) : null,
                        ]
                    );
                }
            }
            $this->command->info('✓ Section 5 (Leave Applications) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 5 failed: ' . $e->getMessage());
        }

        // SECTION 6 — Study Materials
        try {
            $this->command->info('Seeding Section 6: Study Materials...');
            $materialTypes = ['notes', 'reference', 'video', 'other'];
            $materialTitles = [
                ['Lecture Notes - Week 1', 'Reference: Chapter 3', 'Video Tutorial: Introduction'],
                ['Lecture Notes - Week 2', 'Reference: Chapter 5', 'Link: Industry Case Study'],
                ['Lecture Notes - Week 3', 'Reference: Chapter 7', 'Lecture Notes - Week 4'],
                ['Reference: Research Paper', 'Video Tutorial: Advanced Concepts', 'Lecture Notes - Week 5'],
            ];

            foreach ($teachers as $tIdx => $teacher) {
                // Get subjects assigned to this teacher via timetable entries
                $teacherSubjectIds = TimetableEntry::where('teacher_id', $teacher->id)
                    ->distinct()
                    ->pluck('subject_id')
                    ->toArray();

                if (empty($teacherSubjectIds)) {
                    $teacherSubjectIds = $subjects->take(2)->pluck('id')->toArray();
                }

                foreach ($teacherSubjectIds as $subjectId) {
                    $titles = $materialTitles[$tIdx % count($materialTitles)];
                    foreach ($titles as $mIdx => $title) {
                        StudyMaterial::firstOrCreate(
                            ['title' => $title, 'subject_id' => $subjectId, 'uploaded_by' => $teacher->user_id],
                            [
                                'type'         => $materialTypes[$mIdx % count($materialTypes)],
                                'description'  => 'Comprehensive study material for ' . Subject::find($subjectId)?->name . '. Covers key concepts and examples.',
                                'is_published' => true,
                                'term_id'      => $currentTerm->id,
                                'external_url' => $mIdx >= 2 ? 'https://example.com/resource/' . $subjectId : null,
                            ]
                        );
                    }
                }
            }
            $this->command->info('✓ Section 6 (Study Materials) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 6 failed: ' . $e->getMessage());
        }

        // SECTION 7 — Assignments & Submissions
        try {
            $this->command->info('Seeding Section 7: Assignments & Submissions...');

            $assignmentDefs = [
                ['title' => 'Case Study Analysis', 'description' => 'Analyse the given business case and present findings.'],
                ['title' => 'Research Report',     'description' => 'Prepare a detailed research report on the assigned topic.'],
            ];

            foreach ($teachers as $tIdx => $teacher) {
                $teacherSubjectIds = TimetableEntry::where('teacher_id', $teacher->id)
                    ->distinct()
                    ->pluck('subject_id')
                    ->take(2)
                    ->toArray();

                if (empty($teacherSubjectIds)) continue;

                foreach ($teacherSubjectIds as $subjectId) {
                    foreach ($assignmentDefs as $aIdx => $aDef) {
                        $isPast  = ($aIdx === 0);
                        $dueAt   = $isPast
                            ? Carbon::today()->subDays(rand(5, 20))->setHour(23)->setMinute(59)
                            : Carbon::today()->addDays(rand(5, 15))->setHour(23)->setMinute(59);

                        $assignment = Assignment::firstOrCreate(
                            ['title' => $aDef['title'], 'subject_id' => $subjectId, 'created_by' => $teacher->user_id],
                            [
                                'description'  => $aDef['description'],
                                'instructions' => 'Submit via the portal before the due date. Late submissions will be penalised 10% per day.',
                                'max_marks'    => 50,
                                'due_at'       => $dueAt,
                                'term_id'      => $currentTerm->id,
                                'is_published' => true,
                                'allow_late_submission' => true,
                                'late_penalty_percent'  => 10,
                            ]
                        );

                        // Create submissions only for past assignments
                        if ($isPast) {
                            foreach ($students as $student) {
                                $submittedAt = (clone $dueAt)->subHours(rand(1, 48));
                                $marksObtained = rand(25, 48);
                                $grade = $marksObtained >= 40 ? 'A' : ($marksObtained >= 30 ? 'B' : 'C');

                                AssignmentSubmission::firstOrCreate(
                                    ['assignment_id' => $assignment->id, 'student_id' => $student->id],
                                    [
                                        'answer_text'    => 'This is my submission for ' . $aDef['title'] . '. I have analysed the topic thoroughly.',
                                        'submitted_at'   => $submittedAt,
                                        'is_late'        => false,
                                        'marks_obtained' => $marksObtained,
                                        'feedback'       => 'Good effort. ' . ($marksObtained >= 40 ? 'Excellent analysis.' : 'Could improve depth of analysis.'),
                                        'graded_by'      => $teacher->user_id,
                                        'graded_at'      => Carbon::today()->subDays(rand(1, 5)),
                                        'status'         => 'graded',
                                    ]
                                );
                            }
                        }
                    }
                }
            }
            $this->command->info('✓ Section 7 (Assignments & Submissions) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 7 failed: ' . $e->getMessage());
        }

        // SECTION 8 — Subject Announcements
        try {
            $this->command->info('Seeding Section 8: Subject Announcements...');

            $announcementDefs = [
                ['title' => 'Upcoming Quiz Date', 'body' => 'A quiz will be conducted next week covering chapters 1-5. Please prepare thoroughly. The quiz will be for 20 marks and will be taken in class.'],
                ['title' => 'Assignment Submission Reminder', 'body' => 'This is a reminder that the assignment is due at end of this week. Please ensure you submit via the portal. Late submissions will not be accepted without prior approval.'],
            ];

            foreach ($teachers as $teacher) {
                $teacherSubjectIds = TimetableEntry::where('teacher_id', $teacher->id)
                    ->distinct()
                    ->pluck('subject_id')
                    ->take(2)
                    ->toArray();

                foreach ($teacherSubjectIds as $subjectId) {
                    foreach ($announcementDefs as $idx => $aDef) {
                        SubjectAnnouncement::firstOrCreate(
                            ['title' => $aDef['title'], 'subject_id' => $subjectId, 'posted_by' => $teacher->user_id],
                            [
                                'body'    => $aDef['body'],
                                'term_id' => $currentTerm->id,
                                'is_pinned' => $idx === 0,
                            ]
                        );
                    }
                }
            }
            $this->command->info('✓ Section 8 (Announcements) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 8 failed: ' . $e->getMessage());
        }

        // SECTION 9 — Course Feedback
        try {
            $this->command->info('Seeding Section 9: Course Feedback...');

            $commentOptions = [
                'Excellent teaching style, concepts explained clearly.',
                'Could improve pace of delivery.',
                'Very engaging sessions, enjoyed the case discussions.',
                null,
                'More real-world examples would be helpful.',
                null,
                'Professor is very approachable and helpful.',
                'Assignments are very relevant to industry.',
                null,
                'Class pace is a bit fast, some topics need more time.',
            ];

            foreach ($students as $sIdx => $student) {
                $enrolledSubjects = Enrollment::where('student_id', $student->id)
                    ->where('term_id', $currentTerm->id)
                    ->pluck('subject_id');

                foreach ($enrolledSubjects as $subjectId) {
                    CourseFeedback::firstOrCreate(
                        ['student_id' => $student->id, 'subject_id' => $subjectId, 'term_id' => $currentTerm->id],
                        [
                            'teaching_rating' => rand(3, 5),
                            'content_rating'  => rand(3, 5),
                            'overall_rating'  => rand(3, 5),
                            'comments'        => $commentOptions[$sIdx % count($commentOptions)],
                            'is_anonymous'    => false,
                        ]
                    );
                }
            }
            $this->command->info('✓ Section 9 (Course Feedback) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 9 failed: ' . $e->getMessage());
        }

        // SECTION 10 — Mentor Meetings & Messages
        try {
            $this->command->info('Seeding Section 10: Mentor Meetings & Messages...');

            $meetingTopics = [
                ['topic' => 'Academic Progress Review', 'notes' => 'Student is performing well in most subjects. Needs to improve attendance in FIN101.'],
                ['topic' => 'Career Counselling',        'notes' => 'Discussed internship options. Student interested in Finance sector. Suggested working on Excel and analytics skills.'],
                ['topic' => 'Subject Difficulty',        'notes' => 'Student struggling with Business Statistics. Recommended additional practice problems and visiting the learning centre.'],
            ];

            $mentorMessages = [
                ['message' => 'Good morning Professor, I had a doubt regarding the DCF valuation method covered in class. Could you please help?', 'fromStudent' => true],
                ['message' => 'Hello! DCF involves discounting future cash flows at the cost of capital. Please review Chapter 7 and come to my office hours tomorrow at 3pm.', 'fromStudent' => false],
                ['message' => 'Thank you Professor, I will do that. Also, I wanted to discuss my internship options — I have two offers and need guidance.', 'fromStudent' => true],
            ];

            // Assign each teacher to groups of 3 students
            foreach ($teachers as $tIdx => $teacher) {
                $menteeGroup = $students->slice($tIdx * 3, 3);

                foreach ($menteeGroup as $student) {
                    foreach ($meetingTopics as $mIdx => $meeting) {
                        MentorMeeting::firstOrCreate(
                            [
                                'student_id'   => $student->id,
                                'mentor_id'    => $teacher->user_id,
                                'topic'        => $meeting['topic'],
                            ],
                            [
                                'meeting_date' => Carbon::today()->subDays(($mIdx + 1) * 10)->toDateString(),
                                'notes'        => $meeting['notes'],
                                'status'       => 'completed',
                            ]
                        );
                    }

                    foreach ($mentorMessages as $msgIdx => $msg) {
                        $senderId = $msg['fromStudent'] ? $student->user_id : $teacher->user_id;
                        MentorMessage::firstOrCreate(
                            [
                                'student_id' => $student->id,
                                'sender_id'  => $senderId,
                                'message'    => substr($msg['message'], 0, 50),
                            ],
                            [
                                'message' => $msg['message'],
                                'read_at' => Carbon::today()->subDays(rand(1, 5)),
                            ]
                        );
                    }
                }
            }
            $this->command->info('✓ Section 10 (Mentor Meetings & Messages) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 10 failed: ' . $e->getMessage());
        }

        // SECTION 11 — Student Grievances
        try {
            $this->command->info('Seeding Section 11: Student Grievances...');

            $grievances = [
                ['category' => 'academic',        'title' => 'Marks not updated in portal',          'description' => 'My IA1 marks for FIN101 have not been updated in the student portal even after 2 weeks of submission. Please look into this urgently.', 'status' => 'open',        'resolution' => null],
                ['category' => 'administrative',  'title' => 'Bonafide certificate delay',            'description' => 'I applied for a bonafide certificate 3 weeks ago but have not received it yet. I need it urgently for a bank loan application.', 'status' => 'in_progress',  'resolution' => null],
                ['category' => 'infrastructure',  'title' => 'Air conditioning not working in LH-101','description' => 'The air conditioning in Lecture Hall 101 has been non-functional for the past week making it very difficult to attend classes during afternoon sessions.', 'status' => 'resolved', 'resolution' => 'AC unit has been repaired and is now functional.'],
                ['category' => 'faculty',         'title' => 'Assignment deadline not communicated',  'description' => 'The assignment deadline for MKT101 was changed without proper notification via the portal. Several students missed the deadline as a result.', 'status' => 'in_progress', 'resolution' => null],
                ['category' => 'academic',        'title' => 'Library book not available',            'description' => 'The prescribed textbook for Business Statistics is not available in the library and we are unable to study for the upcoming exam.', 'status' => 'resolved', 'resolution' => 'Additional copies have been procured and are now available at the issue counter.'],
            ];

            foreach ($grievances as $idx => $g) {
                $student = $students->get($idx % $students->count());
                if (!$student) continue;

                StudentGrievance::firstOrCreate(
                    ['student_id' => $student->id, 'title' => $g['title']],
                    [
                        'program_id'       => $pgdm?->id,
                        'category'         => $g['category'],
                        'description'      => $g['description'],
                        'status'           => $g['status'],
                        'priority'         => $idx < 2 ? 'high' : 'medium',
                        'assigned_to'      => $g['status'] !== 'open' ? $adminUser?->id : null,
                        'resolution_notes' => $g['resolution'],
                        'resolved_by'      => $g['status'] === 'resolved' ? $adminUser?->id : null,
                        'resolved_at'      => $g['status'] === 'resolved' ? now()->subDays(rand(1, 5)) : null,
                    ]
                );
            }
            $this->command->info('✓ Section 11 (Student Grievances) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 11 failed: ' . $e->getMessage());
        }

        // SECTION 12 — Attendance Condonation Requests
        try {
            $this->command->info('Seeding Section 12: Attendance Condonation...');

            // Pick 3 students with potentially low attendance
            $condonationStudents = $students->take(3);
            $enrolledSubjectId   = Enrollment::where('term_id', $currentTerm->id)->value('subject_id');
            $subject             = $enrolledSubjectId ? Subject::find($enrolledSubjectId) : $subjects->first();

            $condonationData = [
                ['reason' => 'Medical emergency — hospitalised for 2 weeks due to typhoid fever. Medical certificate attached.', 'status' => 'pending',  'sessions' => null],
                ['reason' => 'Family bereavement — attended last rites of grandfather, had to travel to hometown for 10 days.',  'status' => 'approved', 'sessions' => 4],
                ['reason' => 'Participated in national-level debate competition representing the college.',                       'status' => 'approved', 'sessions' => 3],
            ];

            foreach ($condonationStudents as $idx => $student) {
                if (!isset($condonationData[$idx])) continue;
                $data = $condonationData[$idx];

                AttendanceCondonation::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subject?->id, 'term_id' => $currentTerm->id],
                    [
                        'reason'            => $data['reason'],
                        'status'            => $data['status'],
                        'sessions_condoned' => $data['sessions'] ?? 0,
                        'approved_by'       => $data['status'] === 'approved' ? $adminUser?->id : null,
                        'remarks'           => $data['status'] === 'approved' ? 'Approved based on documentary evidence provided.' : null,
                    ]
                );
            }
            $this->command->info('✓ Section 12 (Condonation) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 12 failed: ' . $e->getMessage());
        }

        // SECTION 13 — Marks Appeals
        try {
            $this->command->info('Seeding Section 13: Marks Appeals...');

            // Find end-sem exam results to appeal
            $endSemResults = ExamResult::whereHas('exam', fn($q) => $q->where('type', 'final'))
                ->with('exam')
                ->take(5)
                ->get();

            $appealDefs = [
                ['reason' => 'Totalling error in section B', 'description' => 'I believe there is a totalling error in Section B of my answer sheet. My marks in Q8 were not added correctly.', 'status' => 'pending'],
                ['reason' => 'Answer was correct for Q5',    'description' => 'I believe my answer for Question 5 was correct based on the textbook reference on page 214. Requesting re-evaluation.', 'status' => 'under_review'],
                ['reason' => 'Paper not fully evaluated',    'description' => 'Page 4 of my answer booklet appears to have been missed during evaluation. I have attempted 3 complete questions on that page.', 'status' => 'rejected'],
            ];

            foreach ($endSemResults->take(3) as $idx => $result) {
                $def = $appealDefs[$idx % count($appealDefs)];

                MarksAppeal::firstOrCreate(
                    ['student_id' => $result->student_id, 'exam_result_id' => $result->id],
                    [
                        'reason'         => $def['reason'],
                        'description'    => $def['description'],
                        'marks_claimed'  => $result->marks_obtained + rand(5, 15),
                        'status'         => $def['status'],
                        'reviewed_by'    => $def['status'] !== 'pending' ? $adminUser?->id : null,
                        'review_remarks' => $def['status'] === 'rejected' ? 'Appeal rejected after thorough review. Original marks stand.' : null,
                        'revised_marks'  => null,
                        'reviewed_at'    => $def['status'] !== 'pending' ? now()->subDays(rand(1, 7)) : null,
                    ]
                );
            }
            $this->command->info('✓ Section 13 (Marks Appeals) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 13 failed: ' . $e->getMessage());
        }

        // SECTION 14 — Career Events
        try {
            $this->command->info('Seeding Section 14: Career Events...');

            $cmcUser = User::where('email', 'cmc@college.com')->first() ?? $adminUser;

            $careerEvents = [
                [
                    'title' => 'Resume Writing Workshop',
                    'event_type' => 'workshop',
                    'event_date' => Carbon::today()->addDays(7)->toDateString(),
                    'venue' => 'Lecture Hall 201',
                    'description' => 'Learn to craft an impactful resume that stands out. Expert HR professionals will guide you through modern resume formats.',
                    'seats' => 50,
                    'registration_deadline' => Carbon::today()->addDays(5)->toDateString(),
                    'is_published' => true,
                ],
                [
                    'title' => 'LinkedIn for MBA Students',
                    'event_type' => 'seminar',
                    'event_date' => Carbon::today()->addDays(3)->toDateString(),
                    'venue' => 'Conference Hall A',
                    'description' => 'Build a professional LinkedIn presence. Strategies for networking, job hunting and personal branding on LinkedIn.',
                    'seats' => 100,
                    'registration_deadline' => Carbon::today()->addDays(2)->toDateString(),
                    'is_published' => true,
                ],
                [
                    'title' => 'Mock Group Discussion',
                    'event_type' => 'workshop',
                    'event_date' => Carbon::today()->subDays(10)->toDateString(),
                    'venue' => 'Seminar Room 1',
                    'description' => 'Practice group discussion with live feedback from industry panelists. Topics will be announced on the day.',
                    'seats' => 40,
                    'registration_deadline' => Carbon::today()->subDays(12)->toDateString(),
                    'is_published' => true,
                ],
                [
                    'title' => 'Campus Placement Orientation',
                    'event_type' => 'seminar',
                    'event_date' => Carbon::today()->addDays(7)->toDateString(),
                    'venue' => 'Auditorium',
                    'description' => 'Comprehensive orientation for upcoming campus placement season. Eligibility criteria, process timelines and company profiles will be shared.',
                    'seats' => 120,
                    'registration_deadline' => Carbon::today()->addDays(6)->toDateString(),
                    'is_published' => true,
                ],
                [
                    'title' => 'Industry Expert Talk: FinTech',
                    'event_type' => 'other',
                    'event_date' => Carbon::today()->addDays(30)->toDateString(),
                    'venue' => 'Lecture Hall 101',
                    'description' => 'Industry experts from leading FinTech companies will share insights on the future of financial technology and career opportunities.',
                    'seats' => 80,
                    'registration_deadline' => Carbon::today()->addDays(28)->toDateString(),
                    'is_published' => true,
                ],
            ];

            $createdEvents = [];
            foreach ($careerEvents as $ceDef) {
                $createdEvents[] = CareerEvent::firstOrCreate(
                    ['title' => $ceDef['title']],
                    array_merge($ceDef, ['organizer_id' => $cmcUser?->id])
                );
            }

            // Register 6-8 students for events 1, 2, 4 (indices 0, 1, 3)
            $registerForEventIndices = [0, 1, 3];
            foreach ($registerForEventIndices as $eIdx) {
                if (!isset($createdEvents[$eIdx])) continue;
                $event = $createdEvents[$eIdx];

                foreach ($students->take(7) as $student) {
                    CareerEventRegistration::firstOrCreate(
                        ['career_event_id' => $event->id, 'student_id' => $student->id],
                        ['attended' => Carbon::parse($event->event_date)->isPast()]
                    );
                }
            }
            $this->command->info('✓ Section 14 (Career Events) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 14 failed: ' . $e->getMessage());
        }

        // SECTION 15 — Internships
        try {
            $this->command->info('Seeding Section 15: Internships...');

            $internshipDefs = [
                ['company_name' => 'Deloitte India',      'role_title' => 'Finance Intern',            'start_date' => '2025-05-01', 'end_date' => '2025-07-31', 'status' => 'completed', 'stipend' => 20000],
                ['company_name' => 'ICICI Prudential',    'role_title' => 'Marketing Research Intern', 'start_date' => '2025-06-01', 'end_date' => '2025-08-31', 'status' => 'completed', 'stipend' => 15000],
                ['company_name' => 'Nestle India',        'role_title' => 'HR Intern',                 'start_date' => '2025-07-01', 'end_date' => null,          'status' => 'ongoing',   'stipend' => 12000],
                ['company_name' => 'TCS BPS',             'role_title' => 'Consulting Intern',         'start_date' => '2025-07-15', 'end_date' => null,          'status' => 'ongoing',   'stipend' => 18000],
            ];

            foreach ($internshipDefs as $idx => $def) {
                $student = $students->get($idx);
                if (!$student) continue;

                Internship::firstOrCreate(
                    ['student_id' => $student->id, 'company_name' => $def['company_name']],
                    [
                        'role_title'       => $def['role_title'],
                        'start_date'       => $def['start_date'],
                        'end_date'         => $def['end_date'],
                        'type'             => 'summer',
                        'status'           => $def['status'],
                        'stipend'          => $def['stipend'],
                        'description'      => 'Internship at ' . $def['company_name'] . ' as ' . $def['role_title'] . '. Gained practical exposure to industry practices.',
                        'supervisor_name'  => 'Industry Supervisor',
                        'supervisor_email' => 'supervisor@' . strtolower(str_replace(' ', '', $def['company_name'])) . '.com',
                        'rating'           => $def['status'] === 'completed' ? rand(4, 5) : null,
                        'feedback'         => $def['status'] === 'completed' ? 'Excellent work ethic and professional attitude.' : null,
                        'approved_by'      => $adminUser?->id,
                    ]
                );
            }
            $this->command->info('✓ Section 15 (Internships) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 15 failed: ' . $e->getMessage());
        }

        // SECTION 16 — Assessment Components
        try {
            $this->command->info('Seeding Section 16: Assessment Components...');

            // Note: AssessmentComponent links to exam_result_id, not subject directly
            // We'll add components to existing ExamResults
            $examResults = ExamResult::take(10)->get();
            $componentTypes = ['ia1', 'ia2', 'end_semester'];
            $maxMarksByType = ['ia1' => 30, 'ia2' => 30, 'end_semester' => 100];

            foreach ($examResults->take(5) as $result) {
                foreach ($componentTypes as $cType) {
                    AssessmentComponent::firstOrCreate(
                        ['exam_result_id' => $result->id, 'component_type' => $cType],
                        [
                            'marks_obtained' => rand(10, $maxMarksByType[$cType]),
                            'max_marks'      => $maxMarksByType[$cType],
                            'marked_by'      => $teachers->first()->user_id ?? null,
                            'marked_at'      => now()->subDays(rand(5, 20)),
                            'remarks'        => 'Marks entered by faculty.',
                        ]
                    );
                }
            }
            $this->command->info('✓ Section 16 (Assessment Components) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 16 failed: ' . $e->getMessage());
        }

        // SECTION 17 — Subject Discussions
        try {
            $this->command->info('Seeding Section 17: Subject Discussions...');

            $discussionDefs = [
                [
                    'title' => 'Doubt regarding DCF Valuation method',
                    'body'  => 'I have a doubt about how the terminal value is calculated in the DCF model. The textbook seems to use a different formula than what was taught in class. Can someone clarify?',
                ],
                [
                    'title' => 'Case Study: Tata Group diversification strategy',
                    'body'  => 'For the upcoming case study assignment, I wanted to start a discussion on the key factors that drove Tata Group\'s diversification. Please share your views.',
                ],
            ];

            $replyDefs = [
                ['body' => 'Great question! The terminal value in DCF is calculated using the Gordon Growth Model: TV = FCF * (1+g) / (r-g). Let me know if this helps.'],
                ['body' => 'I had the same doubt. I checked Chapter 8 and it matches what the professor explained. The book uses a simplified version.'],
                ['body' => 'The Tata Group diversification was driven by both related and unrelated diversification. Key factors include risk mitigation and capitalising on India\'s growing economy.'],
            ];

            $discussionSubjects = $subjects->take(3);

            foreach ($discussionSubjects as $sIdx => $subject) {
                foreach ($discussionDefs as $dIdx => $dDef) {
                    $poster = $students->get(($sIdx + $dIdx) % $students->count());
                    if (!$poster) continue;

                    $discussion = SubjectDiscussion::firstOrCreate(
                        ['title' => $dDef['title'], 'subject_id' => $subject->id, 'posted_by' => $poster->user_id],
                        [
                            'body'        => $dDef['body'],
                            'term_id'     => $currentTerm->id,
                            'is_pinned'   => false,
                            'is_resolved' => false,
                            'views'       => rand(5, 40),
                        ]
                    );

                    // Add 2-3 replies
                    foreach (array_slice($replyDefs, 0, 2) as $rIdx => $rDef) {
                        $replier = ($rIdx % 2 === 0)
                            ? ($teachers->get($sIdx % $teachers->count())?->user_id ?? $poster->user_id)
                            : ($students->get(($sIdx + $rIdx + 1) % $students->count())?->user_id ?? $poster->user_id);

                        SubjectDiscussionReply::firstOrCreate(
                            ['discussion_id' => $discussion->id, 'posted_by' => $replier, 'body' => substr($rDef['body'], 0, 50)],
                            [
                                'body'               => $rDef['body'],
                                'is_accepted_answer' => $rIdx === 0,
                            ]
                        );
                    }
                }
            }
            $this->command->info('✓ Section 17 (Subject Discussions) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 17 failed: ' . $e->getMessage());
        }

        // SECTION 18 — Alumni Profiles
        try {
            $this->command->info('Seeding Section 18: Alumni Profiles...');

            $alumniDefs = [
                ['current_employer' => 'Deloitte',       'current_role' => 'Management Consultant',       'graduation_year' => 2022, 'linkedin_url' => 'https://linkedin.com/in/alumni-deloitte',     'is_verified' => true,  'city' => 'Mumbai',    'feedback' => 'The program gave me an excellent foundation for my consulting career.'],
                ['current_employer' => 'Goldman Sachs',  'current_role' => 'Investment Banking Analyst',  'graduation_year' => 2022, 'linkedin_url' => 'https://linkedin.com/in/alumni-goldman',      'is_verified' => true,  'city' => 'Mumbai',    'feedback' => 'Strong finance curriculum helped me crack Goldman Sachs placement.'],
                ['current_employer' => 'Nestlé India',   'current_role' => 'Brand Manager',               'graduation_year' => 2023, 'linkedin_url' => 'https://linkedin.com/in/alumni-nestle',       'is_verified' => false, 'city' => 'Pune',      'feedback' => null],
                ['current_employer' => 'HDFC Bank',      'current_role' => 'Relationship Manager',        'graduation_year' => 2023, 'linkedin_url' => 'https://linkedin.com/in/alumni-hdfc',         'is_verified' => false, 'city' => 'Bangalore', 'feedback' => null],
            ];

            // We need 4 extra alumni students — use last 4 students
            $alumniStudents = $students->take(4);

            foreach ($alumniStudents as $idx => $student) {
                if (!isset($alumniDefs[$idx])) continue;
                $def = $alumniDefs[$idx];

                AlumniProfile::firstOrCreate(
                    ['student_id' => $student->id],
                    [
                        'graduation_year'  => $def['graduation_year'],
                        'current_employer' => $def['current_employer'],
                        'current_role'     => $def['current_role'],
                        'current_salary'   => rand(6, 20) * 100000,
                        'linkedin_url'     => $def['linkedin_url'],
                        'city'             => $def['city'],
                        'country'          => 'India',
                        'feedback'         => $def['feedback'],
                        'is_verified'      => $def['is_verified'],
                    ]
                );
            }
            $this->command->info('✓ Section 18 (Alumni Profiles) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 18 failed: ' . $e->getMessage());
        }

        // SECTION 19 — Approval Workflows (HOD pending)
        try {
            $this->command->info('Seeding Section 19: Approval Workflows...');

            $applicants = Applicant::take(5)->get();

            foreach ($applicants as $applicant) {
                ApprovalWorkflow::firstOrCreate(
                    [
                        'approvable_type' => \App\Models\Applicant::class,
                        'approvable_id'   => $applicant->id,
                        'approver_role'   => 'hod',
                    ],
                    [
                        'status'   => 'pending',
                        'sla_days' => 3,
                        'due_at'   => now()->addDays(3),
                    ]
                );
            }
            $this->command->info('✓ Section 19 (Approval Workflows) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 19 failed: ' . $e->getMessage());
        }

        // SECTION 20 — Teacher Availability
        try {
            $this->command->info('Seeding Section 20: Teacher Availability...');

            $days = [1, 2, 3, 4, 5]; // Mon-Fri
            $slotsList = $slots->values();

            foreach ($teachers as $teacher) {
                foreach ($days as $day) {
                    foreach ($slotsList as $sIdx => $slot) {
                        // Mark last 2 slots on Friday as unavailable
                        $isUnavailable = ($day === 5 && $sIdx >= ($slotsList->count() - 2));
                        $availability  = $isUnavailable ? 'unavailable' : 'available';

                        TeacherAvailability::firstOrCreate(
                            [
                                'teacher_id'         => $teacher->id,
                                'term_id'            => $currentTerm->id,
                                'day_of_week'        => $day,
                                'timetable_slot_id'  => $slot->id,
                            ],
                            [
                                'availability' => $availability,
                                'notes'        => $isUnavailable ? 'Friday afternoon — department meetings' : null,
                            ]
                        );
                    }
                }
            }
            $this->command->info('✓ Section 20 (Teacher Availability) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 20 failed: ' . $e->getMessage());
        }

        // SECTION 21 — Fee Payment Requests
        try {
            $this->command->info('Seeding Section 21: Fee Payment Requests...');

            $feeDemands = FeeDemand::whereIn('student_id', $students->take(4)->pluck('id'))->get()->keyBy('student_id');
            $paymentStatuses = ['pending', 'verified', 'rejected', 'pending'];
            $paymentModes    = ['online', 'dd', 'cheque', 'online'];

            foreach ($students->take(4) as $idx => $student) {
                $demand = $feeDemands->get($student->id);
                if (!$demand) continue;

                FeePaymentRequest::firstOrCreate(
                    ['student_id' => $student->id, 'fee_demand_id' => $demand->id],
                    [
                        'amount'          => 150000,
                        'payment_method'  => $paymentModes[$idx],
                        'bank_name'       => ['HDFC Bank', 'SBI', 'ICICI Bank', 'Axis Bank'][$idx],
                        'transaction_ref' => 'TXN' . strtoupper(uniqid()),
                        'proof_path'      => 'uploads/fee_proofs/sample_receipt_' . ($idx + 1) . '.pdf',
                        'submitted_at'    => now()->subDays(rand(2, 10)),
                        'status'          => $paymentStatuses[$idx],
                        'verified_by'     => $paymentStatuses[$idx] === 'verified' ? $adminUser?->id : null,
                        'verified_at'     => $paymentStatuses[$idx] === 'verified' ? now()->subDays(1) : null,
                        'notes'           => $paymentStatuses[$idx] === 'rejected' ? 'Transaction reference could not be verified. Please resubmit.' : null,
                    ]
                );
            }
            $this->command->info('✓ Section 21 (Fee Payment Requests) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 21 failed: ' . $e->getMessage());
        }

        // SECTION 22 — Exam Registration
        try {
            $this->command->info('Seeding Section 22: Exam Registrations...');

            $endSemExams = Exam::where('type', 'final')->get();

            foreach ($endSemExams as $exam) {
                foreach ($students as $student) {
                    ExamRegistration::firstOrCreate(
                        ['exam_id' => $exam->id, 'student_id' => $student->id],
                        [
                            'status'               => 'approved',
                            'attendance_eligible'  => true,
                            'fee_cleared'          => true,
                            'remarks'              => null,
                            'approved_by'          => $adminUser?->id,
                        ]
                    );
                }
            }
            $this->command->info('✓ Section 22 (Exam Registrations) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 22 failed: ' . $e->getMessage());
        }

        // SECTION 23 — Student Resumes
        try {
            $this->command->info('Seeding Section 23: Student Resumes...');

            $resumeSkillSets = [
                ['Leadership', 'Analytics', 'Finance', 'Excel', 'PowerPoint'],
                ['Marketing', 'Branding', 'Social Media', 'Market Research', 'Communication'],
                ['Finance', 'Investment Analysis', 'Valuation', 'Bloomberg', 'Python'],
                ['Operations', 'Supply Chain', 'Lean Management', 'ERP', 'Six Sigma'],
                ['HR Management', 'Recruitment', 'Talent Management', 'HRIS', 'Training'],
                ['Consulting', 'Strategy', 'Business Development', 'CRM', 'Presentation'],
            ];

            $resumeObjectives = [
                'Seeking a challenging management role in Finance where I can leverage my analytical skills and academic training to contribute meaningfully to organisational growth.',
                'Goal-oriented MBA graduate seeking Marketing Manager role in FMCG sector. Passionate about consumer insights and brand building.',
                'Finance professional with strong quantitative background seeking investment banking analyst role. Adept at financial modelling and valuation.',
                'Operations Management graduate seeking Supply Chain Analyst position. Experienced in process optimisation and lean management principles.',
                'Aspiring HR professional with strong interpersonal skills seeking HR Business Partner role in a dynamic organisation.',
                'Management consultant aspirant with strong analytical and communication skills seeking consulting role in strategy or operations.',
            ];

            $workExperiences = [
                [['company' => 'Deloitte India', 'role' => 'Finance Intern', 'duration' => 'May-Jul 2025', 'description' => 'Financial analysis, DCF modelling, client presentations']],
                [['company' => 'Hindustan Unilever', 'role' => 'Marketing Intern', 'duration' => 'Jun-Aug 2025', 'description' => 'Brand research, consumer surveys, campaign analysis']],
                [['company' => 'ICICI Bank', 'role' => 'Research Analyst Intern', 'duration' => 'May-Jul 2025', 'description' => 'Equity research, financial modelling, sector reports']],
                [['company' => 'Amazon India', 'role' => 'Operations Intern', 'duration' => 'May-Jul 2025', 'description' => 'Supply chain optimisation, vendor management, KPI tracking']],
                [['company' => 'Infosys BPO', 'role' => 'HR Intern', 'duration' => 'Jun-Aug 2025', 'description' => 'Recruitment support, onboarding, employee engagement']],
                [['company' => 'McKinsey & Company', 'role' => 'Business Analyst Intern', 'duration' => 'May-Aug 2025', 'description' => 'Client research, deck preparation, market analysis']],
            ];

            foreach ($students->take(6) as $idx => $student) {
                StudentResume::firstOrCreate(
                    ['student_id' => $student->id],
                    [
                        'headline'    => $student->user?->name . ' | MBA Graduate | ' . ['Finance', 'Marketing', 'Consulting', 'Operations', 'HR', 'Strategy'][$idx % 6],
                        'objective'   => $resumeObjectives[$idx % count($resumeObjectives)],
                        'skills'      => $resumeSkillSets[$idx % count($resumeSkillSets)],
                        'experience'  => $workExperiences[$idx % count($workExperiences)],
                        'languages'   => ['English', 'Hindi'],
                        'projects'    => [
                            ['title' => 'Capstone Project: ' . ['Market Entry Strategy', 'Financial Portfolio Analysis', 'Brand Relaunch Plan', 'Supply Chain Redesign', 'Talent Acquisition Study', 'Strategic Consulting Case'][$idx % 6], 'description' => 'Comprehensive project submitted as part of MBA curriculum.'],
                        ],
                        'certifications' => [
                            ['name' => ['CFA Level 1', 'Google Analytics', 'PMP Certification', 'Six Sigma Green Belt', 'SHRM-CP', 'Consulting Foundations'][$idx % 6], 'issuer' => 'Professional Body', 'year' => 2025],
                        ],
                        'linkedin_url' => 'https://linkedin.com/in/' . strtolower(str_replace(' ', '-', $student->user?->name ?? 'student')),
                        'is_complete'  => $idx < 4,
                    ]
                );
            }
            $this->command->info('✓ Section 23 (Student Resumes) done');
        } catch (\Throwable $e) {
            $this->command->warn('Section 23 failed: ' . $e->getMessage());
        }

        // Seed StudentSubjectEnrollment for current term (used by many student features)
        try {
            $this->command->info('Seeding StudentSubjectEnrollments...');
            $currentEnrollments = Enrollment::where('term_id', $currentTerm->id)->get();
            foreach ($currentEnrollments as $enrollment) {
                StudentSubjectEnrollment::firstOrCreate(
                    [
                        'student_id' => $enrollment->student_id,
                        'subject_id' => $enrollment->subject_id,
                        'term_id'    => $enrollment->term_id,
                    ],
                    [
                        'enrollment_type' => 'compulsory',
                        'status'          => 'active',
                    ]
                );
            }
            $this->command->info('✓ StudentSubjectEnrollments done');
        } catch (\Throwable $e) {
            $this->command->warn('StudentSubjectEnrollments failed: ' . $e->getMessage());
        }

        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('  MasterDataSeeder completed successfully!');
        $this->command->info('===========================================');
    }
}
