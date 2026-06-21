<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_admin_dashboard_explains_operating_sequence(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin operating sequence')
            ->assertSee('1. Check institute KPIs')
            ->assertSee('2. Review attendance/fees')
            ->assertSee('3. Open quick action')
            ->assertSee('4. Verify audit/security')
            ->assertSee('5. Export or report')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_admin_dashboard_primary_kpis_open_source_pages(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Active Students')
            ->assertSee('Teachers')
            ->assertSee('Departments')
            ->assertSee('Courses')
            ->assertSeeText("Today's Attendance")
            ->assertSee('Fee Collected')
            ->assertSee('Active Notices')
            ->assertSee('Upcoming Exams');

        foreach ([
            'admin.students.index',
            'admin.teachers.index',
            'admin.departments.index',
            'admin.programs.index',
            'admin.attendance.index',
            'admin.fees.index',
            'admin.notices.index',
            'admin.exams.index',
        ] as $route) {
            $response->assertSee(route($route), false);
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_admin_setup_pages_show_configuration_sequence(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $routes = [
            'admin.dashboard',
            'admin.academic-years.index',
            'admin.departments.index',
            'admin.programs.index',
            'admin.batches.index',
            'admin.semesters.index',
            'admin.role-assignments.index',
            'admin.roles.permissions.index',
            'admin.settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Admin setup sequence')
                ->assertSee('1. Academic year')
                ->assertSee('2. Departments')
                ->assertSee('3. Programs')
                ->assertSee('4. Batches')
                ->assertSee('5. Terms')
                ->assertSee('6. Users and roles')
                ->assertSee('7. Permissions')
                ->assertSee(route('admin.academic-years.index'), false)
                ->assertSee(route('admin.departments.index'), false)
                ->assertSee(route('admin.programs.index'), false)
                ->assertSee(route('admin.batches.index'), false)
                ->assertSee(route('admin.semesters.index'), false)
                ->assertSee(route('admin.role-assignments.index'), false)
                ->assertSee(route('admin.roles.permissions.index'), false)
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);
        }
    }

    public function test_accounts_and_cmc_dashboards_explain_daily_workflow(): void
    {
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();

        $this->actingAs($accounts)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Accounts operating sequence')
            ->assertSee('1. Verify payments')
            ->assertSee('2. Review outstanding')
            ->assertSee('3. Process scholarships')
            ->assertSee('4. Reconcile receipts')
            ->assertSee('5. Export reports')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('SERVICE ERROR', false);

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();

        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee('CMC operating sequence')
            ->assertSee('1. Check active drives')
            ->assertSee('2. Create drive/event')
            ->assertSee('3. Track applications')
            ->assertSee('4. Review placement rate')
            ->assertSee('5. Open analytics')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_operations_entry_pages_explain_operating_sequences(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        foreach ([
            ['route' => 'admin.library.index', 'heading' => 'Library operating sequence', 'steps' => ['1. Check overdue', '2. Review due today', '3. Manage issues', '4. Update catalog', '5. Clear fines']],
            ['route' => 'admin.hostel.index', 'heading' => 'Hostel operating sequence', 'steps' => ['1. Check capacity', '2. Review allocations', '3. Check fees', '4. Handle outpasses/complaints', '5. Update blocks/rooms']],
            ['route' => 'admin.transport.index', 'heading' => 'Transport operating sequence', 'steps' => ['1. Create route', '2. Add stops', '3. Add vehicle', '4. Assign students', '5. Export/review fleet']],
            ['route' => 'admin.assets.index', 'heading' => 'Asset operating sequence', 'steps' => ['1. Check availability', '2. Review low stock', '3. Create item/asset', '4. Assign or return', '5. Export register']],
        ] as $case) {
            $response = $this->actingAs($admin)->get(route($case['route']))
                ->assertOk()
                ->assertSee($case['heading'])
                ->assertDontSee('href="#"', false)
                ->assertDontSee('SERVICE ERROR', false);

            foreach ($case['steps'] as $step) {
                $response->assertSee($step);
            }
        }
    }
}
