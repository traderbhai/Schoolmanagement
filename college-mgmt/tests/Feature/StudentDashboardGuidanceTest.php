<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\FeeDemand;
use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
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

class StudentDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create(['name' => 'Student Guidance Program']);
        $user = User::factory()->create(['name' => 'Student Guide']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
        ]);
    }

    public function test_dashboard_uses_fee_demands_for_outstanding_priority(): void
    {
        $student = $this->makeStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'final_amount' => 10000,
            'penalty_amount' => 2500,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 5000,
            'scholarship_deduction' => 0,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Clear pending fee balance')
            ->assertSee('Review Fees')
            ->assertSee('Rs. 12,500')
            ->assertDontSee('Rs. 17,500');
    }

    public function test_dashboard_includes_pending_hostel_fee_demands_in_outstanding_priority(): void
    {
        $student = $this->makeStudent();
        $student->update(['status' => 'active']);
        $block = HostelBlock::create([
            'name' => 'Dashboard Hostel Block',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => 'D-201',
            'floor' => 2,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 4500,
            'status' => 'occupied',
        ]);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Clear pending fee balance')
            ->assertSee('Review Fees')
            ->assertSee('Rs. 4,500')
            ->assertDontSee('No urgent academic action due today');
    }

    public function test_dashboard_prioritizes_upcoming_assignment_when_no_fee_or_attendance_blocker(): void
    {
        $student = $this->makeStudent();
        $subject = Subject::factory()->create(['program_id' => $student->program_id, 'name' => 'Business Analytics']);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Analytics Case Study',
            'description' => 'Submit the case analysis.',
            'max_marks' => 100,
            'due_at' => now()->addDays(2),
            'is_published' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Submit 1 upcoming assignment')
            ->assertSee('Open Assignments')
            ->assertSee('Analytics Case Study');
    }

    public function test_dashboard_today_classes_show_only_student_published_timetable_rows(): void
    {
        $student = $this->makeStudent();
        $term = Term::factory()->create([
            'program_id' => $student->program_id,
            'batch_id' => $student->batch_id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student->update(['current_term_id' => $term->id]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $teacherUser = User::factory()->create(['name' => 'Dashboard Faculty']);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $publishedSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Published Dashboard Subject',
        ]);
        $draftSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Draft Dashboard Subject',
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Draft Version Dashboard Subject',
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Unenrolled Dashboard Subject',
        ]);
        foreach ([$publishedSubject, $draftSubject, $draftVersionSubject] as $subject) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'status' => 'active',
            ]);
        }

        $draftVersion = TimetableVersion::create([
            'program_id' => $student->program_id,
            'term_id' => $term->id,
            'batch_id' => $student->batch_id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $today = now()->dayOfWeekIso;

        foreach ([
            [$publishedSubject, 'Dashboard Room', 'published', null],
            [$draftSubject, 'Draft Dashboard Room', 'draft', null],
            [$draftVersionSubject, 'Draft Version Dashboard Room', 'published', $draftVersion->id],
            [$unenrolledSubject, 'Unenrolled Dashboard Room', 'published', null],
        ] as [$subject, $roomName, $status, $versionId]) {
            TimetableEntry::create([
                'semester_id' => $semester->id,
                'course_id' => $student->course_id,
                'program_id' => $student->program_id,
                'batch_id' => $student->batch_id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'classroom_id' => Classroom::factory()->create(['name' => $roomName])->id,
                'timetable_slot_id' => TimetableSlot::factory()->create()->id,
                'day_of_week' => $today,
                'is_active' => true,
                'status' => $status,
                'timetable_version_id' => $versionId,
            ]);
        }

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSeeText("Attend today's scheduled classes")
            ->assertSee('Published Dashboard Subject')
            ->assertSee('Dashboard Faculty')
            ->assertSee('Dashboard Room')
            ->assertDontSee('Draft Dashboard Subject')
            ->assertDontSee('Draft Version Dashboard Subject')
            ->assertDontSee('Unenrolled Dashboard Subject');
    }

    public function test_dashboard_attendance_summary_ignores_draft_timetable_history(): void
    {
        $student = $this->makeStudent();
        $term = Term::factory()->create([
            'program_id' => $student->program_id,
            'batch_id' => $student->batch_id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student->update(['current_term_id' => $term->id]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Dashboard Attendance Subject',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $student->program_id,
            'term_id' => $term->id,
            'batch_id' => $student->batch_id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);

        $entries = [
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $student->course_id,
                'program_id' => $student->program_id,
                'batch_id' => $student->batch_id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'is_active' => true,
                'status' => 'published',
            ]),
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $student->course_id,
                'program_id' => $student->program_id,
                'batch_id' => $student->batch_id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'is_active' => true,
                'status' => 'draft',
            ]),
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $student->course_id,
                'program_id' => $student->program_id,
                'batch_id' => $student->batch_id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'is_active' => true,
                'status' => 'published',
                'timetable_version_id' => $draftVersion->id,
            ]),
        ];

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entries[0]->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
        ]);
        foreach (array_slice($entries, 1) as $entry) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays(2)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('100%')
            ->assertDontSee('Low Attendance Alert')
            ->assertDontSee('Recover attendance in 1 subject');
    }

    public function test_dashboard_has_clear_empty_priority_state(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('No urgent academic action due today')
            ->assertSee('Review Courses')
            ->assertSee('Need Help?');
    }
}
