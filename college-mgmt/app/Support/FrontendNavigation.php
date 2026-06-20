<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class FrontendNavigation
{
    /**
     * Canonical frontend route manifest used by tests, browser smoke scripts, and
     * future sidebar refactors. Keep labels user-facing and route names stable.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manifest(): array
    {
        return [
            'admin' => [
                'email' => 'admin@college.com',
                'landing' => 'admin.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
                        ['label' => 'Global Search', 'route' => 'admin.search'],
                        ['label' => 'Analytics', 'route' => 'admin.analytics'],
                        ['label' => 'Institutional KPI', 'route' => 'admin.institutional-kpi'],
                    ],
                    'Governance' => [
                        ['label' => 'Academic Years', 'route' => 'admin.academic-years.index', 'active' => 'admin.academic-years.*'],
                        ['label' => 'Semesters', 'route' => 'admin.semesters.index', 'active' => 'admin.semesters.*'],
                        ['label' => 'Departments', 'route' => 'admin.departments.index', 'active' => 'admin.departments.*'],
                        ['label' => 'Subjects', 'route' => 'admin.subjects.index', 'active' => 'admin.subjects.*'],
                        ['label' => 'Classrooms', 'route' => 'admin.classrooms.index', 'active' => 'admin.classrooms.*'],
                        ['label' => 'Programs', 'route' => 'admin.programs.index', 'active' => ['admin.programs.*', 'admin.admission-config.*']],
                        ['label' => 'Batches', 'route' => 'admin.batches.index', 'active' => 'admin.batches.*'],
                    ],
                    'Timetable' => [
                        ['label' => 'Time Slots', 'route' => 'admin.timetable-slots.index', 'active' => 'admin.timetable-slots.*'],
                        ['label' => 'Weekly Timetable', 'route' => 'admin.timetable.index', 'active' => ['admin.timetable.index', 'admin.timetable.create', 'admin.timetable.edit']],
                        ['label' => 'Teacher View', 'route' => 'admin.timetable.teacher-view'],
                    ],
                    'Students / Applicants' => [
                        ['label' => 'Teachers', 'route' => 'admin.teachers.index', 'active' => 'admin.teachers.*'],
                        ['label' => 'Students', 'route' => 'admin.students.index', 'active' => 'admin.students.*'],
                        ['label' => 'Student Documents', 'route' => 'admin.document-requests.index', 'active' => 'admin.document-requests.*'],
                        ['label' => 'Applications', 'route' => 'admin.applicants.index', 'active' => 'admin.applicants.*'],
                        ['label' => 'Admissions', 'route' => 'admin.admissions.index', 'active' => 'admin.admissions.*'],
                        ['label' => 'Parents', 'route' => 'admin.parents.index', 'active' => 'admin.parents.*'],
                    ],
                    'Admission' => [
                        ['label' => 'CRM Dashboard', 'route' => 'admission.dashboard'],
                        ['label' => 'Command Center', 'route' => 'admission.command-center.index', 'active' => 'admission.command-center.*'],
                        ['label' => 'Workbench', 'route' => 'admission.workbench'],
                        ['label' => 'Calling Desk', 'route' => 'admission.calling-desk.index', 'active' => ['admission.calling-desk.*', 'admission.call-attempts.*']],
                        ['label' => 'Quick Search', 'route' => 'admission.quick-search.index', 'active' => 'admission.quick-search.*'],
                        ['label' => 'Counsellor Workspace', 'route' => 'admission.counsellor-workspace.index', 'active' => 'admission.counsellor-workspace.*'],
                        ['label' => 'Manager Workspace', 'route' => 'admission.manager-workspace.index', 'active' => 'admission.manager-workspace.*'],
                        ['label' => 'Reminders', 'route' => 'admission.reminders.index', 'active' => 'admission.reminders.*'],
                        ['label' => 'Admission Calendar', 'route' => 'admission.calendar.index', 'active' => 'admission.calendar.*'],
                        ['label' => 'Call Queue', 'route' => 'admission.call-queue.index', 'active' => 'admission.call-queue.*'],
                        ['label' => 'Pipeline', 'route' => 'admission.pipeline.index', 'active' => 'admission.pipeline.*'],
                    ],
                    'Assessments' => [
                        ['label' => 'Assessment Control', 'route' => 'admission.assessment-control-room.index', 'active' => 'admission.assessment-control-room.*'],
                        ['label' => 'Assessment Panels', 'route' => 'admission.assessment-panels.index', 'active' => ['admission.assessment-panels.*', 'admission.assessment-operations.*']],
                        ['label' => 'Assessment Scheduling', 'route' => 'admission.assessment-slots.index', 'active' => ['admission.assessment-slots.*', 'admission.gd-groups.*', 'admission.assessment-submissions.*']],
                        ['label' => 'Committee Board', 'route' => 'admission.selection-committee.index', 'active' => 'admission.selection-committee.*'],
                        ['label' => 'Walk-ins', 'route' => 'admission.walk-ins.index', 'active' => 'admission.walk-ins.*'],
                        ['label' => 'Manager Reviews', 'route' => 'admission.manager-reviews.index', 'active' => 'admission.manager-reviews.*'],
                    ],
                    'Applicants' => [
                        ['label' => 'Applicants CRM', 'route' => 'admission.applicants.index', 'active' => 'admission.applicants.*'],
                        ['label' => 'Document Queue', 'route' => 'admission.documents.queue', 'active' => 'admission.documents.*'],
                        ['label' => 'Payment Queue', 'route' => 'admission.payments.queue'],
                        ['label' => 'Merit List', 'route' => 'admission.merit-list.index', 'paramsFrom' => 'first_admission_program', 'condition' => 'admission_first_program', 'active' => 'admission.merit-list.*'],
                        ['label' => 'Offer Letters', 'route' => 'admission.offer-letters.index', 'paramsFrom' => 'first_admission_program', 'condition' => 'admission_first_program', 'active' => 'admission.offer-letters.*'],
                        ['label' => 'Enrollments', 'route' => 'admission.enrollment.index', 'active' => 'admission.enrollment.*'],
                        ['label' => 'Seat Control', 'route' => 'admission.offer-rounds.index', 'active' => ['admission.offer-rounds.*', 'admission.seat-control.*', 'admission.waitlist.*', 'admission.deferrals.*', 'admission.joining-kit.*']],
                        ['label' => 'Handoff', 'route' => 'admission.handoff.index', 'active' => 'admission.handoff.*'],
                    ],
                    'Leads' => [
                        ['label' => 'All Leads', 'route' => 'admission.leads.index', 'active' => ['admission.leads.index', 'admission.leads.show']],
                        ['label' => 'Import Leads', 'route' => 'admission.leads.import'],
                        ['label' => 'Follow-up Calendar', 'route' => 'admission.leads.follow-ups.calendar', 'active' => 'admission.leads.follow-ups.*'],
                        ['label' => 'Lead Analytics', 'route' => 'admission.leads.analytics'],
                    ],
                    'Communication' => [
                        ['label' => 'Communication Hub', 'route' => 'admission.communication.index', 'active' => 'admission.communication.*'],
                        ['label' => 'Bulk Communication', 'route' => 'admission.bulk-communication.index', 'active' => 'admission.bulk-communication.*'],
                        ['label' => 'Consent & Safety', 'route' => 'admission.communication-safety.index', 'active' => ['admission.communication-safety.*', 'admission.consent-center.*', 'admission.template-approvals.*']],
                        ['label' => 'Notices', 'route' => 'admin.notices.index', 'active' => 'admin.notices.*'],
                        ['label' => 'Bulk Mail', 'route' => 'admin.bulk-mail.index', 'active' => 'admin.bulk-mail.*'],
                        ['label' => 'Email Logs', 'route' => 'admin.email-logs.index', 'active' => 'admin.email-logs.*'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Academics Command', 'route' => 'academics.command-center.index', 'active' => ['academics.command-center.*', 'academics.attention.*']],
                        ['label' => 'Academics Governance', 'route' => 'academics.governance.index', 'active' => 'academics.*'],
                        ['label' => 'Curriculum Changes', 'route' => 'academic.curriculum-changes.index', 'active' => 'academic.curriculum-changes.*'],
                        ['label' => 'OBE Framework', 'route' => 'academic.obe.co.index', 'active' => 'academic.obe.*'],
                        ['label' => 'Faculty Roster', 'route' => 'hod.faculty.roster', 'active' => 'hod.faculty.*'],
                        ['label' => 'Leave Approvals', 'route' => 'hod.leaves', 'active' => 'hod.leaves*'],
                        ['label' => 'Leave Mgmt', 'route' => 'admin.leaves.index', 'active' => 'admin.leaves.*'],
                        ['label' => 'Faculty Workload', 'route' => 'admin.faculty.workload', 'active' => 'admin.faculty.*'],
                        ['label' => 'Attendance', 'route' => 'admin.attendance.index', 'active' => 'admin.attendance.*'],
                    ],
                    'Exams' => [
                        ['label' => 'Exams & Results', 'route' => 'admin.exams.index', 'active' => 'admin.exams.*'],
                        ['label' => 'Enrollments', 'route' => 'admin.enrollments.index', 'active' => 'admin.enrollments.*'],
                        ['label' => 'Grade Reports', 'route' => 'admin.results.index', 'active' => 'admin.results.*'],
                        ['label' => 'Transcripts', 'route' => 'academic.transcripts.index', 'active' => 'academic.transcripts.*'],
                        ['label' => 'Schedule Exam', 'route' => 'exam-cell.exams.create'],
                        ['label' => 'Hall Tickets', 'route' => 'exam-cell.hall-tickets', 'active' => 'exam-cell.hall-tickets*'],
                        ['label' => 'Marks Appeals', 'route' => 'exam-cell.marks-appeals', 'active' => 'exam-cell.marks-appeals*'],
                    ],
                    'Finance' => [
                        ['label' => 'Fees', 'route' => 'admin.fees.index', 'active' => ['admin.fees.index', 'admin.fees.show', 'admin.fees.create', 'admin.fees.edit', 'admin.fees.collect', 'admin.fees.receipt']],
                        ['label' => 'Fee Report', 'route' => 'admin.fees.report'],
                        ['label' => 'Accounts Dashboard', 'route' => 'accounts.dashboard'],
                        ['label' => 'Reconciliation', 'route' => 'accounts.reconciliation'],
                        ['label' => 'Refunds', 'route' => 'admission.refunds.index', 'active' => 'admission.refunds.*'],
                        ['label' => 'Scholarship Schemes', 'route' => 'admission.scholarship-schemes.index', 'active' => 'admission.scholarship-schemes.*'],
                        ['label' => 'Disbursements', 'route' => 'admission.scholarship-disbursements.index', 'active' => 'admission.scholarship-disbursements.*'],
                        ['label' => 'Student Applications', 'route' => 'admin.student-scholarships.index', 'active' => 'admin.student-scholarships.*'],
                    ],
                    'Operations' => [
                        ['label' => 'Library', 'route' => 'admin.library.index'],
                        ['label' => 'Hostel', 'route' => 'admin.hostel.index'],
                        ['label' => 'Transport', 'route' => 'admin.transport.index'],
                        ['label' => 'Assets', 'route' => 'admin.assets.index'],
                    ],
                    'Placement' => [
                        ['label' => 'Companies', 'route' => 'admin.companies.index', 'active' => 'admin.companies.*'],
                        ['label' => 'Drives', 'route' => 'admin.placement-drives.index', 'active' => 'admin.placement-drives.*'],
                        ['label' => 'Placement Drives', 'route' => 'cmc.drives', 'active' => 'cmc.drives*'],
                        ['label' => 'Placement Stats', 'route' => 'cmc.placement-stats'],
                        ['label' => 'Internships', 'route' => 'cmc.internships.index', 'active' => 'cmc.internships.*'],
                        ['label' => 'Alumni Database', 'route' => 'cmc.alumni.index', 'active' => 'cmc.alumni.*'],
                    ],
                    'Approvals' => [
                        ['label' => 'My Approvals', 'route' => 'approvals.inbox', 'active' => 'approvals.*'],
                    ],
                    'Settings' => [
                        ['label' => 'Role Hierarchy', 'route' => 'admin.roles.hierarchy'],
                        ['label' => 'Permission Matrix', 'route' => 'admin.roles.permissions.index'],
                        ['label' => 'Feature Matrix', 'route' => 'admin.roles.feature-access.index', 'active' => 'admin.roles.feature-access.*'],
                        ['label' => 'Role Assignments', 'route' => 'admin.users.roles.index', 'active' => 'admin.users.roles.*'],
                        ['label' => 'Legacy Assignments', 'route' => 'admin.role-assignments.index', 'active' => 'admin.role-assignments.*'],
                        ['label' => 'Org Hierarchy', 'route' => 'admin.org-hierarchy.index', 'active' => 'admin.org-hierarchy.*'],
                        ['label' => 'Department Hierarchy', 'route' => 'department-hierarchy.index', 'active' => 'department-hierarchy.*'],
                        ['label' => 'Department Governance', 'route' => 'department-governance.index', 'active' => 'department-governance.*'],
                        ['label' => 'Audit Log', 'route' => 'admin.audit.index', 'active' => 'admin.audit.*'],
                        ['label' => 'Grievances', 'route' => 'admin.grievances.index', 'active' => 'admin.grievances.*'],
                        ['label' => 'System Settings', 'route' => 'admin.settings'],
                        ['label' => 'Activity Log', 'route' => 'admin.activity-log'],
                    ],
                    'Reports' => [
                        ['label' => 'Admission Reports', 'route' => 'admission.reports.index', 'active' => 'admission.reports.*'],
                        ['label' => 'Integration Health', 'route' => 'admission.integration-health.index', 'active' => 'admission.integration-health.*'],
                        ['label' => 'Forecasting', 'route' => 'admission.forecasting.index', 'active' => 'admission.forecasting.*'],
                        ['label' => 'AICTE Report', 'route' => 'admin.aicte-report', 'active' => 'admin.aicte-report*'],
                    ],
                ],
            ],
            'admission' => [
                'email' => 'head@college.com',
                'landing' => 'admission.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'admission.dashboard'],
                        ['label' => 'Command Center', 'route' => 'admission.command-center.index'],
                        ['label' => 'Workbench', 'route' => 'admission.workbench'],
                    ],
                    'Daily Work' => [
                        ['label' => 'Calling Desk', 'route' => 'admission.calling-desk.index', 'active' => ['admission.calling-desk.*', 'admission.call-attempts.*']],
                        ['label' => 'Counsellor Desk', 'route' => 'admission.counsellor-desk.index'],
                        ['label' => 'Quick Search', 'route' => 'admission.quick-search.index'],
                    ],
                    'Applicants' => [
                        ['label' => 'Applicants', 'route' => 'admission.applicants.index'],
                        ['label' => 'Document Queue', 'route' => 'admission.documents.queue', 'active' => 'admission.documents.*'],
                        ['label' => 'Payment Queue', 'route' => 'admission.payments.queue'],
                    ],
                    'Assessments' => [
                        ['label' => 'Assessment Control', 'route' => 'admission.assessment-control-room.index'],
                        ['label' => 'Assessment Scheduling', 'route' => 'admission.assessment-slots.index', 'active' => ['admission.assessment-slots.*', 'admission.gd-groups.*', 'admission.assessment-submissions.*']],
                        ['label' => 'Committee Board', 'route' => 'admission.selection-committee.index'],
                    ],
                    'Process' => [
                        ['label' => 'Merit List', 'route' => 'admission.merit-list.index', 'paramsFrom' => 'first_admission_program', 'condition' => 'admission_first_program', 'active' => 'admission.merit-list.*'],
                        ['label' => 'Offer Letters', 'route' => 'admission.offer-letters.index', 'paramsFrom' => 'first_admission_program', 'condition' => 'admission_first_program', 'active' => 'admission.offer-letters.*'],
                        ['label' => 'Enrollments', 'route' => 'admission.enrollment.index', 'active' => 'admission.enrollment.*'],
                        ['label' => 'Seat Control', 'route' => 'admission.offer-rounds.index', 'active' => ['admission.offer-rounds.*', 'admission.seat-control.*', 'admission.waitlist.*', 'admission.deferrals.*', 'admission.joining-kit.*']],
                        ['label' => 'Handoff', 'route' => 'admission.handoff.index', 'active' => 'admission.handoff.*'],
                        ['label' => 'Sessions', 'route' => 'admission.sessions.index', 'active' => 'admission.sessions.*'],
                    ],
                    'Leads' => [
                        ['label' => 'All Leads', 'route' => 'admission.leads.index', 'active' => ['admission.leads.index', 'admission.leads.show']],
                        ['label' => 'Import Leads', 'route' => 'admission.leads.import'],
                        ['label' => 'Follow-up Calendar', 'route' => 'admission.leads.follow-ups.calendar', 'active' => 'admission.leads.follow-ups.*'],
                        ['label' => 'Lead Analytics', 'route' => 'admission.leads.analytics'],
                    ],
                    'Reports' => [
                        ['label' => 'Admission Reports', 'route' => 'admission.reports.index', 'active' => 'admission.reports.*'],
                        ['label' => 'Bulk Communication', 'route' => 'admission.bulk-communication.index', 'active' => 'admission.bulk-communication.*'],
                        ['label' => 'Consent & Safety', 'route' => 'admission.communication-safety.index', 'active' => ['admission.communication-safety.*', 'admission.consent-center.*', 'admission.template-approvals.*']],
                        ['label' => 'Integration Health', 'route' => 'admission.integration-health.index', 'active' => 'admission.integration-health.*'],
                        ['label' => 'Refunds', 'route' => 'admission.refunds.index', 'active' => 'admission.refunds.*'],
                    ],
                ],
            ],
            'dean' => [
                'email' => 'dean@college.com',
                'landing' => 'academics.dean-os.index',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dean OS', 'route' => 'academics.dean-os.index'],
                        ['label' => 'Legacy Dashboard', 'route' => 'dean.dashboard'],
                        ['label' => 'Academics Command', 'route' => 'academics.command-center.index', 'active' => ['academics.command-center.*', 'academics.attention.*']],
                    ],
                    'Governance' => [
                        ['label' => 'Academics Governance', 'route' => 'academics.governance.index', 'active' => 'academics.governance.*'],
                        ['label' => 'Hierarchy', 'route' => 'academics.hierarchy.index', 'active' => 'academics.hierarchy.*'],
                        ['label' => 'Permission Matrix', 'route' => 'academics.permission-matrix.index', 'active' => 'academics.permission-matrix.*'],
                        ['label' => 'Planning', 'route' => 'academics.dean-os.planning.index', 'active' => 'academics.dean-os.planning.*'],
                        ['label' => 'Reviews', 'route' => 'academics.dean-os.reviews', 'active' => 'academics.dean-os.reviews'],
                        ['label' => 'Approval Cockpit', 'route' => 'academics.dean-os.approval-cockpit.index', 'active' => 'academics.dean-os.approval-cockpit.*'],
                    ],
                    'Operations' => [
                        ['label' => 'PMC Operating', 'route' => 'academics.pmc.index', 'active' => 'academics.pmc.*'],
                        ['label' => 'PMC Command', 'route' => 'academics.pmc.command'],
                        ['label' => 'CoE Operating', 'route' => 'academics.coe.index', 'active' => 'academics.coe.*'],
                        ['label' => 'IQAC Operating', 'route' => 'academics.iqac.index', 'active' => 'academics.iqac.*'],
                        ['label' => 'Program Leadership', 'route' => 'academics.program-leadership.index', 'active' => 'academics.program-leadership.*'],
                        ['label' => 'Course Delivery', 'route' => 'academics.course-delivery.index', 'active' => 'academics.course-delivery.*'],
                        ['label' => 'Hostel', 'route' => 'admin.hostel.index', 'active' => 'admin.hostel.*'],
                        ['label' => 'Library', 'route' => 'admin.library.index', 'active' => 'admin.library.*'],
                    ],
                    'Students' => [
                        ['label' => 'Academics Overview', 'route' => 'dean.academics'],
                        ['label' => 'Programs', 'route' => 'dean.programs'],
                        ['label' => 'Students', 'route' => 'dean.students'],
                        ['label' => 'Attendance', 'route' => 'dean.attendance'],
                    ],
                    'Curriculum' => [
                        ['label' => 'Curriculum Changes', 'route' => 'academic.curriculum-changes.index', 'active' => 'academic.curriculum-changes.*'],
                        ['label' => 'OBE Framework', 'route' => 'academic.obe.co.index', 'active' => 'academic.obe.*'],
                    ],
                    'Exams' => [
                        ['label' => 'Exams', 'route' => 'exam-cell.exams'],
                        ['label' => 'Transcripts', 'route' => 'academic.transcripts.index', 'active' => 'academic.transcripts.*'],
                        ['label' => 'Hall Tickets', 'route' => 'exam-cell.hall-tickets', 'active' => 'exam-cell.hall-tickets*'],
                        ['label' => 'Marks Appeals', 'route' => 'exam-cell.marks-appeals', 'active' => 'exam-cell.marks-appeals*'],
                    ],
                    'Approvals' => [
                        ['label' => 'Approvals', 'route' => 'dean.approvals'],
                    ],
                    'Reports' => [
                        ['label' => 'Dean Reports', 'route' => 'academics.dean-os.reports'],
                        ['label' => 'Program Risk', 'route' => 'academics.dean-os.program-risk'],
                        ['label' => 'Analytics', 'route' => 'academics.dean-os.analytics.index'],
                        ['label' => 'Institution Analytics', 'route' => 'admin.analytics'],
                        ['label' => 'AICTE Report', 'route' => 'admin.aicte-report', 'active' => 'admin.aicte-report*'],
                        ['label' => 'Placement Stats', 'route' => 'cmc.placement-stats'],
                        ['label' => 'Policy Audit', 'route' => 'academics.dean-os.policy-audit.index'],
                    ],
                ],
            ],
            'hod' => [
                'email' => 'hod@college.com',
                'landing' => 'hod.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'hod.dashboard'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Faculty Roster', 'route' => 'hod.faculty.roster', 'active' => ['hod.faculty.roster', 'hod.faculty.roster.alias']],
                        ['label' => 'Faculty Workload', 'route' => 'hod.faculty.workload'],
                    ],
                    'Students' => [
                        ['label' => 'Leave Approvals', 'route' => 'hod.leaves', 'active' => 'hod.leaves*'],
                        ['label' => 'Dept Performance', 'route' => 'hod.department-performance'],
                    ],
                    'Support' => [
                        ['label' => 'Student Grievances', 'route' => 'hod.grievances.index', 'active' => 'hod.grievances.*'],
                    ],
                    'Approvals' => [
                        ['label' => 'Approvals', 'route' => 'hod.approvals'],
                    ],
                ],
            ],
            'director' => [
                'email' => 'director@college.com',
                'landing' => 'director.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'director.dashboard'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Programs', 'route' => 'director.programs'],
                        ['label' => 'Reports', 'route' => 'director.reports'],
                    ],
                    'Reports' => [
                        ['label' => 'Analytics', 'route' => 'admin.analytics'],
                        ['label' => 'Institutional KPI', 'route' => 'admin.institutional-kpi'],
                        ['label' => 'AICTE Report', 'route' => 'admin.aicte-report', 'active' => 'admin.aicte-report*'],
                    ],
                ],
            ],
            'pmc' => [
                'email' => 'chair@college.com',
                'landing' => 'academics.pmc.command',
                'groups' => [
                    'Command' => [
                        ['label' => 'PMC Command', 'route' => 'academics.pmc.command'],
                        ['label' => 'Legacy Dashboard', 'route' => 'chair.dashboard'],
                        ['label' => 'PMC Workspace', 'route' => 'academics.workspaces.show', 'params' => ['pmc'], 'active' => ['academics.workspaces.*', 'academics.attention.*']],
                        ['label' => 'PMC Operating', 'route' => 'academics.pmc.index', 'active' => 'academics.pmc.*'],
                        ['label' => 'Program Leadership', 'route' => 'academics.program-leadership.index', 'active' => 'academics.program-leadership.*'],
                        ['label' => 'Academics Governance', 'route' => 'academics.governance.index'],
                    ],
                    'Planning' => [
                        ['label' => 'Planning', 'route' => 'academics.pmc.planning.index'],
                        ['label' => 'Semester Readiness', 'route' => 'academics.pmc.semester-readiness.index'],
                    ],
                    'Curriculum' => [
                        ['label' => 'Curriculum', 'route' => 'chair.curriculum.index', 'active' => 'chair.curriculum.*'],
                        ['label' => 'Curriculum Governance', 'route' => 'academics.pmc.curriculum-governance.index'],
                    ],
                    'Timetable' => [
                        ['label' => 'Course Allocation', 'route' => 'academics.pmc.course-allocation.index'],
                        ['label' => 'Section Builder', 'route' => 'academics.pmc.course-groups.index'],
                        ['label' => 'Timetable Planner', 'route' => 'academics.pmc.timetable-planner.index'],
                        ['label' => 'Timetable Builder', 'route' => 'chair.timetable.builder', 'active' => 'chair.timetable.*'],
                    ],
                    'Students' => [
                        ['label' => 'At-Risk Students', 'route' => 'chair.students.at-risk'],
                        ['label' => 'Leave Approvals', 'route' => 'chair.students.leaves'],
                        ['label' => 'Condonations', 'route' => 'chair.students.condonations'],
                        ['label' => 'Student Success', 'route' => 'academics.pmc.student-success-v004.index'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Faculty Workload', 'route' => 'chair.faculty.workload', 'active' => 'chair.faculty.*'],
                        ['label' => 'Faculty Allocation', 'route' => 'academics.pmc.faculty-allocation-v004.index'],
                        ['label' => 'Course Delivery', 'route' => 'academics.pmc.course-delivery.index'],
                    ],
                    'Approvals' => [
                        ['label' => 'Approvals', 'route' => 'chair.approvals'],
                        ['label' => 'Approval Cockpit', 'route' => 'academics.pmc.approvals.index'],
                    ],
                    'Reports' => [
                        ['label' => 'Subject Performance', 'route' => 'chair.reports.subject-performance', 'active' => 'chair.reports.*'],
                        ['label' => 'Analytics', 'route' => 'academics.pmc.analytics.index'],
                        ['label' => 'Policy Audit', 'route' => 'academics.pmc.policy-audit.index'],
                    ],
                ],
            ],
            'coe' => [
                'email' => 'exam@college.com',
                'landing' => 'academics.coe.index',
                'groups' => [
                    'Command' => [
                        ['label' => 'CoE OS', 'route' => 'academics.coe.index'],
                        ['label' => 'Exam Cell Dashboard', 'route' => 'exam-cell.dashboard'],
                        ['label' => 'CoE Workspace', 'route' => 'academics.workspaces.show', 'params' => ['coe'], 'active' => ['academics.workspaces.*', 'academics.attention.*']],
                        ['label' => 'Academics Governance', 'route' => 'academics.governance.index'],
                    ],
                    'Daily Work' => [
                        ['label' => 'Exam Readiness', 'route' => 'academics.coe.exam-readiness'],
                        ['label' => 'Marks & Results', 'route' => 'academics.coe.marks-results'],
                        ['label' => 'Hall Tickets', 'route' => 'academics.coe.hall-ticket-readiness'],
                    ],
                    'Exams' => [
                        ['label' => 'All Exams', 'route' => 'exam-cell.exams', 'active' => 'exam-cell.exams'],
                        ['label' => 'Schedule Exam', 'route' => 'exam-cell.exams.create'],
                        ['label' => 'Results', 'route' => 'exam-cell.results'],
                    ],
                    'Reports' => [
                        ['label' => 'Transcripts', 'route' => 'academics.coe.transcripts'],
                        ['label' => 'Reports', 'route' => 'academics.coe.reports'],
                        ['label' => 'Legacy Transcripts', 'route' => 'academic.transcripts.index', 'active' => 'academic.transcripts.*'],
                    ],
                    'Governance' => [
                        ['label' => 'Hall Ticket Admin', 'route' => 'exam-cell.hall-tickets', 'active' => 'exam-cell.hall-tickets*'],
                        ['label' => 'Marks Appeals', 'route' => 'exam-cell.marks-appeals', 'active' => 'exam-cell.marks-appeals*'],
                        ['label' => 'Anomaly Log', 'route' => 'exam-cell.anomalies.index', 'active' => 'exam-cell.anomalies.*'],
                    ],
                ],
            ],
            'iqac' => [
                'email' => 'iqac.head@college.com',
                'landing' => 'academics.iqac.index',
                'groups' => [
                    'Command' => [
                        ['label' => 'IQAC OS', 'route' => 'academics.iqac.index'],
                        ['label' => 'IQAC Workspace', 'route' => 'academics.workspaces.show', 'params' => ['iqac'], 'active' => ['academics.workspaces.*', 'academics.attention.*']],
                        ['label' => 'Academics Governance', 'route' => 'academics.governance.index'],
                        ['label' => 'OBE Readiness', 'route' => 'academics.iqac.obe-readiness'],
                    ],
                    'Quality' => [
                        ['label' => 'OBE Framework', 'route' => 'academic.obe.co.index', 'active' => 'academic.obe.*'],
                        ['label' => 'Attainment', 'route' => 'academics.iqac.attainment-monitoring'],
                        ['label' => 'Feedback Quality', 'route' => 'academics.iqac.feedback-quality'],
                        ['label' => 'Audit Compliance', 'route' => 'academics.iqac.audit-compliance'],
                    ],
                    'Reports' => [
                        ['label' => 'Reports', 'route' => 'academics.iqac.reports'],
                    ],
                ],
            ],
            'program_leadership' => [
                'email' => 'chair@college.com',
                'landing' => 'academics.program-leadership.index',
                'groups' => [
                    'Command' => [
                        ['label' => 'Program Workspace', 'route' => 'academics.workspaces.show', 'params' => ['program'], 'active' => ['academics.workspaces.*', 'academics.attention.*']],
                        ['label' => 'Program Leadership', 'route' => 'academics.program-leadership.index'],
                        ['label' => 'Portfolio', 'route' => 'academics.program-leadership.portfolio'],
                    ],
                    'Daily Work' => [
                        ['label' => 'Student Success', 'route' => 'academics.program-leadership.student-success'],
                        ['label' => 'Course Delivery', 'route' => 'academics.program-leadership.course-delivery'],
                        ['label' => 'Student Monitoring', 'route' => 'chair.students.at-risk', 'active' => 'chair.students.*'],
                    ],
                    'Reports' => [
                        ['label' => 'Quality Signals', 'route' => 'academics.program-leadership.quality-signals'],
                        ['label' => 'Reports', 'route' => 'academics.program-leadership.reports'],
                        ['label' => 'Program Reports', 'route' => 'chair.reports.subject-performance', 'active' => 'chair.reports.*'],
                    ],
                ],
            ],
            'course_delivery' => [
                'email' => 'dean@college.com',
                'landing' => 'academics.course-delivery.index',
                'groups' => [
                    'Command' => [
                        ['label' => 'Course Delivery OS', 'route' => 'academics.course-delivery.index'],
                    ],
                    'Daily Work' => [
                        ['label' => 'Session Delivery', 'route' => 'academics.course-delivery.session-delivery'],
                        ['label' => 'Attendance Interventions', 'route' => 'academics.course-delivery.attendance-interventions'],
                        ['label' => 'Mentor Actions', 'route' => 'academics.course-delivery.mentor-actions'],
                    ],
                    'Reports' => [
                        ['label' => 'Course Engagement', 'route' => 'academics.course-delivery.course-engagement'],
                        ['label' => 'Reports', 'route' => 'academics.course-delivery.reports'],
                    ],
                ],
            ],
            'teacher' => [
                'email' => 'anjali@demo.edu',
                'landing' => 'teacher.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'teacher.dashboard'],
                    ],
                    'Daily Work' => [
                        ['label' => 'My Timetable', 'route' => 'teacher.timetable.index', 'active' => 'teacher.timetable.*'],
                        ['label' => 'Mark Attendance', 'route' => 'teacher.attendance.mark', 'active' => 'teacher.attendance.*'],
                        ['label' => 'Enter Marks', 'route' => 'teacher.exams.index', 'active' => 'teacher.exams.*'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Study Materials', 'route' => 'teacher.materials.index', 'active' => 'teacher.materials.*'],
                        ['label' => 'Assignments', 'route' => 'teacher.assignments.index', 'active' => 'teacher.assignments.*'],
                        ['label' => 'Announcements', 'route' => 'teacher.announcements.index', 'active' => 'teacher.announcements.*'],
                    ],
                    'Students' => [
                        ['label' => 'My Students', 'route' => 'teacher.students.index', 'active' => 'teacher.students.*'],
                        ['label' => 'My Mentees', 'route' => 'teacher.mentor.index', 'active' => 'teacher.mentor.*'],
                    ],
                    'Settings' => [
                        ['label' => 'My Feedback', 'route' => 'teacher.feedback.index', 'active' => 'teacher.feedback.*'],
                        ['label' => 'Leave', 'route' => 'teacher.leaves.index', 'active' => 'teacher.leaves.*'],
                        ['label' => 'My Profile', 'route' => 'teacher.profile'],
                    ],
                ],
            ],
            'student' => [
                'email' => 'arjun.k@demo.edu',
                'landing' => 'student.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'student.dashboard'],
                    ],
                    'Daily Work' => [
                        ['label' => 'My Timetable', 'route' => 'student.timetable'],
                        ['label' => 'Attendance', 'route' => 'student.attendance'],
                        ['label' => 'Results', 'route' => 'student.results'],
                    ],
                    'Academics / Delivery' => [
                        ['label' => 'Admit Cards', 'route' => 'student.admit-cards.index'],
                        ['label' => 'Subject Registration', 'route' => 'student.subjects.index'],
                        ['label' => 'Academic Calendar', 'route' => 'student.calendar.index'],
                        ['label' => 'Leave Applications', 'route' => 'student.leave.index'],
                        ['label' => 'Official Transcript', 'route' => 'student.transcript.download', 'condition' => 'student_has_issued_transcript'],
                        ['label' => 'Exam Registration', 'route' => 'student.exam-reg.index'],
                        ['label' => 'Marks Appeals', 'route' => 'student.appeals.index'],
                        ['label' => 'My Courses', 'route' => 'student.courses.index'],
                        ['label' => 'Assignments', 'route' => 'student.assignments.index'],
                        ['label' => 'Quizzes', 'route' => 'student.quizzes.index'],
                    ],
                    'Finance' => [
                        ['label' => 'Fee Status', 'route' => 'student.fees'],
                        ['label' => 'Submit Payment', 'route' => 'student.fee-payment.index'],
                    ],
                    'Career' => [
                        ['label' => 'Placements', 'route' => 'student.placements'],
                        ['label' => 'Scholarships', 'route' => 'student.scholarships.index'],
                        ['label' => 'My Resume', 'route' => 'student.resume.index'],
                        ['label' => 'Career Events', 'route' => 'student.career-events.index'],
                        ['label' => 'My Internships', 'route' => 'student.internships.index'],
                        ['label' => 'Alumni Network', 'route' => 'student.alumni.index'],
                    ],
                    'Support' => [
                        ['label' => 'Notices', 'route' => 'student.notices'],
                        ['label' => 'Library', 'route' => 'student.library.index'],
                        ['label' => 'Grievances', 'route' => 'student.grievances.index'],
                        ['label' => 'My Mentor', 'route' => 'student.mentor.index'],
                        ['label' => 'Course Feedback', 'route' => 'student.feedback.index'],
                        ['label' => 'Attendance Condonation', 'route' => 'student.condonation.index'],
                        ['label' => 'Document Requests', 'route' => 'student.documents.index'],
                        ['label' => 'Transport', 'route' => 'student.transport.index'],
                        ['label' => 'Outpass Request', 'route' => 'student.hostel.outpass'],
                        ['label' => 'Hostel Complaints', 'route' => 'student.hostel.complaints.index'],
                    ],
                    'Settings' => [
                        ['label' => 'Academic Summary', 'route' => 'student.summary.index'],
                        ['label' => 'Promotion Status', 'route' => 'student.promotion.index'],
                        ['label' => 'My Profile', 'route' => 'student.profile'],
                        ['label' => 'Notifications', 'route' => 'student.notifications.edit'],
                    ],
                ],
            ],
            'parent' => [
                'email' => 'parent@demo.edu',
                'landing' => 'parent.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'parent.dashboard'],
                    ],
                    'Students' => [
                        ['label' => 'My Children', 'route' => 'parent.children'],
                    ],
                    'Communication' => [
                        ['label' => 'Notices', 'route' => 'parent.notices'],
                    ],
                ],
            ],
            'applicant' => [
                'email' => 'priya.sharma@applicant.demo',
                'landing' => 'applicant.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'applicant.dashboard'],
                        ['label' => 'Checklist', 'route' => 'applicant.checklist'],
                    ],
                    'Daily Work' => [
                        ['label' => 'Application', 'route' => 'applicant.application.show'],
                        ['label' => 'Documents', 'route' => 'applicant.documents.index'],
                        ['label' => 'Fees', 'route' => 'applicant.fees.index'],
                    ],
                    'Track' => [
                        ['label' => 'Status Tracker', 'route' => 'applicant.status'],
                    ],
                ],
            ],
            'accounts' => [
                'email' => 'accounts@college.com',
                'landing' => 'accounts.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'accounts.dashboard'],
                    ],
                    'Finance' => [
                        ['label' => 'Fee Collections', 'route' => 'accounts.fee-collections'],
                        ['label' => 'Admission Payments', 'route' => 'accounts.admission-payments'],
                        ['label' => 'Outstanding', 'route' => 'accounts.outstanding'],
                        ['label' => 'Reconciliation', 'route' => 'accounts.reconciliation'],
                        ['label' => 'Reports', 'route' => 'accounts.reports'],
                    ],
                ],
            ],
            'cmc' => [
                'email' => 'cmc@college.com',
                'landing' => 'cmc.dashboard',
                'groups' => [
                    'Command' => [
                        ['label' => 'Dashboard', 'route' => 'cmc.dashboard'],
                    ],
                    'Placement' => [
                        ['label' => 'Placement Drives', 'route' => 'cmc.drives', 'active' => 'cmc.drives*'],
                        ['label' => 'Companies', 'route' => 'cmc.companies', 'active' => 'cmc.companies*'],
                        ['label' => 'Career Events', 'route' => 'cmc.events', 'active' => 'cmc.events*'],
                        ['label' => 'Placement Stats', 'route' => 'cmc.placement-stats'],
                    ],
                    'Students' => [
                        ['label' => 'Internships', 'route' => 'cmc.internships.index', 'active' => 'cmc.internships.*'],
                        ['label' => 'Alumni Database', 'route' => 'cmc.alumni.index', 'active' => 'cmc.alumni.*'],
                    ],
                    'Reports' => [
                        ['label' => 'Analytics', 'route' => 'cmc.analytics'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{role:string, group:string, label:string, route:string}>
     */
    public static function flatRoutes(): Collection
    {
        return collect(self::manifest())->flatMap(function (array $roleConfig, string $role) {
            return collect($roleConfig['groups'])->flatMap(function (array $items, string $group) use ($role) {
                return collect($items)->map(fn (array $item) => [
                    'role' => $role,
                    'group' => $group,
                    'label' => $item['label'],
                    'route' => $item['route'],
                ]);
            })->prepend([
                'role' => $role,
                'group' => 'Landing',
                'label' => 'Default landing',
                'route' => $roleConfig['landing'],
            ]);
        })->values();
    }

    public static function routeExists(string $routeName): bool
    {
        return Route::has($routeName);
    }
}
