<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminExamAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_global_exam_authority_roles_can_open_admin_exam_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics', 'exam_cell'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.exams.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.exams.create'))->assertOk();
            $this->actingAs($user)->get(route('admin.results.index'))->assertOk();
        }
    }

    public function test_broad_non_exam_admin_group_roles_cannot_open_global_exam_surfaces(): void
    {
        $exam = $this->examFixture();

        foreach (['program_chair', 'hod', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.exams.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.exams.create'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.exams.show', $exam))->assertForbidden();
            $this->actingAs($user)->get(route('admin.exams.edit', $exam))->assertForbidden();
            $this->actingAs($user)->get(route('admin.exams.results', $exam))->assertForbidden();
            $this->actingAs($user)->get(route('admin.results.index'))->assertForbidden();
        }
    }

    public function test_broad_non_exam_admin_group_roles_cannot_mutate_global_exam_records(): void
    {
        $chair = $this->userWithRole('program_chair');
        $exam = $this->examFixture();

        $this->actingAs($chair)->post(route('admin.exams.store'), [
            'semester_id' => $exam->semester_id,
            'subject_id' => $exam->subject_id,
            'name' => 'Blocked Exam',
            'type' => 'internal',
            'exam_date' => now()->addWeek()->toDateString(),
            'total_marks' => 50,
            'passing_marks' => 20,
        ])->assertForbidden();

        $this->actingAs($chair)->put(route('admin.exams.update', $exam), [
            'semester_id' => $exam->semester_id,
            'subject_id' => $exam->subject_id,
            'name' => 'Changed By Chair',
            'type' => $exam->type,
            'exam_date' => $exam->exam_date->toDateString(),
            'total_marks' => $exam->total_marks,
            'passing_marks' => $exam->passing_marks,
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.exams.results.save', $exam), [
            'results' => [],
        ])->assertForbidden();

        $this->actingAs($chair)->delete(route('admin.exams.destroy', $exam))
            ->assertForbidden();

        $this->assertDatabaseMissing('exams', ['name' => 'Blocked Exam']);
        $this->assertSame('Access Controlled Exam', $exam->fresh()->name);
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    private function examFixture(): Exam
    {
        $semester = Semester::factory()->create();
        $subject = Subject::factory()->create(['is_active' => true]);

        return Exam::factory()->create([
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'program_id' => $subject->program_id,
            'name' => 'Access Controlled Exam',
            'type' => 'internal',
            'exam_date' => now()->subDay()->toDateString(),
            'total_marks' => 100,
            'passing_marks' => 40,
        ]);
    }
}
