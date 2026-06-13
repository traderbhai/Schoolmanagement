<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::roleDashboardProvider() as [$role]) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleDashboardProvider(): array
    {
        return [
            'admin' => ['admin', 'admin.dashboard'],
            'admission_head' => ['admission_head', 'admission.dashboard'],
            'admission_officer' => ['admission_officer', 'admission.dashboard'],
            'dean_academics' => ['dean_academics', 'dean.dashboard'],
            'program_chair' => ['program_chair', 'chair.dashboard'],
            'hod' => ['hod', 'hod.dashboard'],
            'exam_cell' => ['exam_cell', 'exam-cell.dashboard'],
            'accounts_officer' => ['accounts_officer', 'accounts.dashboard'],
            'cmc' => ['cmc', 'cmc.dashboard'],
            'director' => ['director', 'director.dashboard'],
            'teacher' => ['teacher', 'teacher.dashboard'],
            'parent' => ['parent', 'parent.dashboard'],
            'applicant' => ['applicant', 'applicant.dashboard'],
            'student' => ['student', 'student.dashboard'],
        ];
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_login_redirects_each_role_to_their_primary_dashboard(string $role, string $expectedRoute): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole($role);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route($expectedRoute));
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_root_redirects_each_role_to_their_primary_dashboard(string $role, string $expectedRoute): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route($expectedRoute));
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_dashboard_alias_redirects_each_role_to_their_primary_dashboard(string $role, string $expectedRoute): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route($expectedRoute));
    }
}
