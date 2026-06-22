<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardCanonicalTimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_timetable_pages_render_official_canonical_pmc_sessions(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $program = Program::factory()->create(['code' => 'ADM-TT']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Admin Timetable Canonical Subject',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Admin Timetable Group A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $admin->id,
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Admin Timetable Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $admin->id,
            'status' => 'published',
            'scheduled_count' => 1,
        ]);
        $teacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Admin Timetable Canonical Faculty'])->id,
        ]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Admin Timetable Canonical Slot',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'sort_order' => 1,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'ADM-TT-101'])->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'published',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.timetable.index', ['semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Admin Timetable Canonical Subject')
            ->assertSee('Admin Timetable Group A')
            ->assertSee('Admin Timetable Canonical Faculty')
            ->assertSee('ADM-TT-101')
            ->assertSee('PMC');

        $legacyCourse = Course::factory()->create(['code' => 'LEG-FILTER']);
        $this->actingAs($admin)
            ->get(route('admin.timetable.index', [
                'semester_id' => $semester->id,
                'course_id' => $legacyCourse->id,
            ]))
            ->assertOk()
            ->assertSee('Admin Timetable Canonical Subject')
            ->assertSee('Admin Timetable Group A');

        $bridgeCourse = Course::factory()->create(['code' => 'PMCG' . $group->id]);
        $this->actingAs($admin)
            ->get(route('admin.timetable.index', [
                'semester_id' => $semester->id,
                'course_id' => $bridgeCourse->id,
            ]))
            ->assertOk()
            ->assertSee('Admin Timetable Canonical Subject')
            ->assertSee('Admin Timetable Group A');

        $this->actingAs($admin)
            ->get(route('admin.timetable.teacher-view', ['semester_id' => $semester->id, 'teacher_id' => $teacher->id]))
            ->assertOk()
            ->assertSee('Admin Timetable Canonical Subject')
            ->assertSee('Admin Timetable Group A')
            ->assertSee('ADM-TT-101');

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertSee('1 periods')
            ->assertSee('Admin Timetable Canonical Subject')
            ->assertSee('Admin Timetable Group A')
            ->assertSee('ADM-TT-101')
            ->assertSee('PMC');
    }

    public function test_admin_dashboard_recent_timetable_prefers_official_pmc_sessions(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $program = Program::factory()->create(['code' => 'ADM-PMC']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $canonicalSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Admin Canonical Timetable Subject',
        ]);
        $legacySubject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Admin Stale Legacy Timetable Subject',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Admin Section A',
            'group_type' => 'section',
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $canonicalSubject->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Admin Dashboard Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $admin->id,
            'status' => 'published',
        ]);
        $teacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Admin Canonical Faculty'])->id,
        ]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Admin Canonical Slot',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'sort_order' => 1,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $canonicalSubject->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'CAN-101'])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
        ]);

        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => Course::factory()->create()->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $legacySubject->id,
            'teacher_id' => Teacher::factory()->create([
                'user_id' => User::factory()->create(['name' => 'Admin Legacy Faculty'])->id,
            ])->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'LEG-101'])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Canonical Timetable Subject')
            ->assertSee('Admin Section A')
            ->assertSee('Admin Canonical Faculty')
            ->assertSee('CAN-101')
            ->assertSee("Today's Classes", false)
            ->assertSee('1 periods')
            ->assertDontSee('Admin Stale Legacy Timetable Subject')
            ->assertDontSee('Admin Legacy Faculty')
            ->assertDontSee('LEG-101');
    }
}
