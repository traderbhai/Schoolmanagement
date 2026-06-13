<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke-tests that every role dashboard works with the minimum profile data
 * the real portal expects. This protects the commercial role surface while
 * product and UX work iterates across portals.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);
        return $user;
    }

    private function makeTeacherUser(): User
    {
        $user = $this->makeUserWithRole('teacher');
        Teacher::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeStudentUser(): User
    {
        $user = $this->makeUserWithRole('student');
        Student::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeApplicantUser(): User
    {
        $user = $this->makeUserWithRole('applicant');
        Applicant::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeParentUser(): User
    {
        $user = $this->makeUserWithRole('parent');
        $student = Student::factory()->create();
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function staffDashboardProvider(): array
    {
        return [
            'admin' => ['admin', 'admin.dashboard'],
            'admission head' => ['admission_head', 'admission.dashboard'],
            'admission officer' => ['admission_officer', 'admission.dashboard'],
            'dean academics' => ['dean_academics', 'dean.dashboard'],
            'hod' => ['hod', 'hod.dashboard'],
            'program chair' => ['program_chair', 'chair.dashboard'],
            'exam cell' => ['exam_cell', 'exam-cell.dashboard'],
            'accounts officer' => ['accounts_officer', 'accounts.dashboard'],
            'cmc' => ['cmc', 'cmc.dashboard'],
            'director' => ['director', 'director.dashboard'],
        ];
    }

    #[DataProvider('staffDashboardProvider')]
    public function test_staff_role_dashboard_accessible(string $role, string $routeName): void
    {
        $user = $this->makeUserWithRole($role);

        $this->actingAs($user)->get(route($routeName))->assertStatus(200);
    }

    public function test_teacher_dashboard_accessible(): void
    {
        $this->actingAs($this->makeTeacherUser())
            ->get(route('teacher.dashboard'))
            ->assertStatus(200);
    }

    public function test_student_dashboard_accessible(): void
    {
        $this->actingAs($this->makeStudentUser())
            ->get(route('student.dashboard'))
            ->assertStatus(200);
    }

    public function test_parent_dashboard_accessible(): void
    {
        $this->actingAs($this->makeParentUser())
            ->get(route('parent.dashboard'))
            ->assertStatus(200);
    }

    public function test_applicant_dashboard_accessible(): void
    {
        $this->actingAs($this->makeApplicantUser())
            ->get(route('applicant.dashboard'))
            ->assertStatus(200);
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_wrong_role_cannot_access_admin_dashboard(): void
    {
        $user = $this->makeUserWithRole('teacher');
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }
}
