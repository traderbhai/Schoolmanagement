# Module Boundaries

## Academics / PMC

- Source of truth: program, batch, term, curriculum, faculty allocation, canonical PMC timetable generation items, academic readiness and delivery records.
- Timetable rule: `academic_pmc_timetable_generation_items` is canonical for official sessions; `timetable_entries` is compatibility output only.
- Main consumers: Program Chair, PMC, Dean, Teacher, Student timetable, attendance, substitutions, room utilization, and academic reports.

## Admission

- Source of truth: applicants, applications, leads, selection sessions, scoring, merit, offers, admission payments, documents, and enrollment handoff.
- Main consumers: applicant portal, admission team workspaces, reports, communications, offer/enrollment outputs, and accounts handoff.

## Examination

- Source of truth: exams, schedules, hall tickets, marks, result publication, grade reports, appeals, and anomaly records.
- Main consumers: exam cell, students, teachers, admin reports, transcripts, and notifications.

## Finance / Accounts

- Source of truth: fee structures, demands, payments, refunds, scholarships, admission payments, reconciliation, and account reports.
- Main consumers: accounts dashboard, student fees, applicant payments, admin finance reports, and receipts.

## Student Portal

- Source of truth: student profile, enrollments, subject visibility, attendance, timetable, assignments, exams, fees, career events, and notifications.
- Main consumers: student dashboard, course hub, timetable, fees, admit cards, results, and profile workflows.

## Teacher Portal

- Source of truth: teacher profile, official teaching subjects, canonical timetable sessions, attendance marking, assignments, materials, announcements, feedback, and exams.
- Main consumers: teacher dashboard, teaching workflow pages, attendance, student rosters, and exam result entry.

## Admin Operations

- Source of truth: users, roles, departments, master data, hostels, transport, library, assets, governance, and cross-module dashboards.
- Main consumers: admin dashboards, operational CRUD pages, role navigation, and institutional reports.

## CMC / Placement

- Source of truth: companies, placement drives, applications, placements, internships, career events, alumni, and placement analytics.
- Main consumers: CMC dashboard, student career workflows, placement reports, and alumni operations.

