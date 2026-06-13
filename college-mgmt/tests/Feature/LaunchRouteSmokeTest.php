<?php

namespace Tests\Feature;

use App\Models\ApplicationWindow;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
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
