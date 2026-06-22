<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
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

class TeacherStudentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_student_list_scopes_canonical_enrollments_to_entry_term(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $termOne = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $termTwo = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 2,
            'name' => 'Term 2',
            'start_date' => now()->addMonths(5),
            'end_date' => now()->addMonths(9),
            'is_current' => false,
            'sort_order' => 2,
        ]);

        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'Shared Subject Across Terms',
        ]);

        $visibleStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $termOne->id,
            'roll_number' => 'TERM001',
        ]);
        $visibleStudent->user->forceFill(['name' => 'Correct Term Student'])->save();

        $wrongTermStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $termTwo->id,
            'roll_number' => 'TERM002',
        ]);
        $wrongTermStudent->user->forceFill(['name' => 'Wrong Term Student'])->save();

        StudentSubjectEnrollment::create([
            'student_id' => $visibleStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $termOne->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $wrongTermStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $termTwo->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => null,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee('Correct Term Student')
            ->assertDontSee('Wrong Term Student');
    }

    public function test_teacher_student_list_prefers_official_pmc_group_membership(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
            'is_current' => true,
            'sort_order' => 1,
        ]);

        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'Official Section Subject',
        ]);

        $sectionStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $term->id,
            'roll_number' => 'SEC001',
        ]);
        $sectionStudent->user->forceFill(['name' => 'Official Section Student'])->save();

        $otherSectionStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $term->id,
            'roll_number' => 'SEC002',
        ]);
        $otherSectionStudent->user->forceFill(['name' => 'Other Section Student'])->save();

        foreach ([$sectionStudent, $otherSectionStudent] as $student) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }

        $courseGroup = AcademicPmcCourseGroup::create([
            'name' => 'Section A',
            'group_type' => 'section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);
        AcademicPmcCourseGroupMember::create([
            'course_group_id' => $courseGroup->id,
            'student_id' => $sectionStudent->id,
            'status' => 'active',
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $teacherUser->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Teacher Roster Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $teacherUser->id,
            'status' => 'published',
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'course_group_id' => $courseGroup->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'scheduled',
            'official_status' => 'published',
        ]);

        $bridgeEntry = TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
            'pmc_generation_item_id' => $item->id,
        ]);
        $item->update(['operational_timetable_entry_id' => $bridgeEntry->id]);

        TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'is_active' => true,
            'status' => 'published',
        ]);

        Attendance::create([
            'student_id' => $sectionStudent->id,
            'timetable_entry_id' => $bridgeEntry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
            'marked_by' => $teacherUser->id,
        ]);
        Attendance::create([
            'student_id' => $sectionStudent->id,
            'timetable_entry_id' => $bridgeEntry->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacherUser->id,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee('Official Section Student')
            ->assertSee('50%')
            ->assertSee('1/2')
            ->assertDontSee('Other Section Student');
    }

    public function test_teacher_student_list_creates_safe_bridge_for_unbridged_official_pmc_session(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
            'is_current' => true,
            'sort_order' => 1,
        ]);

        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'Unbridged Official Roster Subject',
        ]);
        $student = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $term->id,
            'roll_number' => 'UB001',
        ]);
        $student->user->forceFill(['name' => 'Unbridged Official Student'])->save();

        $courseGroup = AcademicPmcCourseGroup::create([
            'name' => 'Unbridged Section A',
            'group_type' => 'section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);
        AcademicPmcCourseGroupMember::create([
            'course_group_id' => $courseGroup->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $teacherUser->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Unbridged Teacher Roster Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $teacherUser->id,
            'status' => 'published',
        ]);
        $slot = TimetableSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'course_group_id' => $courseGroup->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
        ]);

        $this->assertNull($item->operational_timetable_entry_id);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee('Unbridged Official Student')
            ->assertSee('No attendance marked yet');

        $bridge = TimetableEntry::where('pmc_generation_item_id', $item->id)->firstOrFail();
        $this->assertSame($bridge->id, $item->fresh()->operational_timetable_entry_id);
        $this->assertSame($semester->id, $bridge->semester_id);
        $this->assertSame('published', $bridge->status);
    }

    public function test_teacher_student_list_is_empty_without_published_entries(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $teacherUser->id]);
        Student::factory()->create()->user->forceFill(['name' => 'Unrelated Visible Risk'])->save();

        $response = $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSeeText('Roster source:')
            ->assertSeeText('published timetable for the current semester or term')
            ->assertSeeText('Academic setup must publish the current semester before teacher rosters can appear')
            ->assertDontSee('Unrelated Visible Risk');

        $this->assertCount(0, $response->viewData('students'));
    }

    public function test_teacher_student_list_shows_database_backed_attendance(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create(['name' => 'Faculty One']);
        $teacherUser->assignRole('teacher');

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
            'is_current' => true,
            'sort_order' => 1,
        ]);

        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
        ]);

        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
        ]);

        $student = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_semester' => 1,
            'current_term_id' => $term->id,
            'roll_number' => 'ROLL001',
        ]);
        $student->user->forceFill(['name' => 'Aarav Searchable'])->save();

        $otherStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_semester' => 1,
            'current_term_id' => $term->id,
            'roll_number' => 'ROLL002',
        ]);
        $otherStudent->user->forceFill(['name' => 'Bhavna Filtered'])->save();

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $otherStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $entry = TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'is_active' => true,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
            'marked_by' => $teacherUser->id,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacherUser->id,
        ]);

        $draftEntry = TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacherUser->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $teacherUser->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(4)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacherUser->id,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSeeText('Roster source:')
            ->assertSeeText('Search roster')
            ->assertSeeText('Showing 2 roster student(s)')
            ->assertSee($student->user->name)
            ->assertSee('50%')
            ->assertSee('1/2')
            ->assertSee('No attendance marked yet')
            ->assertDontSee('No records')
            ->assertDontSee(route('admin.students.show', $student), false)
            ->assertSee(route('teacher.students.index'), false)
            ->assertSee(route('notifications.index'), false);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index', ['search' => 'Aarav']))
            ->assertOk()
            ->assertSeeText('Showing 1 roster student(s) for "Aarav"')
            ->assertSeeText('Clear')
            ->assertSee('Aarav Searchable')
            ->assertDontSee('Bhavna Filtered');
    }
}
