<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeeDemand;
use App\Models\FeeStructure;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentPortalGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithStudent(): array
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('parent');
        $student = Student::factory()->create();
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        return [$user, $student];
    }

    public function test_parent_dashboard_uses_fee_demands_for_priority_and_balance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 0,
            'final_amount' => 50000,
            'penalty_amount' => 5000,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertStatus(200)
            ->assertSee('Parent Priority')
            ->assertSee('Fee demand is overdue')
            ->assertSee('Rs. 55,000')
            ->assertSee('Open demands: 1')
            ->assertDontSee('999,999');
    }

    public function test_parent_fee_page_shows_demand_breakdown_and_overdue_guidance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id, 'name' => 'Term 1']);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 45000,
            'scholarship_deduction' => 5000,
            'final_amount' => 40000,
            'penalty_amount' => 1000,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->actingAs($user)
            ->get(route('parent.children.fees', $student))
            ->assertStatus(200)
            ->assertSee('Fee follow-up needed')
            ->assertSee('Term 1')
            ->assertSee('Rs. 41,000')
            ->assertSee('Overdue')
            ->assertDontSee('999,999');
    }

    public function test_parent_attendance_and_results_use_canonical_enrolled_subjects_only(): void
    {
        [$user, $student, $semester, $enrolledSubject, $unenrolledSubject] = $this->parentAcademicFixture();

        $this->actingAs($user)
            ->get(route('parent.children.attendance', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('100%')
            ->assertDontSee('Parent Unenrolled Subject');

        $this->actingAs($user)
            ->get(route('parent.children.results', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('88')
            ->assertDontSee('Parent Unenrolled Subject');
    }

    private function parentAcademicFixture(): array
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('parent');
        $program = Program::factory()->create(['is_active' => true]);
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        $enrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Parent Enrolled Subject',
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Parent Unenrolled Subject',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        foreach ([$enrolledSubject, $unenrolledSubject] as $subject) {
            $entry = TimetableEntry::factory()->create([
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'course_id' => $course->id,
                'term_id' => $term->id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'timetable_slot_id' => TimetableSlot::factory()->create()->id,
                'day_of_week' => now()->dayOfWeekIso,
                'is_active' => true,
            ]);
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->toDateString(),
                'status' => 'present',
            ]);
            $exam = Exam::factory()->create([
                'program_id' => $program->id,
                'term_id' => $term->id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'name' => $subject->name . ' Exam',
                'total_marks' => 100,
            ]);
            ExamResult::factory()->create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'marks_obtained' => $subject->id === $enrolledSubject->id ? 88 : 66,
                'is_absent' => false,
            ]);
        }

        return [$user, $student, $semester, $enrolledSubject, $unenrolledSubject];
    }
}
