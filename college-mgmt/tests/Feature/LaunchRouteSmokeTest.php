<?php

namespace Tests\Feature;

use App\Models\ApplicationWindow;
use App\Models\Batch;
use App\Models\Applicant;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaunchRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    public function test_public_entry_pages_are_accessible(): void
    {
        $this->get(route('apply'))->assertStatus(200);
        $this->get(route('public.status-tracker.index'))->assertStatus(200);
    }

    public function test_public_program_application_page_opens_for_active_window(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        ApplicationWindow::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $this->get(route('apply.program', $program))->assertStatus(200);
    }

    public function test_seeded_demo_public_apply_flow_can_register_new_applicant(): void
    {
        $this->seed(DemoDataSeeder::class);

        $program = Program::where('code', 'PGDM')->firstOrFail();
        $window = ApplicationWindow::where('program_id', $program->id)
            ->where('is_active', true)
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->firstOrFail();
        $beforeCount = (int) $window->current_applications;

        $email = 'qa-public-apply@example.test';

        $this->get(route('apply.program', $program))
            ->assertOk()
            ->assertSee('Create Your Application');

        $this->post(route('apply.program.register', $program), [
            'name' => 'QA Public Applicant',
            'email' => $email,
            'phone' => '9876500000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('applicant.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertDatabaseHas('applicants', [
            'program_id' => $program->id,
            'batch_id' => $window->batch_id,
            'status' => 'draft',
        ]);
        $this->assertSame($beforeCount + 1, (int) $window->fresh()->current_applications);
        $applicant = Applicant::whereHas('user', fn ($query) => $query->where('email', $email))->firstOrFail();

        $this->post(route('applicant.application.section', 'personal'), [
            'father_name' => 'QA Father',
            'date_of_birth' => '2000-01-01',
            'gender' => 'Male',
            'category' => 'General',
            'domicile_state' => 'Delhi',
            'entrance_exam_type' => 'CAT',
            'entrance_exam_score' => '91',
            'entrance_exam_roll_number' => 'QA123',
            'entrance_exam_year' => '2025',
            'phone' => '9876500000',
            'address' => 'QA Address',
        ])
            ->assertRedirect(route('applicant.application.show', ['step' => 1]))
            ->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('general', $applicant->category);
        $this->assertSame('Delhi', $applicant->domicile_state);
        $this->assertSame('cat', $applicant->entrance_exam_type);
        $this->assertSame(91.0, $applicant->entrance_exam_score);
        $this->assertSame('QA123', $applicant->entrance_exam_roll_number);
        $this->assertSame(2025, $applicant->entrance_exam_year);
    }

    public function test_closed_public_program_application_redirects_to_apply_index(): void
    {
        $program = Program::factory()->create(['is_active' => true]);

        $this->get(route('apply.program', $program))
            ->assertRedirect(route('apply'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'admin dashboard' => ['admin.dashboard'],
            'admission dashboard' => ['admission.dashboard'],
            'teacher dashboard' => ['teacher.dashboard'],
            'student dashboard' => ['student.dashboard'],
            'parent dashboard' => ['parent.dashboard'],
            'dean dashboard' => ['dean.dashboard'],
            'program chair dashboard' => ['chair.dashboard'],
            'hod dashboard' => ['hod.dashboard'],
            'exam cell dashboard' => ['exam-cell.dashboard'],
            'accounts dashboard' => ['accounts.dashboard'],
            'cmc dashboard' => ['cmc.dashboard'],
            'director dashboard' => ['director.dashboard'],
            'applicant dashboard' => ['applicant.dashboard'],
        ];
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_protected_portal_routes_redirect_guests_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unrelatedRoleDenialProvider(): array
    {
        return [
            'student cannot open admission dashboard' => ['student', 'admission.dashboard'],
            'student cannot open teacher dashboard' => ['student', 'teacher.dashboard'],
            'teacher cannot open student dashboard' => ['teacher', 'student.dashboard'],
            'teacher cannot open parent dashboard' => ['teacher', 'parent.dashboard'],
            'applicant cannot open admin dashboard' => ['applicant', 'admin.dashboard'],
            'parent cannot open accounts dashboard' => ['parent', 'accounts.dashboard'],
            'accounts cannot open student dashboard' => ['accounts_officer', 'student.dashboard'],
            'cmc cannot open applicant dashboard' => ['cmc', 'applicant.dashboard'],
        ];
    }

    #[DataProvider('unrelatedRoleDenialProvider')]
    public function test_unrelated_roles_cannot_open_other_portals(string $roleName, string $routeName): void
    {
        $this->actingAs($this->makeUserWithRole($roleName))
            ->get(route($routeName))
            ->assertStatus(403);
    }
}
