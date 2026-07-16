<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\AcademicTranscript;
use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanActionEvidence;
use App\Models\AcademicDeanApprovalItem;
use App\Models\AcademicDeanCalendarEvent;
use App\Models\AcademicDeanDecision;
use App\Models\AcademicDeanExportLog;
use App\Models\AcademicDeanMeetingMinute;
use App\Models\AcademicDeanOperatingRecord;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanPolicyAudit;
use App\Models\AcademicDeanReadinessItem;
use App\Models\AcademicDeanReportPack;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\AcademicDeanReviewTemplate;
use App\Models\AcademicDeanRiskMitigation;
use App\Models\AcademicDeanRiskSnapshot;
use App\Models\AcademicDeanRiskThreshold;
use App\Models\AcademicDeanSavedView;
use App\Models\AcademicPmcCurriculumPlan;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcFacultyLoadPlan;
use App\Models\AcademicPmcAnalyticsSnapshot;
use App\Models\AcademicPmcActionDependency;
use App\Models\AcademicPmcActionEvidence;
use App\Models\AcademicPmcActionReminder;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcAutomationExecution;
use App\Models\AcademicPmcAutomationRule;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcGroupDeliveryTracker;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcPlanningCycle;
use App\Models\AcademicPmcParentEscalation;
use App\Models\AcademicPmcReadinessItem;
use App\Models\AcademicPmcRemedialAction;
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcSessionDeliveryLog;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableControl;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcWorkItem;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseFeedback;
use App\Models\CoAttainment;
use App\Models\CoPoMapping;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\ElectiveRegistrationWindow;
use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\LeaveApplication;
use App\Models\MarksAppeal;
use App\Models\MentorMeeting;
use App\Models\MentorMessage;
use App\Models\ObeSurvey;
use App\Models\ObeSurveyResponse;
use App\Models\PoAttainment;
use App\Models\PmcAssessmentComponentConfig;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\ProgramSpecificOutcome;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use App\Models\SubjectDiscussion;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\AcademicScopeService;
use App\Services\AcademicPmcV003Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AcademicsPmcV004DemoSeeder extends Seeder
{
    public function seedPmcV004Signals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
    {
        $pmcManager = User::where('email', 'pmc.manager@college.com')->first() ?: $pmcHead;
        $pmcOfficer = User::where('email', 'pmc.officer@college.com')->first() ?: $pmcHead;
        $mentor = User::where('email', 'faculty.mentor@college.com')->first() ?: $pmcHead;
        $programId = $program?->id;
        $subjectId = $subject?->id;
        $studentId = $student?->id;

        $records = [
            ['planning', 'annual_plan', 'Annual PMC Academic Execution Plan 2026-27', 'approved', 'medium', 82, $pmcHead->id, 'Annual program delivery, assessment, review, and resource readiness plan.'],
            ['semester_readiness', 'curriculum_approved', 'Semester readiness checklist - curriculum approved', 'open', 'high', 72, $pmcManager->id, 'Curriculum, faculty, timetable, elective, assessment, mentor, and student-risk checklist.'],
            ['academic_calendar', 'internal_assessment_calendar', 'Internal assessment and PMC review calendar', 'published', 'low', 90, $pmcOfficer->id, 'Assessment windows, review meetings, elective deadlines, and delivery checkpoints.'],
            ['curriculum_rollout', 'co_po_mapping', 'CO/PO mapping pending for Management Analytics', 'blocked', 'critical', 48, $pmcManager->id, 'CO/PO/PSO mapping must be completed before final curriculum rollout.'],
            ['syllabus_version', 'version_compare', 'Syllabus version v2 ready for Dean approval', 'pmc_review', 'high', 76, $pmcHead->id, 'Versioned syllabus with change log, owner sign-off, and compliance checklist.'],
            ['curriculum_validation', 'credit_rule', 'Credit structure validation warning', 'open', 'high', 65, $pmcManager->id, 'Elective and audit credits need validation against academic rules.'],
            ['faculty_allocation', 'unassigned_subject', 'Advanced Analytics subject has no backup faculty', 'open', 'high', 60, $pmcManager->id, 'Primary faculty exists but backup and lab support are missing.'],
            ['faculty_allocation', 'overload', 'Faculty overload requires Dean approval', 'pmc_review', 'critical', 42, $pmcHead->id, 'Weekly planned load exceeds threshold and needs adjustment or approval.'],
            ['workload_rule', 'mentor_capacity', 'Mentor capacity threshold configured', 'active', 'medium', 80, $pmcOfficer->id, 'Mentor load cap, overload threshold, subject cap, and substitution risk rules.'],
            ['faculty_shortage', 'adjunct_required', 'Adjunct faculty required for Business Analytics lab', 'open', 'high', 55, $pmcHead->id, 'Shortage planning record for unfilled lab and tutorial load.'],
            ['timetable_governance', 'freeze_due', 'Timetable freeze due this week', 'conflict_review', 'high', 70, $pmcOfficer->id, 'Timetable must move through PMC approval, Dean approval, publish, and freeze.'],
            ['timetable_conflict', 'conflict', 'Room and teacher clash in Tuesday slot', 'open', 'critical', 35, $pmcOfficer->id, 'Conflict board item with resolution actions: reassign teacher, room, or slot.'],
            ['substitution_control', 'repeated_substitution', 'Repeated substitution trend in analytics studio', 'open', 'high', 58, $pmcManager->id, 'Repeated substitutions need review and backup faculty plan.'],
            ['course_delivery', 'behind_schedule', 'Course delivery behind planned sessions', 'open', 'critical', 46, $pmcManager->id, 'Planned vs actual delivery gap requires remedial and faculty follow-up.'],
            ['course_delivery', 'marks_pending', 'Internal assessment marks pending', 'open', 'high', 52, $pmcOfficer->id, 'Assessment marks need submission before PMC review pack.'],
            ['delivery_risk', 'low_feedback_subject', 'Low feedback subject needs corrective action', 'open', 'high', 54, $pmcHead->id, 'Poor feedback, weak result trend, and attendance drop detected.'],
            ['remedial_plan', 'remedial_class', 'Remedial class plan for weak performers', 'assigned', 'medium', 74, $mentor->id, 'Remedial class schedule and owner tracking.'],
            ['student_success', 'retention_risk', 'Student success risk needs mentor intervention', 'open', 'critical', 38, $mentor->id, 'Attendance, academic, mentor follow-up, and parent escalation risk.'],
            ['intervention', 'parent_contact', 'Parent contact and mentor follow-up pending', 'assigned', 'high', 57, $mentor->id, 'Intervention lifecycle from mentor contact to resolution.'],
            ['mentor_governance', 'mentor_overdue', 'Mentor follow-up overdue for assigned cohort', 'open', 'high', 50, $mentor->id, 'Mentor load and follow-up compliance queue.'],
            ['parent_escalation', 'guardian_call', 'Parent escalation required for attendance decline', 'open', 'high', 49, $mentor->id, 'Parent/guardian escalation with next follow-up date.'],
        ];

        foreach ($records as [$type, $category, $title, $status, $risk, $score, $ownerId, $description]) {
            AcademicPmcOperatingRecord::firstOrCreate(
                ['record_type' => $type, 'title' => $title],
                [
                    'category' => $category,
                    'description' => $description,
                    'program_id' => $programId,
                    'term_id' => $termId,
                    'subject_id' => in_array($type, ['curriculum_rollout', 'syllabus_version', 'curriculum_validation', 'faculty_allocation', 'course_delivery', 'delivery_risk', 'remedial_plan'], true) ? $subjectId : null,
                    'student_id' => in_array($type, ['student_success', 'intervention', 'mentor_governance', 'parent_escalation'], true) ? $studentId : null,
                    'owner_user_id' => $ownerId,
                    'created_by' => $pmcHead->id,
                    'status' => $status,
                    'priority' => in_array($risk, ['critical', 'high'], true) ? 'high' : 'normal',
                    'risk_band' => $risk,
                    'score' => $score,
                    'due_at' => now()->addDays($risk === 'critical' ? 1 : 5),
                    'source_type' => 'pmc_v004_demo',
                    'source_key' => $type . ':' . $category,
                    'source_route' => route('academics.pmc.command'),
                    'metrics' => ['planned' => 100, 'actual' => $score, 'gap' => 100 - $score],
                    'checklist' => ['owner_assigned' => true, 'evidence_required' => in_array($risk, ['critical', 'high'], true), 'dean_escalation' => $risk === 'critical'],
                    'payload' => ['recommended_action' => 'Review source record and close blocker with evidence.', 'version' => 'Academics PMC OS v0.04'],
                ]
            );
        }

        foreach ([
            ['Annual PMC Execution Plan 2026-27', 'annual_plan', 'approved', 82, $pmcHead->id],
            ['PGDM Term 1 Semester Readiness Plan', 'semester_readiness', 'pmc_review', 58, $pmcManager->id],
        ] as [$title, $cycleType, $status, $score, $ownerId]) {
            $cycle = AcademicPmcPlanningCycle::firstOrCreate(
                ['title' => $title, 'cycle_type' => $cycleType],
                [
                    'academic_year' => '2026-27',
                    'program_id' => $programId,
                    'term_id' => $termId,
                    'owner_user_id' => $ownerId,
                    'approved_by' => $status === 'approved' ? $pmcHead->id : null,
                    'status' => $status,
                    'readiness_score' => $score,
                    'starts_at' => now()->startOfMonth(),
                    'ends_at' => now()->addMonths(5),
                    'approved_at' => $status === 'approved' ? now()->subDay() : null,
                    'metadata' => ['version' => 'Academics PMC OS v0.056', 'open_blockers' => $status === 'approved' ? 1 : 4],
                ]
            );

            foreach ([
                ['curriculum_ready', 'Curriculum and syllabus approved', 'Syllabus version, CO/PO mapping, credit rules, and rollout checklist.', 'blocked', 'critical', 45, true, $pmcManager->id],
                ['subjects_mapped', 'Subjects mapped to term and student baskets', 'Core, elective, backlog, repeat, and audit course baskets reviewed.', 'in_progress', 'high', 68, true, $pmcOfficer->id],
                ['faculty_assigned', 'Faculty and backup allocation ready', 'Primary, co-faculty, lab/tutorial, backup, and acknowledgement status clear.', 'in_progress', 'high', 62, true, $pmcManager->id],
                ['timetable_ready', 'Timetable conflict-free and publish-ready', 'Groups, rooms, faculty availability, locked slots, and conflict checks pass.', 'blocked', 'critical', 40, true, $pmcOfficer->id],
                ['assessment_ready', 'Assessment components and internal calendar ready', 'Internal assessment components, marks windows, and review dates configured.', 'open', 'medium', 55, false, $pmcOfficer->id],
                ['mentor_student_risk_ready', 'Mentors and student-risk review ready', 'Mentor assignments, interventions, parent escalations, and retention-risk checks assigned.', 'open', 'medium', 52, false, $mentor->id],
                ['resources_ready', 'Classroom, lab, and LMS resources ready', 'Rooms, labs, LMS/material readiness, and delivery resources confirmed.', 'open', 'medium', 60, false, $pmcOfficer->id],
            ] as [$section, $itemTitle, $description, $itemStatus, $severity, $completion, $blocker, $itemOwner]) {
                AcademicPmcReadinessItem::firstOrCreate(
                    ['planning_cycle_id' => $cycle->id, 'section' => $section],
                    [
                        'title' => $itemTitle,
                        'description' => $description,
                        'owner_user_id' => $itemOwner,
                        'status' => $itemStatus,
                        'severity' => $severity,
                        'completion_percent' => $completion,
                        'is_blocker' => $blocker,
                        'due_at' => now()->addDays($blocker ? 2 : 7),
                        'source_type' => 'pmc_v056_demo',
                        'source_key' => $cycleType . ':' . $section,
                        'evidence' => [['label' => 'Demo readiness evidence', 'status' => $itemStatus === 'blocked' ? 'missing' : 'pending']],
                        'metadata' => ['version' => 'Academics PMC OS v0.056'],
                    ]
                );
            }
        }

        foreach ([
            ['curriculum_change', 'Curriculum change approval for analytics syllabus', 'pending', $pmcManager->id],
            ['faculty_allocation', 'Faculty overload exception approval', 'pending', $pmcHead->id],
            ['timetable_freeze', 'Timetable freeze approval for PGDM term', 'pending', $pmcOfficer->id],
            ['student_intervention', 'Escalated student success intervention', 'evidence_requested', $mentor->id],
        ] as [$type, $title, $status, $ownerId]) {
            AcademicPmcApproval::firstOrCreate(
                ['approval_type' => $type, 'title' => $title],
                [
                    'description' => 'Seeded PMC v0.04 approval cockpit item.',
                    'program_id' => $programId,
                    'term_id' => $termId,
                    'subject_id' => $subjectId,
                    'requested_by' => $pmcOfficer->id,
                    'owner_user_id' => $ownerId,
                    'status' => $status,
                    'sla_status' => $status === 'pending' ? 'at_risk' : 'waiting_evidence',
                    'due_at' => now()->addDays(2),
                    'source_type' => 'pmc_v004_demo',
                    'source_key' => $type,
                    'evidence' => [['label' => 'Demo evidence checklist', 'status' => 'pending']],
                    'metadata' => ['version' => 'Academics PMC OS v0.04'],
                ]
            );
        }

        if ($studentId) {
            $successPlan = AcademicPmcStudentSuccessPlan::firstOrCreate(
                ['student_id' => $studentId, 'risk_type' => 'retention_risk'],
                [
                    'program_id' => $programId,
                    'batch_id' => $student?->batch_id,
                    'mentor_user_id' => $mentor->id,
                    'risk_band' => 'critical',
                    'status' => 'intervention_due',
                    'intervention_plan' => 'Mentor meeting, parent call, remedial plan, and weekly PMC review.',
                    'next_review_at' => now()->addDays(2),
                    'parent_escalation_required' => true,
                    'signals' => ['risk_score' => 82, 'attendance_percent' => 61, 'average_marks' => 38, 'exam_absences' => 1, 'open_grievances' => 1, 'mentor_meetings_30d' => 0, 'reasons' => ['Attendance below 75%', 'Average marks below 45', 'No mentor meeting in last 30 days']],
                    'metadata' => ['version' => 'Academics PMC OS v0.057'],
                ]
            );

            $intervention = AcademicPmcStudentIntervention::firstOrCreate(
                ['student_success_plan_id' => $successPlan->id, 'intervention_type' => 'mentor_meeting'],
                [
                    'student_id' => $studentId,
                    'program_id' => $programId,
                    'batch_id' => $student?->batch_id,
                    'owner_user_id' => $mentor->id,
                    'created_by' => $pmcHead->id,
                    'status' => 'open',
                    'priority' => 'critical',
                    'reason' => 'Attendance-performance risk with no recent mentor meeting.',
                    'action_plan' => 'Complete mentor meeting, assign remedial class, call parent, and report outcome to PMC.',
                    'due_at' => now()->addDays(2),
                    'evidence' => [['label' => 'mentor note', 'status' => 'pending']],
                    'metadata' => ['version' => 'Academics PMC OS v0.057'],
                ]
            );

            AcademicPmcParentEscalation::firstOrCreate(
                ['student_success_plan_id' => $successPlan->id, 'reason' => 'attendance_performance_risk'],
                [
                    'intervention_id' => $intervention->id,
                    'student_id' => $studentId,
                    'owner_user_id' => $mentor->id,
                    'created_by' => $pmcHead->id,
                    'guardian_name' => $student?->guardian_name ?: 'Parent / Guardian',
                    'guardian_phone' => $student?->guardian_phone ?: '9999999999',
                    'status' => 'scheduled',
                    'scheduled_at' => now()->addDay(),
                    'outcome_note' => null,
                    'metadata' => ['version' => 'Academics PMC OS v0.057'],
                ]
            );
        }

        if ($subjectId) {
            $deliveryCheckpoint = AcademicPmcCourseDeliveryCheckpoint::firstOrCreate(
                ['subject_id' => $subjectId, 'term_id' => $termId],
                [
                    'program_id' => $programId,
                    'batch_id' => $student?->batch_id,
                    'teacher_id' => Teacher::first()?->id,
                    'owner_user_id' => $pmcManager->id,
                    'planned_sessions' => 24,
                    'conducted_sessions' => 17,
                    'missed_sessions' => 7,
                    'marks_pending_count' => 8,
                    'attendance_percent' => 68.50,
                    'feedback_score' => 3.20,
                    'delivery_score' => 42,
                    'risk_band' => 'critical',
                    'status' => 'action_required',
                    'next_review_at' => now()->addDays(2),
                    'signals' => [
                        'risk_score' => 78,
                        'reasons' => [
                            'Planned sessions not conducted',
                            'Low attendance in delivered sessions',
                            'Marks pending',
                            'Low course feedback',
                        ],
                    ],
                    'metadata' => ['version' => 'Academics PMC OS v0.058'],
                ]
            );

            AcademicPmcRemedialAction::firstOrCreate(
                ['checkpoint_id' => $deliveryCheckpoint->id, 'action_type' => 'makeup_session'],
                [
                    'subject_id' => $subjectId,
                    'teacher_id' => $deliveryCheckpoint->teacher_id,
                    'owner_user_id' => $pmcOfficer->id,
                    'created_by' => $pmcHead->id,
                    'status' => 'faculty_contacted',
                    'priority' => 'high',
                    'reason' => 'Critical delivery gap from missed sessions and pending marks.',
                    'action_plan' => 'Schedule makeup sessions, collect pending internal marks, and submit evidence before PMC review.',
                    'due_at' => now()->addDays(3),
                    'evidence' => [['label' => 'makeup session calendar', 'status' => 'pending']],
                    'metadata' => ['version' => 'Academics PMC OS v0.058'],
                ]
            );
        }

        $template = AcademicPmcReviewGovernanceRecord::firstOrCreate(
            ['record_type' => 'template', 'title' => 'Weekly PMC Review Template'],
            ['body' => 'Curriculum readiness, faculty allocation, timetable, delivery, student success, approvals, and actions.', 'owner_user_id' => $pmcHead->id, 'status' => 'active', 'decision_type' => 'weekly_pmc', 'metadata' => ['recurrence' => 'weekly']]
        );
        $meeting = AcademicPmcReviewMeeting::firstOrCreate(
            ['title' => 'PMC v0.04 Weekly Academic Execution Review'],
            ['review_type' => 'weekly_pmc', 'scheduled_for' => now()->addDays(2), 'chair_user_id' => $pmcHead->id, 'status' => 'scheduled', 'agenda' => 'Review blockers and decisions.', 'metadata' => ['template_id' => $template->id]]
        );
        foreach ([
            ['agenda', 'Resolve timetable conflict before freeze', 'expected_decision'],
            ['minutes', 'PMC minutes draft with action extraction', 'minutes_draft'],
            ['decision', 'Approve backup faculty plan with Dean escalation', 'faculty_allocation'],
            ['evidence', 'Evidence metadata for curriculum rollout closure', 'evidence_required'],
        ] as [$type, $title, $decisionType]) {
            AcademicPmcReviewGovernanceRecord::firstOrCreate(
                ['record_type' => $type, 'title' => $title],
                ['meeting_id' => $meeting->id, 'body' => 'Seeded PMC v0.04 review governance record.', 'owner_user_id' => $pmcManager->id, 'status' => $type === 'minutes' ? 'draft' : 'open', 'decision_type' => $decisionType, 'due_at' => now()->addDays(3), 'evidence' => [['label' => 'source link', 'url' => route('academics.pmc.command')]], 'metadata' => ['version' => 'Academics PMC OS v0.04']]
            );
        }

        $dependencySource = AcademicPmcWorkItem::firstOrCreate(
            ['source_type' => 'pmc_v060_seed', 'source_key' => 'curriculum_evidence_prerequisite'],
            [
                'work_type' => 'review_action',
                'title' => 'Upload curriculum rollout evidence',
                'description' => 'Prerequisite evidence for closing PMC review action.',
                'program_id' => $programId,
                'term_id' => $termId,
                'subject_id' => $subjectId,
                'owner_user_id' => $pmcManager->id,
                'assigned_by' => $pmcHead->id,
                'priority' => 'high',
                'status' => 'done',
                'severity' => 'high',
                'due_at' => now()->addDay(),
                'metadata' => ['version' => 'Academics PMC OS v0.060'],
            ]
        );
        $dependentAction = AcademicPmcWorkItem::firstOrCreate(
            ['source_type' => 'pmc_v060_seed', 'source_key' => 'weekly_review_closure'],
            [
                'work_type' => 'review_action',
                'title' => 'Close weekly PMC review blockers',
                'description' => 'Verify evidence, close blockers, and update Dean review pack.',
                'program_id' => $programId,
                'term_id' => $termId,
                'subject_id' => $subjectId,
                'owner_user_id' => $pmcOfficer->id,
                'assigned_by' => $pmcHead->id,
                'priority' => 'critical',
                'status' => 'blocked',
                'severity' => 'critical',
                'due_at' => now()->addDays(2),
                'metadata' => ['requires_evidence' => true, 'version' => 'Academics PMC OS v0.060'],
            ]
        );
        AcademicPmcActionDependency::updateOrCreate(
            ['work_item_id' => $dependentAction->id, 'depends_on_work_item_id' => $dependencySource->id],
            ['dependency_type' => 'blocked_by', 'status' => 'resolved', 'reason' => 'Curriculum evidence must exist before weekly review closure.', 'created_by' => $pmcHead->id, 'resolved_at' => now()->subHour(), 'metadata' => ['version' => 'Academics PMC OS v0.060']]
        );
        AcademicPmcActionReminder::firstOrCreate(
            ['work_item_id' => $dependentAction->id, 'reminder_type' => 'closure_due'],
            ['owner_user_id' => $pmcOfficer->id, 'status' => 'scheduled', 'due_at' => now()->addDay(), 'message' => 'Weekly PMC review blocker closure is due.', 'metadata' => ['version' => 'Academics PMC OS v0.060']]
        );
        AcademicPmcActionEvidence::firstOrCreate(
            ['work_item_id' => $dependentAction->id, 'title' => 'PMC review evidence pack'],
            ['uploaded_by' => $pmcManager->id, 'evidence_type' => 'note', 'evidence_note' => 'Curriculum rollout, timetable readiness, and delivery remediation evidence reviewed.', 'verification_status' => 'submitted', 'metadata' => ['version' => 'Academics PMC OS v0.060']]
        );

        foreach ([
            ['Curriculum blocker auto-escalation', 'curriculum_mapping_gap'],
            ['Faculty overload attention rule', 'faculty_overload'],
            ['Timetable conflict refresh', 'timetable_conflict'],
            ['Student success parent escalation', 'parent_escalation_due'],
        ] as [$name, $trigger]) {
            $rule = AcademicPmcAutomationRule::firstOrCreate(
                ['name' => $name],
                ['trigger_key' => $trigger, 'conditions' => ['risk' => ['high', 'critical']], 'actions' => ['create_work_item', 'assign_owner', 'escalate'], 'priority' => 50, 'is_active' => true]
            );
            AcademicPmcAutomationExecution::firstOrCreate(
                ['idempotency_key' => 'seed-' . $trigger],
                ['rule_id' => $rule->id, 'subject_type' => 'pmc_seed', 'subject_key' => $trigger, 'status' => 'executed', 'result' => 'Seeded execution log.', 'metadata' => ['version' => 'Academics PMC OS v0.04'], 'executed_at' => now()->subHour()]
            );
        }

        foreach ([
            ['readiness_progress', 82, 'improving'],
            ['faculty_workload', 63, 'at_risk'],
            ['timetable_conflict_reduction', 70, 'stable'],
            ['student_success_risk', 48, 'worsening'],
        ] as [$type, $score, $band]) {
            AcademicPmcAnalyticsSnapshot::firstOrCreate(
                ['snapshot_type' => $type, 'snapshot_date' => now()->toDateString()],
                ['program_id' => $programId, 'term_id' => $termId, 'score' => $score, 'band' => $band, 'metrics' => ['current' => $score, 'previous' => max(0, $score - 8)], 'metadata' => ['version' => 'Academics PMC OS v0.04']]
            );
        }

        foreach ([
            'academics.pmc.command',
            'academics.pmc.planning.index',
            'academics.pmc.curriculum-governance.index',
            'academics.pmc.faculty-allocation-v004.index',
            'academics.pmc.timetable-governance.index',
            'academics.pmc.course-delivery.index',
            'academics.pmc.student-success-v004.index',
            'academics.pmc.approvals.index',
            'academics.pmc.policy-audit.index',
        ] as $route) {
            AcademicPmcPolicyAudit::updateOrCreate(
                ['route_name' => $route],
                ['method' => str_contains($route, 'index') || $route === 'academics.pmc.command' ? 'GET' : 'POST', 'required_scope' => 'pmc_scope', 'risk_level' => str_contains($route, 'approvals') ? 'high' : 'medium', 'middleware_present' => true, 'policy_present' => true, 'last_test_status' => 'passed', 'missing_enforcement' => false, 'roles_tested' => ['pmc_head', 'pmc_manager', 'pmc_officer', 'faculty_mentor', 'student'], 'metadata' => ['version' => 'Academics PMC OS v0.04']]
            );
        }

        foreach ([
            ['academics.pmc.timetable-generator.validate', 'high', 'generation_run_scope', 'scope_aware'],
            ['academics.pmc.timetable-generator.publish', 'critical', 'generation_run_scope', 'scope_aware'],
            ['academics.pmc.timetable-versions-v041.freeze', 'critical', 'timetable_version_scope', 'scope_aware'],
            ['academics.pmc.timetable-versions-v041.unfreeze', 'critical', 'timetable_version_scope', 'scope_aware'],
            ['academics.pmc.timetable-versions-v041.rollback', 'critical', 'timetable_version_scope', 'scope_aware'],
            ['academics.pmc.timetable-constraints.resolution-actions.store', 'high', 'generation_run_scope', 'scope_aware'],
            ['academics.pmc.timetable-resolution-actions.close', 'high', 'generation_run_scope', 'scope_aware'],
            ['academics.pmc.timetable-change-requests.store', 'high', 'timetable_version_scope_when_present', 'conditional_scope'],
            ['academics.pmc.timetable-change-requests.decide', 'high', 'timetable_version_scope_when_present', 'conditional_scope'],
            ['academics.pmc.substitution-intelligence.recommend', 'high', 'course_group_scope_when_present', 'conditional_scope'],
            ['academics.pmc.timetable-notifications.store', 'medium', 'source_metadata_review_needed', 'broad_write'],
            ['academics.pmc.timetable-notifications.update-status', 'medium', 'source_metadata_review_needed', 'broad_write'],
            ['academics.pmc.timetable-notifications.retry', 'medium', 'source_metadata_review_needed', 'broad_write'],
            ['academics.pmc.v004.automation.refresh', 'high', 'department_signal', 'broad_write'],
            ['academics.pmc.course-delivery.refresh', 'high', 'department_signal', 'broad_write'],
            ['academics.pmc.student-success-v004.refresh', 'high', 'department_signal', 'broad_write'],
        ] as [$route, $risk, $scope, $style]) {
            AcademicPmcPolicyAudit::updateOrCreate(
                ['route_name' => $route],
                [
                    'method' => 'POST',
                    'required_scope' => $scope,
                    'risk_level' => $risk,
                    'middleware_present' => true,
                    'policy_present' => $style !== 'broad_write',
                    'last_test_status' => 'passed',
                    'missing_enforcement' => false,
                    'roles_tested' => ['dean_academics', 'pmc_head', 'pmc_manager', 'pmc_officer', 'student'],
                    'metadata' => ['version' => 'PMC OS v0.086', 'enforcement_style' => $style],
                ]
            );
        }

        foreach ([
            ['PMC Planning Default', 'planning'],
            ['PMC Curriculum Blockers', 'curriculum-governance-v004'],
            ['PMC Faculty Overload', 'faculty-allocation-v004'],
            ['PMC Timetable Conflicts', 'timetable-governance'],
            ['PMC Student Risk', 'student-success-v004'],
            ['PMC Analytics Pack', 'analytics'],
        ] as [$name, $surface]) {
            AcademicPmcSavedView::firstOrCreate(
                ['user_id' => $pmcHead->id, 'surface' => $surface, 'name' => $name],
                ['filters' => ['risk_band' => 'high'], 'is_default' => $surface === 'planning']
            );
        }

        AcademicPmcExportLog::firstOrCreate(
            ['user_id' => $pmcHead->id, 'report_key' => 'pmc_v004_seed_report'],
            ['filters' => ['demo' => true], 'row_count' => AcademicPmcOperatingRecord::count(), 'exported_at' => now(), 'metadata' => ['version' => 'Academics PMC OS v0.04']]
        );

        DepartmentActivityLog::firstOrCreate(
            ['action' => 'academics_pmc_os_v004_seeded', 'description' => 'Seeded Academics PMC OS v0.04 complete operating system data.'],
            [
                'department_id' => $department->id,
                'actor_user_id' => $pmcHead->id,
                'metadata' => ['version' => 'Academics PMC OS v0.04'],
            ]
        );
    }

}
