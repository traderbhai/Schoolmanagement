<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FrontendNavigation;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_admin_and_operations_pages_open_without_debug_traces(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();

        $routesByUser = [
            $admin->email => [
                'admin.dashboard',
                'admin.search',
                'admin.analytics',
                'admin.students.index',
                'admin.teachers.index',
                'admin.roles.permissions.index',
                'admin.settings',
                'admin.api-docs',
                'admin.fees.collect',
                'admin.library.index',
                'admin.library.books',
                'admin.hostel.index',
                'admin.hostel.allocations',
                'admin.transport.index',
                'admin.assets.index',
            ],
            $accounts->email => [
                'accounts.dashboard',
                'accounts.fee-collections',
                'accounts.outstanding',
                'accounts.reconciliation',
                'accounts.scholarship-disbursements',
                'accounts.reports',
            ],
            $cmc->email => [
                'cmc.dashboard',
                'cmc.drives',
                'cmc.companies',
                'cmc.events',
                'cmc.analytics',
            ],
        ];

        foreach ($routesByUser as $email => $routes) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($routes as $route) {
                $response = $this->actingAs($user)->get(route($route));

                $response->assertOk()
                    ->assertDontSee('Whoops', false)
                    ->assertDontSee('SERVICE ERROR', false)
                    ->assertDontSee('Stack trace', false)
                    ->assertDontSee('Laravel\\', false)
                    ->assertSee('<title', false);
            }
        }
    }

    public function test_crawled_admin_exam_and_approval_entry_points_render(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        foreach ([
            route('exam-cell.exams.create'),
            route('approvals.inbox'),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Route [exam-cell.exams.index] not defined', false)
                ->assertDontSee('Call to undefined method', false)
                ->assertSee('<title', false);
        }
    }

    public function test_admin_and_operations_dashboards_expose_real_workflow_links(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin operating sequence')
            ->assertSee('Owner: Admin / Director')
            ->assertSee('Source: institute master data, attendance, fees, notices, exams, and audit logs')
            ->assertSee('Rs.')
            ->assertSee('Attendance Trend - Last 14 Days')
            ->assertSee('Fee Collection - Last 6 Months')
            ->assertSee(route('admin.students.index'), false)
            ->assertSee(route('admin.fees.collect'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertDontSee('â', false)
            ->assertDontSee('Â', false)
            ->assertDontSee('₹', false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($admin)
            ->get(route('admin.library.index'))
            ->assertOk()
            ->assertSee(route('admin.library.books'), false)
            ->assertSee(route('admin.library.issues'), false)
            ->assertSee(route('admin.library.reservations'), false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($admin)
            ->get(route('admin.hostel.index'))
            ->assertOk()
            ->assertSee(route('admin.hostel.allocations'), false)
            ->assertSee(route('admin.hostel.fees'), false)
            ->assertSee(route('admin.hostel.complaints'), false)
            ->assertDontSee('href="#"', false);

        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $this->actingAs($accounts)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee(route('accounts.admission-payments'), false)
            ->assertSee(route('accounts.scholarship-disbursements'), false)
            ->assertSee(route('accounts.outstanding'), false)
            ->assertSee(route('accounts.reports'), false)
            ->assertDontSee(route('admission.scholarship-disbursements.index'), false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($accounts)
            ->get(route('accounts.admission-payments'))
            ->assertOk()
            ->assertSee('Accounts queue')
            ->assertDontSee(route('admission.payments.queue'), false);

        $this->actingAs($accounts)
            ->get(route('accounts.scholarship-disbursements'))
            ->assertOk()
            ->assertSee('Accounts scholarship workflow')
            ->assertDontSee(route('admission.scholarship-disbursements.index'), false);

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();
        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee(route('cmc.drives'), false)
            ->assertSee(route('cmc.events.create'), false)
            ->assertSee(route('cmc.analytics'), false)
            ->assertDontSee('href="#"', false);
    }

    public function test_admin_dashboard_quick_actions_and_setup_entry_pages_are_reachable(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.students.create'), false)
            ->assertSee(route('admin.attendance.index'), false)
            ->assertSee(route('admin.fees.collect'), false)
            ->assertSee(route('admin.notices.create'), false)
            ->assertSee(route('admin.admissions.create'), false)
            ->assertSee(route('admin.institutional-kpi'), false)
            ->assertSee(route('admin.aicte-report'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertDontSee('href="#"', false);

        foreach ([
            'admin.academic-years.index',
            'admin.academic-years.create',
            'admin.departments.index',
            'admin.departments.create',
            'admin.programs.index',
            'admin.programs.create',
            'admin.batches.index',
            'admin.batches.create',
            'admin.users.roles.index',
            'admin.users.roles.create',
            'admin.roles.permissions.index',
            'admin.roles.feature-access.index',
            'admin.settings',
            'admin.settings.branding',
        ] as $route) {
            $response = $this->actingAs($admin)->get(route($route));

            $response->assertOk()
                ->assertSee('<title', false)
                ->assertSee('sidebar-mobile-toggle', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);
        }
    }

    public function test_accounts_and_cmc_branches_use_manifest_grouped_sidebar_links(): void
    {
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $this->actingAs($accounts)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Finance')
            ->assertSee('Dashboard')
            ->assertSee('Fee Collections')
            ->assertSee('Admission Payments')
            ->assertSee('Outstanding')
            ->assertSee('Reconciliation')
            ->assertSee('Reports')
            ->assertSee(route('accounts.dashboard'), false)
            ->assertSee(route('accounts.fee-collections'), false)
            ->assertSee(route('accounts.admission-payments'), false)
            ->assertSee(route('accounts.reconciliation'), false)
            ->assertDontSee('href="#"', false);

        $accountsGroups = FrontendNavigation::manifest()['accounts']['groups'];

        $this->assertSame(
            ['Fee Collections', 'Admission Payments', 'Outstanding', 'Reconciliation'],
            collect($accountsGroups['Finance'])->pluck('label')->all()
        );
        $this->assertSame(
            ['Reports'],
            collect($accountsGroups['Reports'])->pluck('label')->all()
        );

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();
        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Placement')
            ->assertSee('Students')
            ->assertSee('Reports')
            ->assertSee('Dashboard')
            ->assertSee('Placement Drives')
            ->assertSee('Companies')
            ->assertSee('Career Events')
            ->assertSee('Placement Stats')
            ->assertSee('Internships')
            ->assertSee('Alumni Database')
            ->assertSee('Analytics')
            ->assertSee(route('cmc.dashboard'), false)
            ->assertSee(route('cmc.drives'), false)
            ->assertSee(route('cmc.companies'), false)
            ->assertSee(route('cmc.events'), false)
            ->assertSee(route('cmc.analytics'), false)
            ->assertDontSee('href="#"', false);

        $cmcGroups = FrontendNavigation::manifest()['cmc']['groups'];

        $this->assertSame(
            ['Placement Drives', 'Companies', 'Career Events'],
            collect($cmcGroups['Placement'])->pluck('label')->all()
        );
        $this->assertSame(
            ['Placement Stats', 'Analytics'],
            collect($cmcGroups['Reports'])->pluck('label')->all()
        );

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="accounts"', $layout);
        $this->assertStringContainsString('<x-ui.manifest-sidebar role="cmc"', $layout);
    }

    public function test_admin_branch_uses_manifest_grouped_sidebar_links(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Governance')
            ->assertSee('Timetable')
            ->assertSee('People')
            ->assertSee('Admission')
            ->assertSee('Assessments')
            ->assertSee('Academics / Delivery')
            ->assertSee('Finance')
            ->assertSee('Reports')
            ->assertSee('Operations')
            ->assertSee('Settings')
            ->assertSee('Academic Years')
            ->assertSee('Teachers')
            ->assertSee('Parents')
            ->assertSee('Weekly Timetable')
            ->assertSee('Student Documents')
            ->assertSee('Command Center')
            ->assertSee('Assessment Panels')
            ->assertSee('Applicants CRM')
            ->assertSee('All Leads')
            ->assertSee('Communication Hub')
            ->assertSee('Academics Command')
            ->assertSee('Exams &amp; Results', false)
            ->assertSee('Scholarship Schemes')
            ->assertSee('Role Hierarchy')
            ->assertSee('System Settings')
            ->assertSee('Library')
            ->assertSee(route('admin.academic-years.index'), false)
            ->assertSee(route('admission.command-center.index'), false)
            ->assertSee(route('admission.assessment-panels.index'), false)
            ->assertSee(route('academics.command-center.index'), false)
            ->assertSee(route('admin.roles.hierarchy'), false)
            ->assertDontSee('href="#"', false);

        $adminGroups = FrontendNavigation::manifest()['admin']['groups'];

        $this->assertSame(
            ['Dashboard', 'Global Search'],
            collect($adminGroups['Command'])->pluck('label')->all()
        );
        $this->assertContains('Analytics', collect($adminGroups['Reports'])->pluck('label')->all());
        $this->assertContains('Institutional KPI', collect($adminGroups['Reports'])->pluck('label')->all());
        $this->assertContains('Fees', collect($adminGroups['Finance'])->pluck('label')->all());
        $this->assertContains('Accounts Dashboard', collect($adminGroups['Finance'])->pluck('label')->all());
        $this->assertContains('Placement Drives', collect($adminGroups['Placement'])->pluck('label')->all());
        $this->assertNotContains('Placement Stats', collect($adminGroups['Placement'])->pluck('label')->all());
        $this->assertContains('Placement Stats', collect($adminGroups['Reports'])->pluck('label')->all());
        $this->assertNotContains('Fee Report', collect($adminGroups['Finance'])->pluck('label')->all());
        $this->assertContains('Fee Report', collect($adminGroups['Reports'])->pluck('label')->all());
        $this->assertNotContains('Lead Analytics', collect($adminGroups['Leads'])->pluck('label')->all());
        $this->assertContains('Lead Analytics', collect($adminGroups['Reports'])->pluck('label')->all());
        $this->assertNotContains('Leave Approvals', collect($adminGroups['Academics / Delivery'])->pluck('label')->all());
        $this->assertContains('Leave Approvals', collect($adminGroups['Approvals'])->pluck('label')->all());
        $this->assertNotContains('Academics Governance', collect($adminGroups['Academics / Delivery'])->pluck('label')->all());
        $this->assertContains('Academics Governance', collect($adminGroups['Governance'])->pluck('label')->all());
        $this->assertContains('Integration Health', collect($adminGroups['Settings'])->pluck('label')->all());
        $this->assertNotContains('Integration Health', collect($adminGroups['Reports'])->pluck('label')->all());

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="admin"', $layout);
        $this->assertSame(2, substr_count($layout, 'role="admin" brand-sub="Admin Portal"'));
        $this->assertStringNotContainsString('Academic Setup</div>', $layout);
    }

    public function test_primary_admin_and_operations_views_do_not_contain_placeholder_or_framework_trace_text(): void
    {
        $viewPaths = [
            resource_path('views/admin/dashboard.blade.php'),
            resource_path('views/admin/settings/index.blade.php'),
            resource_path('views/admin/settings/api-docs.blade.php'),
            resource_path('views/admin/roles/permissions/index.blade.php'),
            resource_path('views/admin/fees/collect.blade.php'),
            resource_path('views/admin/results/index.blade.php'),
            resource_path('views/admin/library/index.blade.php'),
            resource_path('views/admin/library/books.blade.php'),
            resource_path('views/admin/hostel/index.blade.php'),
            resource_path('views/admin/hostel/allocations.blade.php'),
            resource_path('views/admin/transport/index.blade.php'),
            resource_path('views/admin/assets/index.blade.php'),
            resource_path('views/departmental/accounts/dashboard.blade.php'),
            resource_path('views/departmental/accounts/fee-collections.blade.php'),
            resource_path('views/departmental/cmc/dashboard.blade.php'),
            resource_path('views/departmental/cmc/drives.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('href="#"', $contents, $path);
            $this->assertStringNotContainsString("href='#'", $contents, $path);
            $this->assertStringNotContainsString('javascript:void', $contents, $path);
            $this->assertStringNotContainsString('â', $contents, $path);
            $this->assertStringNotContainsString('Ã', $contents, $path);
            $this->assertStringNotContainsString('Â', $contents, $path);
            $this->assertStringNotContainsString('</form><form', $contents, $path);
            $this->assertStringNotContainsString('Ã‚', $contents, $path);
            $this->assertStringNotContainsString('Whoops', $contents, $path);
            $this->assertStringNotContainsString('Stack trace', $contents, $path);
            $this->assertStringNotContainsString('Laravel', $contents, $path);
        }
    }

    public function test_admin_grade_reports_template_uses_result_specific_empty_state_and_fallbacks(): void
    {
        $contents = file_get_contents(resource_path('views/admin/results/index.blade.php'));

        $this->assertStringContainsString('No published result records match the selected student and semester.', $contents);
        $this->assertStringContainsString('Marks not published', $contents);
        $this->assertStringContainsString('Grade pending', $contents);
        $this->assertStringContainsString('Points pending', $contents);
        $this->assertStringNotContainsString('No attendance records found for the selected criteria.', $contents);
        $this->assertStringNotContainsString('Select studentâ', $contents);
        $this->assertStringNotContainsString('Select semesterâ', $contents);
        $this->assertStringNotContainsString('Subject-wise Results â', $contents);
    }

    public function test_sensitive_operations_lifecycle_forms_have_confirmation_guards(): void
    {
        $expectations = [
            resource_path('views/admin/assets/index.blade.php') => [
                'Confirm custodian, handover date, accessories, and return expectations before changing asset custody.',
                'Confirm physical inspection, accessories, custody handover, and maintenance status before closing the assignment.',
                'Confirm quantity, vendor/reference, date, and storage location before increasing available stock.',
                'Confirm quantity, recipient, purpose, and low-stock impact before recording the movement.',
            ],
            resource_path('views/admin/transport/index.blade.php') => [
                'Confirm route/stop removal, monthly fee impact, vehicle capacity release, and student communication before ending access.',
            ],
            resource_path('views/admin/hostel/fees.blade.php') => [
                'Confirm receipt, month, room allocation, and reconciliation reference before closing the demand.',
                'Confirm approved waiver authority, audit reason, and NOC/clearance impact before closing the demand.',
            ],
            resource_path('views/admin/hostel/outpasses.blade.php') => [
                'Confirm reason, expected return, guardian/campus policy, and active hostel allocation before allowing exit.',
                'Confirm physical return, actual time, and any late/escalation notes before closing the movement record.',
            ],
            resource_path('views/admin/library/reservations.blade.php') => [
                'Confirm copy availability, borrower eligibility, due date, and queue fairness before issuing.',
                'Confirm borrower communication and queue impact before cancellation.',
            ],
            resource_path('views/admin/library/fines.blade.php') => [
                'Confirm receipt/reference and unresolved book-return status before closing the fine.',
            ],
        ];

        foreach ($expectations as $path => $guards) {
            $contents = file_get_contents($path);

            foreach ($guards as $guard) {
                $this->assertStringContainsString($guard, $contents, $path);
            }
        }

        $issuesContents = file_get_contents(resource_path('views/admin/library/issues.blade.php'));
        $this->assertStringContainsString('Confirm copy condition, borrower, due/fine status, and shelf availability before closing the issue.', $issuesContents);
        $this->assertStringContainsString('aria-label="Mark', $issuesContents);
        $this->assertStringNotContainsString("confirm('Mark this book as returned?')", $issuesContents);
    }

    public function test_role_permission_revoke_actions_explain_access_impact(): void
    {
        $expectations = [
            resource_path('views/admin/users/roles/index.blade.php') => [
                'Confirm dashboard access, approvals, reports, and portal permissions no longer require this assignment.',
                'aria-label="Revoke role',
            ],
            resource_path('views/admin/role-assignments/index.blade.php') => [
                'Confirm program/batch visibility, approvals, reports, and portal access should be removed for this scope.',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Revoke this role assignment?')", file_get_contents(resource_path('views/admin/users/roles/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Revoke this assignment?')", file_get_contents(resource_path('views/admin/role-assignments/index.blade.php')));
    }

    public function test_admin_admission_seat_document_and_hostel_actions_explain_impact(): void
    {
        $expectations = [
            resource_path('views/admin/admissions/index.blade.php') => [
                'Confirm this record is not needed for applicant history, enrollment audit, fee records, or reports.',
                'aria-label="Delete admission record',
            ],
            resource_path('views/admin/admissions/show.blade.php') => [
                'Confirm this record is not needed for applicant history, enrollment audit, fee records, or reports.',
            ],
            resource_path('views/admin/seat-matrix/index.blade.php') => [
                'Confirm no offer round, waitlist movement, category allocation, or enrollment report depends on this capacity setup.',
                'aria-label="Delete seat matrix',
            ],
            resource_path('views/admin/document-requests/index.blade.php') => [
                'Confirm the rejection reason explains the missing/invalid requirement and the student can act on it before the request is closed.',
            ],
            resource_path('views/admin/hostel/allocations.blade.php') => [
                'Confirm room keys, fee clearance, inventory/inspection, and bed capacity release before ending the allocation.',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Delete this record?')", file_get_contents(resource_path('views/admin/admissions/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Delete this seat matrix?')", file_get_contents(resource_path('views/admin/seat-matrix/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Reject this document request?')", file_get_contents(resource_path('views/admin/document-requests/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Mark as vacated?')", file_get_contents(resource_path('views/admin/hostel/allocations.blade.php')));
    }

    public function test_admin_cmc_timetable_mail_company_and_program_actions_explain_impact(): void
    {
        $expectations = [
            resource_path('views/admin/bulk-mail/index.blade.php') => [
                'Confirm audience filters, subject, message body, and unsubscribe/contact policy before dispatch.',
            ],
            resource_path('views/admin/timetable/index.blade.php') => [
                'Apply timetable filters',
                'Confirm this is not the canonical PMC official session and check attendance, teacher/student timetable, and reporting impact before deletion.',
                'aria-label="Edit legacy timetable entry',
                'aria-label="Remove legacy timetable entry',
            ],
            resource_path('views/admin/companies/index.blade.php') => [
                'Confirm placement drives, student applications, offer history, and CMC reports no longer depend on this company record.',
                'aria-label="Delete company',
            ],
            resource_path('views/admin/placement-drives/index.blade.php') => [
                'Confirm student applications, shortlist/interview records, company communication, and placement reports no longer depend on it.',
                'aria-label="Delete placement drive',
            ],
            resource_path('views/admin/programs/show.blade.php') => [
                'Confirm curriculum, admissions, seat matrix, course groups, and student records no longer depend on it.',
                'aria-label="Remove specialization',
            ],
            resource_path('views/departmental/cmc/create-drive.blade.php') => [
                'Confirm company, eligibility, application deadline, student visibility, and communication readiness before saving.',
            ],
            resource_path('views/departmental/cmc/create-event.blade.php') => [
                'Confirm date, venue, seats, registration deadline, student visibility, and communication readiness before saving.',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Remove?')", file_get_contents(resource_path('views/admin/timetable/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Delete this company?')", file_get_contents(resource_path('views/admin/companies/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Delete this drive?')", file_get_contents(resource_path('views/admin/placement-drives/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Remove specialization?')", file_get_contents(resource_path('views/admin/programs/show.blade.php')));
    }

    public function test_core_admin_icon_actions_have_accessible_names(): void
    {
        $expectations = [
            resource_path('views/admin/academic-years/index.blade.php') => [
                'aria-label="View academic year',
                'aria-label="Edit academic year',
                'aria-label="Delete academic year',
            ],
            resource_path('views/admin/courses/index.blade.php') => [
                'aria-label="View course',
                'aria-label="Edit course',
                'aria-label="Delete course',
            ],
            resource_path('views/admin/timetable-slots/index.blade.php') => [
                'aria-label="Edit timetable slot',
                'aria-label="Delete timetable slot',
            ],
            resource_path('views/admin/teachers/index.blade.php') => [
                'aria-label="View teacher',
                'aria-label="Edit teacher',
                'aria-label="Delete teacher',
            ],
            resource_path('views/admin/classrooms/index.blade.php') => [
                'aria-label="View classroom',
                'aria-label="Edit classroom',
                'aria-label="Delete classroom',
            ],
            resource_path('views/admin/subjects/index.blade.php') => [
                'aria-label="Edit subject',
                'aria-label="Delete subject',
            ],
            resource_path('views/admin/batches/index.blade.php') => [
                'aria-label="View batch',
                'aria-label="Edit batch',
                'aria-label="Delete batch',
            ],
            resource_path('views/admin/admissions/index.blade.php') => [
                'aria-label="View admission record',
                'aria-label="Edit admission record',
            ],
            resource_path('views/admin/library/books.blade.php') => [
                'aria-label="Search library books',
                'aria-label="View library book',
            ],
            resource_path('views/admin/grievances/index.blade.php') => [
                'aria-label="View grievance',
            ],
            resource_path('views/admin/leaves/index.blade.php') => [
                'aria-label="View leave request',
                'aria-label="Approve leave request',
                'aria-label="Reject leave request',
                'aria-label="Delete leave request',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }
    }

    public function test_finance_and_admission_setup_risky_actions_explain_downstream_impact(): void
    {
        $expectations = [
            resource_path('views/academic/fee-demands/index.blade.php') => [
                'Confirm the fee structure, due dates, scholarships, and active student list are final before creating finance ledger records.',
                'Confirm the penalty policy, due dates, and any approved waivers before updating student balances.',
            ],
            resource_path('views/admin/admission-config/index.blade.php') => [
                'Confirm no active applicants still need this checklist item before changing admission readiness rules.',
                'Confirm this will not invalidate active assessments, scores, merit rules, or evaluator workflow history.',
                'Confirm no applicant payment, offer deadline, or finance reconciliation depends on this installment.',
                'aria-label="Remove required document',
                'aria-label="Remove selection step',
                'aria-label="Remove admission fee installment',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Generate fee demands for all active students in this batch/term?')", file_get_contents(resource_path('views/academic/fee-demands/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Remove?')", file_get_contents(resource_path('views/admin/admission-config/index.blade.php')));
    }

    public function test_batch_g_operations_action_entry_surfaces_are_guided(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        foreach ([
            'admin.library.books' => ['Add Book', 'Export Current View'],
            'admin.library.issues' => ['Issue Book', 'Export Current View'],
            'admin.library.reservations' => ['Issues', 'Export Current View'],
            'admin.library.fines' => ['Unpaid Library Fines', 'Export Current View'],
            'admin.hostel.index' => ['Add Block', 'Create Block'],
            'admin.hostel.allocations' => ['Hostel Allocations', 'Export Current View'],
            'admin.hostel.fees' => ['Generate Monthly Demands', 'Export Current View'],
            'admin.hostel.outpasses' => ['Outpasses', 'Export Current View'],
            'admin.transport.index' => ['Create Route', 'Assign Student To Transport'],
            'admin.assets.index' => ['Create Consumable Stock Item', 'Add Asset'],
        ] as $route => $needles) {
            $response = $this->actingAs($admin)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false)
                ->assertDontSee('href="#"', false);

            foreach ($needles as $needle) {
                $response->assertSee($needle);
            }
        }

        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        foreach ([
            'accounts.outstanding' => ['Outstanding Fees', 'Export Current View'],
            'accounts.reconciliation' => ['Admission Fee Reconciliation', 'Export Current View'],
        ] as $route => $needles) {
            $response = $this->actingAs($accounts)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);

            foreach ($needles as $needle) {
                $response->assertSee($needle);
            }
        }

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();
        foreach ([
            'cmc.drives.create' => ['Create Placement Drive', 'Create Drive', 'confirm('],
            'cmc.companies.create' => ['Add Company', 'Company Name', 'confirm('],
            'cmc.events.create' => ['Create Career Event', 'Create Event', 'confirm('],
        ] as $route => $needles) {
            $response = $this->actingAs($cmc)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false)
                ->assertDontSee('href="#"', false);

            foreach ($needles as $needle) {
                $response->assertSee($needle, false);
            }
        }
    }

    public function test_batch_g_mobile_layouts_keep_navigation_and_tables_usable(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();

        foreach ([
            [$admin, 'admin.transport.index'],
            [$admin, 'admin.assets.index'],
            [$admin, 'admin.library.issues'],
            [$admin, 'admin.hostel.allocations'],
            [$accounts, 'accounts.outstanding'],
            [$accounts, 'accounts.reconciliation'],
            [$cmc, 'cmc.drives'],
            [$cmc, 'cmc.companies'],
            [$cmc, 'cmc.events'],
        ] as [$user, $route]) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk()
                ->assertSee('id="mobileSidebar"', false)
                ->assertSee('sidebar-mobile-toggle', false)
                ->assertSee('aria-label="Open navigation menu"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);

            if (str_contains($response->getContent(), '<table')) {
                $response->assertSee('table-responsive', false);
            }
        }

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('offcanvas offcanvas-start sidebar-mobile', $layout);
        $this->assertStringContainsString('data-bs-target="#mobileSidebar"', $layout);
        $this->assertStringContainsString('.sidebar-mobile', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
    }

    public function test_admin_director_and_hod_mobile_shell_and_security_navigation_are_usable(): void
    {
        foreach ([
            ['admin@demo.edu', 'admin.dashboard'],
            ['director@college.com', 'director.dashboard'],
            ['hod@college.com', 'hod.dashboard'],
        ] as [$email, $route]) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk()
                ->assertSee('id="mobileSidebar"', false)
                ->assertSee('sidebar-mobile-toggle', false)
                ->assertSee('data-bs-target="#mobileSidebar"', false)
                ->assertSee('aria-label="Open navigation menu"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);

            if ($route === 'director.dashboard') {
                $response->assertSee('Academics / Delivery')
                    ->assertSee('Programs')
                    ->assertSee('Reports')
                    ->assertSee('Director Reports');
            }

            if ($route === 'hod.dashboard') {
                $response->assertSee('Academics / Delivery')
                    ->assertSee('Faculty Roster')
                    ->assertSee('Approvals')
                    ->assertSee('Leave Approvals')
                    ->assertSee('Reports')
                    ->assertSee('Dept Performance');
            }
        }

        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        foreach ([
            'admin.roles.permissions.index' => 'Permission Matrix',
            'admin.users.roles.index' => 'Role Assignments',
            'admin.roles.feature-access.index' => 'Feature Access Matrix',
            'admin.settings' => 'System Settings',
            'admin.audit.index' => 'Audit',
        ] as $route => $label) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }
}
