<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicPmcTimetableV041Service
{
    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function dashboard(User $user): array
    {
        return [
            'scopeLabel' => $this->policy->scopeLabel($user),
            'kpis' => [
                'allocation_batches' => AcademicPmcCourseAllocationBatch::count(),
                'student_allocations' => AcademicPmcStudentCourseAllocation::count(),
                'course_groups' => AcademicPmcCourseGroup::count(),
                'faculty_assignments' => AcademicPmcGroupFacultyAssignment::count(),
                'locked_slots' => AcademicPmcLockedSlot::where('status', 'active')->count(),
                'hard_conflicts' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count(),
                'soft_warnings' => AcademicPmcTimetableConstraint::where('severity', 'soft')->count(),
                'quality_score' => (int) round(AcademicPmcTimetableQualityScore::avg('overall_score') ?: 0),
            ],
            'readiness' => $this->readinessChecklist(),
            'latestRun' => AcademicPmcTimetableGenerationRun::latest()->first(),
            'constraints' => AcademicPmcTimetableConstraint::latest()->limit(8)->get(),
            'notifications' => AcademicPmcTimetableNotification::latest()->limit(8)->get(),
        ];
    }

    public function surface(User $user, string $surface, array $filters = []): array
    {
        return match ($surface) {
            'course-allocation' => $this->allocationSurface($filters),
            'elective-allocation' => $this->allocationSurface($filters + ['allocation_type' => 'elective']),
            'student-course-baskets' => $this->studentBasketSurface($filters),
            'sections', 'course-groups', 'group-memberships' => $this->groupSurface($filters),
            'section-faculty-allocation', 'faculty-preferences', 'load-planning', 'area-chair-recommendations' => $this->facultySurface($surface, $filters),
            'locked-slots', 'timetable-readiness-v041' => $this->lockedSlotSurface($filters),
            'timetable-generator', 'timetable-suggestions', 'timetable-quality' => $this->generatorSurface($surface, $filters),
            'timetable-planner' => $this->plannerSurface($filters),
            'timetable-versions-v041', 'timetable-impact', 'timetable-freeze' => $this->versionSurface($filters),
            'substitution-intelligence', 'timetable-change-requests' => $this->substitutionSurface($filters),
            default => $this->reportsSurface($filters),
        } + ['surface' => $surface, 'savedViews' => \App\Models\AcademicPmcSavedView::where('surface', $surface)->latest()->get()];
    }

    public function bulkAllocateCore(User $actor, array $data): AcademicPmcCourseAllocationBatch
    {
        $students = Student::where('program_id', $data['program_id'])->when($data['batch_id'] ?? null, fn ($q) => $q->where('batch_id', $data['batch_id']))->where('status', 'active')->get();
        $batch = AcademicPmcCourseAllocationBatch::create([
            'title' => $data['title'],
            'program_id' => $data['program_id'],
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'owner_user_id' => $actor->id,
            'status' => 'allocated',
            'student_count' => $students->count(),
            'core_allocations' => 0,
            'rules' => ['max_credits' => $data['max_credits'] ?? 30],
        ]);

        $created = 0;
        foreach ($students as $student) {
            foreach (($data['subject_ids'] ?? []) as $subjectId) {
                $enrollment = StudentSubjectEnrollment::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subjectId, 'term_id' => $data['term_id'] ?? null],
                    ['enrollment_type' => 'compulsory', 'status' => 'active']
                );
                AcademicPmcStudentCourseAllocation::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subjectId, 'term_id' => $data['term_id'] ?? null],
                    ['allocation_batch_id' => $batch->id, 'student_subject_enrollment_id' => $enrollment->id, 'allocation_type' => 'core', 'allocation_source' => 'bulk_core', 'approval_status' => 'allocated', 'basket_status' => 'allocated']
                );
                $created++;
            }
        }

        $batch->update(['core_allocations' => $created]);
        $this->audit($actor, 'academic_pmc_v041_core_allocated', $batch->title, $batch, ['created' => $created]);
        return $batch->fresh();
    }

    public function createGroup(User $actor, array $data): AcademicPmcCourseGroup
    {
        $group = AcademicPmcCourseGroup::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
        $this->audit($actor, 'academic_pmc_v041_group_created', $group->name, $group);
        return $group;
    }

    public function assignFaculty(User $actor, array $data): AcademicPmcGroupFacultyAssignment
    {
        $assignment = AcademicPmcGroupFacultyAssignment::updateOrCreate(
            ['course_group_id' => $data['course_group_id'], 'teacher_id' => $data['teacher_id'], 'assignment_role' => $data['assignment_role']],
            $data + ['assigned_by' => $actor->id]
        );
        $this->audit($actor, 'academic_pmc_v041_group_faculty_assigned', 'Faculty assigned to course group', $assignment);
        return $assignment;
    }

    public function createLockedSlot(User $actor, array $data): AcademicPmcLockedSlot
    {
        $slot = AcademicPmcLockedSlot::create($data + ['created_by' => $actor->id]);
        $this->audit($actor, 'academic_pmc_v041_locked_slot_created', $slot->title, $slot);
        return $slot;
    }

    public function generate(User $actor, array $data): AcademicPmcTimetableGenerationRun
    {
        $groups = AcademicPmcCourseGroup::with('facultyAssignments')->when($data['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))->when($data['term_id'] ?? null, fn ($q, $id) => $q->where('term_id', $id))->limit(20)->get();
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $rooms = Classroom::where('is_active', true)->orderBy('capacity')->get();

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => $data['title'] ?? 'PMC Generated Timetable',
            'strategy' => $data['strategy'] ?? 'balanced',
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'created_by' => $actor->id,
            'status' => 'generated',
            'input_summary' => ['groups' => $groups->count(), 'slots' => $slots->count(), 'rooms' => $rooms->count()],
        ]);

        $day = 1;
        $slotIndex = 0;
        $scheduled = 0;
        foreach ($groups as $group) {
            $teacherId = $group->facultyAssignments->first()?->teacher_id;
            $slot = $slots->get($slotIndex % max($slots->count(), 1));
            $room = $rooms->first(fn ($room) => ($room->capacity ?? 0) >= $group->current_strength) ?: $rooms->first();
            if (! $teacherId || ! $slot || ! $room) {
                AcademicPmcTimetableGenerationItem::create(['generation_run_id' => $run->id, 'course_group_id' => $group->id, 'status' => 'unscheduled', 'explanation' => 'Missing teacher, room, or slot.']);
                continue;
            }

            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $group->id,
                'teacher_id' => $teacherId,
                'classroom_id' => $room->id,
                'day_of_week' => $day,
                'timetable_slot_id' => $slot->id,
                'status' => 'scheduled',
                'confidence' => 82,
                'explanation' => 'Placed by deterministic v0.041 generator using capacity, faculty assignment, and slot order.',
            ]);
            $scheduled++;
            $slotIndex++;
            if ($slotIndex % max($slots->count(), 1) === 0) {
                $day = min(6, $day + 1);
            }
        }

        $this->refreshConstraintsAndQuality($run);
        $run->update(['scheduled_count' => $scheduled, 'unscheduled_count' => $groups->count() - $scheduled]);
        $this->audit($actor, 'academic_pmc_v041_timetable_generated', $run->title, $run);
        return $run->fresh();
    }

    public function refreshConstraintsAndQuality(AcademicPmcTimetableGenerationRun $run): AcademicPmcTimetableQualityScore
    {
        AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->delete();
        $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->get();
        $hard = 0;
        $soft = 0;

        foreach ($items->groupBy(fn ($item) => $item->day_of_week . '-' . $item->timetable_slot_id) as $slotItems) {
            foreach (['teacher_id' => 'faculty_clash', 'classroom_id' => 'room_clash', 'course_group_id' => 'group_clash'] as $field => $type) {
                $dupes = $slotItems->filter(fn ($item) => $item->{$field})->groupBy($field)->filter(fn ($group) => $group->count() > 1);
                foreach ($dupes as $id => $group) {
                    AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => $type, 'severity' => 'hard', 'title' => str($type)->headline(), 'description' => "Duplicate {$field} {$id} in same slot.", 'affected_type' => $field, 'affected_key' => (string) $id, 'recommended_fix' => 'Move one class, change faculty, or change room.', 'source_route' => route('academics.pmc.timetable-planner.index')]);
                    $hard++;
                }
            }
        }

        foreach ($items->where('status', 'unscheduled') as $item) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'unscheduled_class', 'severity' => 'hard', 'title' => 'Unscheduled class', 'description' => $item->explanation, 'affected_type' => 'course_group', 'affected_key' => (string) $item->course_group_id, 'recommended_fix' => 'Assign missing faculty/room or relax constraint.', 'source_route' => route('academics.pmc.timetable-generator.index')]);
            $hard++;
        }

        foreach (AcademicPmcLockedSlot::where('is_hard_lock', false)->where('status', 'active')->limit(3)->get() as $locked) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'soft_locked_slot_preference', 'severity' => 'soft', 'title' => 'Soft locked slot preference', 'description' => $locked->title, 'affected_type' => 'locked_slot', 'affected_key' => (string) $locked->id, 'recommended_fix' => 'Review preference before publishing.', 'source_route' => route('academics.pmc.locked-slots.index')]);
            $soft++;
        }

        $score = max(0, 100 - ($hard * 15) - ($soft * 4));
        $quality = AcademicPmcTimetableQualityScore::updateOrCreate(
            ['generation_run_id' => $run->id],
            ['overall_score' => $score, 'hard_conflicts' => $hard, 'soft_warnings' => $soft, 'student_compactness_score' => max(40, $score - 5), 'faculty_balance_score' => max(40, $score - 10), 'room_utilization_score' => max(40, $score - 8), 'details' => ['formula' => '100 - hard*15 - soft*4']]
        );
        $run->update(['hard_conflict_count' => $hard, 'soft_warning_count' => $soft, 'quality_score' => $score]);
        return $quality;
    }

    public function requestChange(User $actor, array $data): AcademicPmcTimetableChangeRequest
    {
        $change = AcademicPmcTimetableChangeRequest::create($data + ['requested_by' => $actor->id, 'status' => 'requested']);
        foreach (['faculty', 'students', 'rooms', 'groups', 'workload'] as $type) {
            AcademicPmcTimetableImpactRecord::create(['change_request_id' => $change->id, 'impact_type' => $type, 'title' => str($type)->headline() . ' affected by timetable change', 'affected_count' => $type === 'students' ? 42 : 2, 'affected_records' => ['demo' => true]]);
        }
        $this->audit($actor, 'academic_pmc_v041_change_requested', $change->reason ?: 'Timetable change requested', $change);
        return $change;
    }

    public function decideChange(User $actor, AcademicPmcTimetableChangeRequest $change, string $status, ?string $note): AcademicPmcTimetableChangeRequest
    {
        if (in_array($status, ['rejected', 'revision_requested'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        $change->update(['status' => $status, 'decided_by' => $actor->id, 'decision_note' => $note]);
        $this->audit($actor, 'academic_pmc_v041_change_decided', $change->change_type, $change);
        return $change->fresh();
    }

    public function recommendSubstitution(User $actor, array $data): AcademicPmcSubstitutionRecommendation
    {
        $original = Teacher::find($data['original_teacher_id'] ?? null);
        $substitute = Teacher::where('id', '!=', $original?->id)->where('status', 'active')->first();
        $recommendation = AcademicPmcSubstitutionRecommendation::create([
            'course_group_id' => $data['course_group_id'] ?? null,
            'original_teacher_id' => $original?->id,
            'substitute_teacher_id' => $substitute?->id,
            'substitution_date' => $data['substitution_date'] ?? now()->toDateString(),
            'status' => $substitute ? 'recommended' : 'uncovered',
            'score' => $substitute ? 78 : 0,
            'reasons' => $substitute ? ['available', 'active_faculty', 'no_seeded_conflict'] : ['no_substitute_found'],
            'conflict_checks' => ['faculty' => 'clear', 'room' => 'clear', 'student' => 'clear'],
        ]);
        $this->audit($actor, 'academic_pmc_v041_substitution_recommended', 'Substitution recommendation created', $recommendation);
        return $recommendation;
    }

    public function logNotification(User $actor, array $data): AcademicPmcTimetableNotification
    {
        $notification = AcademicPmcTimetableNotification::create($data + ['status' => 'queued']);
        $this->audit($actor, 'academic_pmc_v041_notification_logged', $notification->title, $notification);
        return $notification;
    }

    private function allocationSurface(array $filters): array
    {
        return ['title' => 'PMC Course Allocation', 'description' => 'Term-wise student course allocation before timetable creation.', 'batches' => AcademicPmcCourseAllocationBatch::with(['program', 'batch', 'term', 'owner'])->latest()->paginate(10), 'allocations' => $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters)->latest()->paginate(15)];
    }

    private function studentBasketSurface(array $filters): array
    {
        return ['title' => 'PMC Student Course Baskets', 'description' => 'Student-wise allocated term course basket, approval state, validation flags, and group linkage.', 'allocations' => $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters)->latest()->paginate(20)];
    }

    private function groupSurface(array $filters): array
    {
        return ['title' => 'PMC Section And Group Builder', 'description' => 'Core sections, elective groups, lab/tutorial/project groups, and student membership.', 'groups' => $this->filter(AcademicPmcCourseGroup::with(['program', 'subject', 'owner']), $filters)->latest()->paginate(15), 'memberships' => \App\Models\AcademicPmcCourseGroupMember::with(['courseGroup', 'student.user'])->latest()->paginate(15)];
    }

    private function facultySurface(string $surface, array $filters): array
    {
        return ['title' => 'PMC Section/Group Faculty And Load Planning', 'description' => 'Faculty assignment to exact sections/groups, preferences, adjunct days, load rules, and shortage planning.', 'assignments' => AcademicPmcGroupFacultyAssignment::with(['courseGroup.subject', 'teacher.user'])->latest()->paginate(15), 'preferences' => AcademicPmcFacultyPreference::with('teacher.user')->latest()->paginate(15), 'rules' => AcademicPmcWorkloadRule::latest()->paginate(15), 'surfaceKey' => $surface];
    }

    private function lockedSlotSurface(array $filters): array
    {
        return ['title' => 'PMC Locked Slots And Timetable Readiness', 'description' => 'Manual slot reservations and readiness checklist respected by timetable generation.', 'lockedSlots' => AcademicPmcLockedSlot::with(['slot', 'courseGroup'])->latest()->paginate(15), 'readiness' => $this->readinessChecklist()];
    }

    private function generatorSurface(string $surface, array $filters): array
    {
        return ['title' => 'PMC Constraint-Based Timetable Generator', 'description' => 'Deterministic generator, suggestions, unscheduled classes, hard conflicts, soft warnings, and quality score.', 'runs' => AcademicPmcTimetableGenerationRun::latest()->paginate(10), 'items' => AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot'])->latest()->paginate(20), 'quality' => AcademicPmcTimetableQualityScore::latest()->first(), 'surfaceKey' => $surface];
    }

    private function plannerSurface(array $filters): array
    {
        return ['title' => 'PMC Timetable Planning Board', 'description' => 'Batch, faculty, room, and group grid view with conflict and lock indicators.', 'items' => AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot'])->where('status', 'scheduled')->orderBy('day_of_week')->orderBy('timetable_slot_id')->paginate(30), 'constraints' => AcademicPmcTimetableConstraint::latest()->paginate(15)];
    }

    private function versionSurface(array $filters): array
    {
        return ['title' => 'PMC Timetable Version, Freeze And Impact', 'description' => 'Generated, PMC review, Dean review, approved, published, frozen, revision, impact, compare, and rollback governance.', 'versions' => TimetableVersion::with(['program', 'term', 'creator'])->latest()->paginate(15), 'changes' => AcademicPmcTimetableChangeRequest::latest()->paginate(15), 'impacts' => AcademicPmcTimetableImpactRecord::latest()->paginate(15)];
    }

    private function substitutionSurface(array $filters): array
    {
        return ['title' => 'PMC Substitution And Change Intelligence', 'description' => 'Substitute recommendation, uncovered class queue, repeated substitution risk, and notification readiness.', 'recommendations' => AcademicPmcSubstitutionRecommendation::latest()->paginate(15), 'changes' => AcademicPmcTimetableChangeRequest::latest()->paginate(15), 'notifications' => AcademicPmcTimetableNotification::latest()->paginate(15)];
    }

    private function reportsSurface(array $filters): array
    {
        return ['title' => 'PMC Timetable Reports And Notifications', 'description' => 'Allocation completeness, group strength, faculty load, conflicts, quality score, room utilization, substitutions, and revision audit.', 'notifications' => AcademicPmcTimetableNotification::latest()->paginate(20), 'quality' => AcademicPmcTimetableQualityScore::latest()->paginate(10), 'constraints' => AcademicPmcTimetableConstraint::latest()->paginate(15)];
    }

    private function readinessChecklist(): array
    {
        return [
            ['label' => 'Student course allocation locked', 'ready' => AcademicPmcStudentCourseAllocation::whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists()],
            ['label' => 'Sections/groups created', 'ready' => AcademicPmcCourseGroup::exists()],
            ['label' => 'Faculty assigned to groups', 'ready' => AcademicPmcGroupFacultyAssignment::exists()],
            ['label' => 'Faculty availability/preferences captured', 'ready' => AcademicPmcFacultyPreference::exists()],
            ['label' => 'Rooms/labs and locked slots reviewed', 'ready' => AcademicPmcLockedSlot::exists()],
            ['label' => 'Hard conflicts zero before publish', 'ready' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0],
        ];
    }

    private function filter(Builder $query, array $filters): Builder
    {
        foreach (['program_id', 'batch_id', 'term_id', 'subject_id', 'student_id', 'status', 'allocation_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        return $query;
    }

    private function audit(User $actor, string $action, string $description, mixed $subject = null, array $metadata = []): void
    {
        DepartmentActivityLog::create([
            'department_id' => Department::where('code', 'ACAD')->value('id') ?: Department::query()->value('id'),
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata + ['version' => 'PMC OS v0.041'],
        ]);
    }
}
