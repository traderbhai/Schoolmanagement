<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
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

class TeacherDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(): Teacher
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Teacher Guide']);
        $user->assignRole('teacher');

        return Teacher::factory()->create(['user_id' => $user->id]);
    }

    private function makeAssignment(Teacher $teacher, array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'subject_id' => Subject::factory()->create()->id,
            'created_by' => $teacher->user_id,
            'title' => 'Case Analysis',
            'description' => 'Review the submitted case.',
            'max_marks' => 100,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ], $overrides));
    }

    public function test_teacher_dashboard_prioritizes_pending_grading(): void
    {
        $teacher = $this->makeTeacher();
        $assignment = $this->makeAssignment($teacher);
        $enrolledStudent = Student::factory()->create();
        $rogueStudent = Student::factory()->create();

        StudentSubjectEnrollment::create([
            'student_id' => $enrolledStudent->id,
            'subject_id' => $assignment->subject_id,
            'term_id' => $assignment->term_id,
            'status' => 'active',
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $enrolledStudent->id,
            'answer_text' => 'My submission',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $rogueStudent->id,
            'answer_text' => 'Rogue stale submission',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('Grade 1 pending submission')
            ->assertDontSee('Grade 2 pending submissions')
            ->assertSee('Owner: You')
            ->assertSee('Source: Submitted assignment work')
            ->assertSee('Review Submissions')
            ->assertSee(route('teacher.assignments.index'), false);
    }

    public function test_teacher_dashboard_shows_active_assignment_priority_when_no_grading_pending(): void
    {
        $teacher = $this->makeTeacher();
        $this->makeAssignment($teacher);

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('Monitor active assignments')
            ->assertSee('Owner: You')
            ->assertSee('Source: Published assignments')
            ->assertSee('View Assignments');
    }

    public function test_teacher_dashboard_shows_only_published_timetable_rows(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-22 09:00:00'));

        $teacher = $this->makeTeacher();
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['code' => 'TDASH']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        Semester::query()->update(['is_current' => false]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);

        foreach ([
            ['Published Dashboard Class', 'Published Teacher Room', 'published', null, 1],
            ['Draft Dashboard Class', 'Draft Teacher Room', 'draft', null, 2],
            ['Draft Version Dashboard Class', 'Draft Version Teacher Room', 'published', $draftVersion->id, 3],
        ] as [$subjectName, $roomName, $status, $versionId, $slotOrder]) {
            TimetableEntry::create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => Subject::factory()->create([
                    'program_id' => $program->id,
                    'term_number' => 1,
                    'name' => $subjectName,
                ])->id,
                'teacher_id' => $teacher->id,
                'classroom_id' => Classroom::factory()->create(['room_number' => 'TR-' . $slotOrder, 'name' => $roomName])->id,
                'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => $slotOrder])->id,
                'day_of_week' => now()->dayOfWeekIso,
                'is_active' => true,
                'status' => $status,
                'timetable_version_id' => $versionId,
            ]);
        }

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSeeText("Mark attendance for today's classes")
            ->assertSee('Owner: You')
            ->assertSee('Source: Published timetable')
            ->assertSee('1<span style="font-size:1rem;opacity:.7"> classes</span>', false)
            ->assertSee('Published Dashboard Class')
            ->assertSee('TDASH')
            ->assertSee('TR-1')
            ->assertDontSee('Draft Dashboard Class')
            ->assertDontSee('Draft Version Dashboard Class');
    }

    public function test_teacher_dashboard_has_empty_priority_state(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('No urgent teaching action due today')
            ->assertSee('Owner: You + Program office')
            ->assertSee('Source: Your teaching records')
            ->assertSee('No published timetable for your profile yet')
            ->assertSee('Only published classes assigned to your teacher profile appear here')
            ->assertSee('Review teacher profile')
            ->assertSee(route('teacher.profile'), false)
            ->assertSee('Upload Material')
            ->assertSee('View Timetable');
    }
}
