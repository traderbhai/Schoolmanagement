<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV054Test extends TestCase
{
    use RefreshDatabase;

    public function test_substitution_recommendation_ranks_conflict_free_available_expert_backup_faculty(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'SUB', 'name' => 'Substitution Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'SUBP', 'name' => 'Substitution Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'SUBP-26', 'name' => 'SUBP 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'SUB501', 'name' => 'Substitution Analytics', 'credits' => 3, 'is_active' => true]);
        $slot = TimetableSlot::firstOrCreate(['name' => 'Substitution Test Slot'], ['start_time' => '10:00', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 2, 'is_active' => true]);
        $room = Classroom::firstOrCreate(['room_number' => 'SUB-101'], ['name' => 'Substitution Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);

        $original = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $clashing = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $best = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'Substitution Analytics Group A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
        ]);

        AcademicPmcGroupFacultyAssignment::create(['course_group_id' => $group->id, 'teacher_id' => $original->id, 'assignment_role' => 'primary', 'approval_status' => 'pmc_approved', 'weekly_hours' => 3, 'assigned_by' => $chair->id]);
        AcademicPmcGroupFacultyAssignment::create(['course_group_id' => $group->id, 'teacher_id' => $best->id, 'assignment_role' => 'backup', 'approval_status' => 'pmc_approved', 'weekly_hours' => 0, 'is_backup' => true, 'assigned_by' => $chair->id]);

        AcademicPmcFacultyPreference::create(['teacher_id' => $clashing->id, 'term_id' => $term->id, 'faculty_type' => 'regular', 'available_days' => [2], 'preferred_slots' => [$slot->id], 'unavailable_slots' => [], 'max_classes_per_day' => 4, 'max_weekly_load' => 18, 'subject_expertise' => ['SUB501']]);
        AcademicPmcFacultyPreference::create(['teacher_id' => $best->id, 'term_id' => $term->id, 'faculty_type' => 'regular', 'available_days' => [2], 'preferred_slots' => [$slot->id], 'unavailable_slots' => [], 'max_classes_per_day' => 4, 'max_weekly_load' => 18, 'subject_expertise' => ['SUB501']]);

        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'Substitution ranking run', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'timetable_version_id' => $version->id, 'strategy' => 'faculty_balanced', 'status' => 'published', 'created_by' => $chair->id]);
        $targetItem = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $original->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
            'confidence' => 90,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => null,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id])->id,
            'teacher_id' => $clashing->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
            'confidence' => 90,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.substitution-intelligence.recommend'), [
            'course_group_id' => $group->id,
            'original_teacher_id' => $original->id,
            'substitution_date' => now()->next('Tuesday')->toDateString(),
        ])->assertRedirect();

        $recommendation = AcademicPmcSubstitutionRecommendation::where('course_group_id', $group->id)->latest()->firstOrFail();

        $this->assertSame($best->id, $recommendation->substitute_teacher_id);
        $this->assertSame($targetItem->id, $recommendation->pmc_generation_item_id);
        $this->assertContains('assigned_backup_for_group', $recommendation->reasons);
        $this->assertContains('subject_expertise_match', $recommendation->reasons);
        $this->assertContains('no_same_slot_conflict', $recommendation->reasons);
        $this->assertSame('clear', $recommendation->conflict_checks['faculty']);
        $this->assertNotEmpty($recommendation->conflict_checks['ranked_candidates']);
    }

    public function test_substitution_recommendation_ignores_draft_generated_candidate_clashes(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'SUBD', 'name' => 'Draft Substitution Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'SUBD', 'name' => 'Draft Substitution Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'SUBD501', 'name' => 'Draft Clash Substitution', 'is_active' => true]);
        $slot = TimetableSlot::factory()->create(['name' => 'Draft Clash Slot', 'sort_order' => 3, 'is_active' => true]);
        $room = Classroom::factory()->create(['room_number' => 'SUBD-101', 'type' => 'lecture', 'is_active' => true]);
        $original = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $candidate = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Draft Clash Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
        ]);
        AcademicPmcGroupFacultyAssignment::create(['course_group_id' => $group->id, 'teacher_id' => $candidate->id, 'assignment_role' => 'backup', 'approval_status' => 'pmc_approved', 'weekly_hours' => 0, 'is_backup' => true, 'assigned_by' => $chair->id]);
        AcademicPmcFacultyPreference::create(['teacher_id' => $candidate->id, 'term_id' => $term->id, 'faculty_type' => 'regular', 'available_days' => [2], 'preferred_slots' => [$slot->id], 'unavailable_slots' => [], 'max_classes_per_day' => 4, 'max_weekly_load' => 18, 'subject_expertise' => ['SUBD501']]);

        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'Draft clash substitution run', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'timetable_version_id' => $version->id, 'strategy' => 'faculty_balanced', 'status' => 'published', 'created_by' => $chair->id]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $original->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => null,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id])->id,
            'teacher_id' => $candidate->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.substitution-intelligence.recommend'), [
            'course_group_id' => $group->id,
            'original_teacher_id' => $original->id,
            'substitution_date' => now()->next('Tuesday')->toDateString(),
        ])->assertRedirect();

        $recommendation = AcademicPmcSubstitutionRecommendation::where('course_group_id', $group->id)->latest()->firstOrFail();

        $this->assertSame($candidate->id, $recommendation->substitute_teacher_id);
        $this->assertContains('no_same_slot_conflict', $recommendation->reasons);
        $this->assertSame('clear', $recommendation->conflict_checks['faculty']);
    }
}
