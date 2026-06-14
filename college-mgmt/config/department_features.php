<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Department Feature Registry
    |--------------------------------------------------------------------------
    |
    | Registered features appear automatically in Department Governance and can
    | be toggled by admins or department heads/owners. Route middleware and
    | services should reference these keys instead of hard-coded one-off flags.
    |
    */

    'departments' => [
        'ADM' => [
            'admission.workbench' => [
                'name' => 'Admission Workbench',
                'category' => 'Staff Operations',
                'description' => 'Priority queues for leads, documents, payments, sessions, offers, and enrollment readiness.',
                'default_enabled' => true,
            ],
            'admission.process_templates' => [
                'name' => 'Admission Process Templates',
                'category' => 'Configuration',
                'description' => 'Program and batch specific stages, documents, fees, selection steps, offers, and waitlist rules.',
                'default_enabled' => true,
            ],
            'admission.assignment' => [
                'name' => 'Lead And Applicant Assignment',
                'category' => 'Staff Operations',
                'description' => 'Hierarchy-aware assignment and reassignment of leads and applicants.',
                'default_enabled' => true,
            ],
            'admission.applicant_checklist' => [
                'name' => 'Applicant Guided Checklist',
                'category' => 'Applicant Experience',
                'description' => 'Applicant-facing readiness checklist with profile, document, fee, selection, offer, and enrollment blockers.',
                'default_enabled' => true,
            ],
            'admission.gateway_payments' => [
                'name' => 'Gateway Payments',
                'category' => 'Payments',
                'description' => 'Online admission payment order creation through the configured gateway provider.',
                'default_enabled' => true,
            ],
            'admission.document_verification' => [
                'name' => 'Document Verification Queue',
                'category' => 'Staff Operations',
                'description' => 'Staff document review, rejection reason capture, and verification workflows.',
                'default_enabled' => true,
            ],
            'admission.payment_verification' => [
                'name' => 'Manual Payment Verification Queue',
                'category' => 'Payments',
                'description' => 'Manual proof review and accounts/admission payment verification handoff.',
                'default_enabled' => true,
            ],
            'admission.reporting_exports' => [
                'name' => 'Admission Reports And Exports',
                'category' => 'Reporting',
                'description' => 'Admission funnel exports, filtered reports, and CSV downloads.',
                'default_enabled' => true,
            ],
            'admission.command_center' => [
                'name' => 'Admission Command Center',
                'category' => 'Staff Operations',
                'description' => 'Role-specific operating center for attention queues, KPIs, calls, forecasts, and quick actions.',
                'default_enabled' => true,
            ],
            'admission.communication_hub' => [
                'name' => 'Communication Hub',
                'category' => 'Communication',
                'description' => 'Template-based email, internal, mock SMS, and mock WhatsApp communication logs.',
                'default_enabled' => true,
            ],
            'admission.telecaller_queue' => [
                'name' => 'Telecaller Call Queue',
                'category' => 'Staff Operations',
                'description' => 'Prioritized call queues, dispositions, call logs, and productivity KPIs.',
                'default_enabled' => true,
            ],
            'admission.pipeline_boards' => [
                'name' => 'Admission Pipeline Boards',
                'category' => 'Staff Operations',
                'description' => 'Configurable lead and applicant pipeline boards with service-backed status movement.',
                'default_enabled' => true,
            ],
            'admission.automation_engine' => [
                'name' => 'Admission Automation Engine',
                'category' => 'Automation',
                'description' => 'Rule-based triggers, conditions, actions, execution logs, and idempotency.',
                'default_enabled' => true,
            ],
            'admission.partner_channels' => [
                'name' => 'Partner And Channel Admissions',
                'category' => 'Channels',
                'description' => 'Partner profiles, approved lead submission, and partner performance tracking.',
                'default_enabled' => true,
            ],
            'admission.forecasting' => [
                'name' => 'Admission Forecasting',
                'category' => 'Reporting',
                'description' => 'Deterministic forecast snapshots for seats, leads, applications, offers, enrollments, and gaps.',
                'default_enabled' => true,
            ],
            'admission.data_quality' => [
                'name' => 'Admission Data Quality',
                'category' => 'Compliance',
                'description' => 'Duplicate, missing data, stale owner, and enrollment blocker quality flags.',
                'default_enabled' => true,
            ],
            'admission.approvals' => [
                'name' => 'Admission Approvals',
                'category' => 'Compliance',
                'description' => 'Approval requests and audit trail for sensitive admission actions.',
                'default_enabled' => true,
            ],
            'admission.v0031_daily_operations' => [
                'name' => 'Admission Daily Operations',
                'category' => 'Staff Operations',
                'description' => 'Counsellor and manager workspaces for follow-ups, reminders, escalations, and daily execution.',
                'default_enabled' => true,
            ],
            'admission.reminder_cadences' => [
                'name' => 'Reminder And Cadence Engine',
                'category' => 'Communication',
                'description' => 'Scheduled, repeatable, pausable, and escalatable reminders backed by communication logs.',
                'default_enabled' => true,
            ],
            'admission.assessment_operations' => [
                'name' => 'Assessment Operations',
                'category' => 'Selection',
                'description' => 'Assessment panels, evaluator assignment, pending score queues, score locks, and overrides.',
                'default_enabled' => true,
            ],
            'admission.walk_ins' => [
                'name' => 'Walk-ins And Campus Visits',
                'category' => 'Staff Operations',
                'description' => 'Walk-in visitor capture, counsellor assignment, follow-up, conversion, and reporting.',
                'default_enabled' => true,
            ],
            'admission.manager_reviews' => [
                'name' => 'Manager Reviews',
                'category' => 'Compliance',
                'description' => 'Review queues for call quality, lost leads, duplicate decisions, and score overrides.',
                'default_enabled' => true,
            ],
        ],
        'ACAD' => [
            'academic.command_center' => [
                'name' => 'Academics Command Center',
                'category' => 'Operations',
                'description' => 'Role-specific command center, attention queues, branch summaries, and academic activity monitoring.',
                'default_enabled' => true,
            ],
            'academic.pmc_operating_system' => [
                'name' => 'PMC Operating System',
                'category' => 'Operations',
                'description' => 'Curriculum readiness, faculty allocation, timetable readiness, student monitoring, and PMC reports.',
                'default_enabled' => true,
            ],
            'academic.coe_operating_system' => [
                'name' => 'CoE Operating System',
                'category' => 'Operations',
                'description' => 'Exam readiness, marks/results control, hall tickets, transcripts, appeals, and anomalies.',
                'default_enabled' => true,
            ],
            'academic.iqac_operating_system' => [
                'name' => 'IQAC Operating System',
                'category' => 'Quality',
                'description' => 'OBE readiness, attainment monitoring, feedback quality, surveys, audit trails, and compliance reports.',
                'default_enabled' => true,
            ],
            'academic.program_leadership_os' => [
                'name' => 'Program Leadership OS',
                'category' => 'Operations',
                'description' => 'Program portfolio, course delivery, student success, quality signals, and leadership reports.',
                'default_enabled' => true,
            ],
            'academic.course_delivery_os' => [
                'name' => 'Course Delivery OS',
                'category' => 'Operations',
                'description' => 'Faculty course load, session delivery, attendance interventions, course engagement, and mentor actions.',
                'default_enabled' => true,
            ],
            'academic.governance' => [
                'name' => 'Academics Governance',
                'category' => 'Governance',
                'description' => 'Flexible hierarchy, branch ownership, academic scopes, permission matrix, and governance audit trail.',
                'default_enabled' => true,
            ],
            'academic.dashboard' => [
                'name' => 'Academic Dashboard',
                'category' => 'Oversight',
                'description' => 'Academic priorities, approvals, curriculum readiness, and operational monitoring.',
                'default_enabled' => true,
            ],
            'academic.curriculum' => [
                'name' => 'Curriculum And Program Setup',
                'category' => 'Configuration',
                'description' => 'Program structures, subjects, terms, curriculum mapping, and academic setup.',
                'default_enabled' => true,
            ],
            'academic.timetable' => [
                'name' => 'Timetable Operations',
                'category' => 'Operations',
                'description' => 'Timetable building, publication, faculty workload, and schedule exports.',
                'default_enabled' => true,
            ],
            'academic.approvals' => [
                'name' => 'Academic Approvals',
                'category' => 'Workflow',
                'description' => 'Program chair, HOD, Dean, and academic approval queues.',
                'default_enabled' => true,
            ],
            'academic.reports' => [
                'name' => 'Academic Reports',
                'category' => 'Reporting',
                'description' => 'Attendance, performance, workload, at-risk student, and curriculum reports.',
                'default_enabled' => true,
            ],
        ],
        'ACC' => [
            'accounts.dashboard' => [
                'name' => 'Finance Dashboard',
                'category' => 'Oversight',
                'description' => 'Billed, collected, outstanding, overdue, and reconciliation priorities.',
                'default_enabled' => true,
            ],
            'accounts.fee_collection' => [
                'name' => 'Fee Collection',
                'category' => 'Operations',
                'description' => 'Student fee collection, demand tracking, penalties, and payment history.',
                'default_enabled' => true,
            ],
            'accounts.reconciliation' => [
                'name' => 'Reconciliation',
                'category' => 'Operations',
                'description' => 'Admission payments, manual proof handoff, gateway reconciliation, and finance review.',
                'default_enabled' => true,
            ],
            'accounts.scholarships' => [
                'name' => 'Scholarship Finance',
                'category' => 'Operations',
                'description' => 'Scholarship approval, disbursement, and finance impact tracking.',
                'default_enabled' => true,
            ],
            'accounts.reports_exports' => [
                'name' => 'Finance Reports And Exports',
                'category' => 'Reporting',
                'description' => 'Collection reports, outstanding exports, dues summaries, and finance analytics.',
                'default_enabled' => true,
            ],
        ],
        'EXAM' => [
            'exam.dashboard' => [
                'name' => 'Exam Cell Dashboard',
                'category' => 'Oversight',
                'description' => 'Exam priorities, result-entry status, appeals, anomalies, and hall ticket readiness.',
                'default_enabled' => true,
            ],
            'exam.scheduling' => [
                'name' => 'Exam Scheduling',
                'category' => 'Operations',
                'description' => 'Exam creation, dates, rooms, schedules, and publication.',
                'default_enabled' => true,
            ],
            'exam.marks_results' => [
                'name' => 'Marks And Results',
                'category' => 'Operations',
                'description' => 'Marks entry, result calculation, result publishing, and result corrections.',
                'default_enabled' => true,
            ],
            'exam.hall_tickets' => [
                'name' => 'Hall Tickets',
                'category' => 'Student Services',
                'description' => 'Hall ticket generation, eligibility, and student-facing exam documents.',
                'default_enabled' => true,
            ],
            'exam.appeals_anomalies' => [
                'name' => 'Appeals And Anomalies',
                'category' => 'Workflow',
                'description' => 'Appeal review, anomaly tracking, and exam-cell resolution workflows.',
                'default_enabled' => true,
            ],
        ],
        'CMC' => [
            'cmc.dashboard' => [
                'name' => 'CMC Dashboard',
                'category' => 'Oversight',
                'description' => 'Placement, internship, recruiter, and alumni priorities.',
                'default_enabled' => true,
            ],
            'cmc.companies_drives' => [
                'name' => 'Companies And Drives',
                'category' => 'Operations',
                'description' => 'Company onboarding, placement drives, eligibility, applications, and selection tracking.',
                'default_enabled' => true,
            ],
            'cmc.internships' => [
                'name' => 'Internships',
                'category' => 'Operations',
                'description' => 'Internship registration, monitoring, completion, feedback, and outcomes.',
                'default_enabled' => true,
            ],
            'cmc.alumni' => [
                'name' => 'Alumni Network',
                'category' => 'Engagement',
                'description' => 'Alumni verification, profiles, engagement, employment outcomes, and networking.',
                'default_enabled' => true,
            ],
            'cmc.analytics_exports' => [
                'name' => 'Placement Analytics And Exports',
                'category' => 'Reporting',
                'description' => 'Placement rate, package, recruiter, internship, alumni, and source reports.',
                'default_enabled' => true,
            ],
        ],
        'HOSTEL' => [
            'hostel.rooms_allocations' => [
                'name' => 'Rooms And Allocations',
                'category' => 'Operations',
                'description' => 'Room setup, bed allocation, transfers, vacancy, and occupancy tracking.',
                'default_enabled' => true,
            ],
            'hostel.complaints' => [
                'name' => 'Hostel Complaints',
                'category' => 'Student Services',
                'description' => 'Student hostel complaints, category/priority tracking, and resolution notes.',
                'default_enabled' => true,
            ],
            'hostel.outpasses' => [
                'name' => 'Outpasses',
                'category' => 'Workflow',
                'description' => 'Outpass requests, approval, rejection, return tracking, and student visibility.',
                'default_enabled' => true,
            ],
            'hostel.fees' => [
                'name' => 'Hostel Fees',
                'category' => 'Finance',
                'description' => 'Monthly hostel fee demands, pending dues, paid/waived status, and student balances.',
                'default_enabled' => true,
            ],
        ],
        'TRANSPORT' => [
            'transport.routes_stops' => [
                'name' => 'Routes And Stops',
                'category' => 'Configuration',
                'description' => 'Transport routes, pickup/drop stops, fee setup, and route planning.',
                'default_enabled' => true,
            ],
            'transport.vehicles' => [
                'name' => 'Vehicles And Drivers',
                'category' => 'Operations',
                'description' => 'Vehicle register, driver details, capacity, assignment, and maintenance status.',
                'default_enabled' => true,
            ],
            'transport.student_assignments' => [
                'name' => 'Student Transport Assignments',
                'category' => 'Student Services',
                'description' => 'Student route assignment, pickup/drop stops, fee, and assignment history.',
                'default_enabled' => true,
            ],
        ],
        'LIB' => [
            'library.catalog' => [
                'name' => 'Library Catalog',
                'category' => 'Configuration',
                'description' => 'Books, copies, categories, availability, and searchable catalog records.',
                'default_enabled' => true,
            ],
            'library.circulation' => [
                'name' => 'Circulation',
                'category' => 'Operations',
                'description' => 'Book issue, return, overdue tracking, availability synchronization, and fines.',
                'default_enabled' => true,
            ],
            'library.memberships' => [
                'name' => 'Library Memberships',
                'category' => 'Student Services',
                'description' => 'Student membership limits, borrowing eligibility, and account status.',
                'default_enabled' => true,
            ],
        ],
    ],
];
