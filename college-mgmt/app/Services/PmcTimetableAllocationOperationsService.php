<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\ElectiveRegistrationWindow;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmcTimetableAllocationOperationsService
{
    public const RESPONSIBILITY = 'PMC course allocation exceptions, group adjustments, faculty acknowledgements, allocations, group building, and lock primitives.';

    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function requestCourseAllocationException(User $actor, array $data, callable $audit): AcademicPmcCourseAllocationException
    {
        $this->policy->authorizeWriteScope($actor, ['subject_id' => $data['subject_id'] ?? null, 'term_id' => $data['term_id'] ?? null]);
        $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->when($data['term_id'] ?? null, fn ($q, $termId) => $q->where('term_id', $termId))
            ->first();
        $flags = $this->allocationExceptionFlags($data, $allocation);

        $exception = AcademicPmcCourseAllocationException::create([
            'student_course_allocation_id' => $allocation?->id,
            'student_id' => $data['student_id'],
            'subject_id' => $data['subject_id'],
            'term_id' => $data['term_id'] ?? null,
            'exception_type' => $data['exception_type'],
            'status' => 'requested',
            'credit_delta' => (int) ($data['credit_delta'] ?? 0),
            'requires_dean_approval' => in_array('credit_overload', $flags, true) || in_array($data['exception_type'], ['improvement', 'audit'], true),
            'reason' => $data['reason'] ?? null,
            'validation_flags' => $flags,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.050'],
        ]);

        $audit($actor, 'academic_pmc_v050_course_allocation_exception_requested', 'Course allocation exception requested', $exception);
        return $exception->fresh();
    }

    public function decideCourseAllocationException(User $actor, AcademicPmcCourseAllocationException $exception, string $status, ?string $note, callable $audit): AcademicPmcCourseAllocationException
    {
        $this->policy->authorizeWriteScope($actor, ['subject_id' => $exception->subject_id, 'term_id' => $exception->term_id]);
        if (in_array($status, ['approved', 'rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        if ($exception->requires_dean_approval && $status === 'approved' && ! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            abort(403, 'Dean/Academic leadership approval is required for this exception.');
        }

        $exception->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            $this->applyCourseAllocationException($actor, $exception->fresh());
        }

        $audit($actor, 'academic_pmc_v050_course_allocation_exception_decided', 'Course allocation exception ' . $status, $exception);
        return $exception->fresh();
    }

    public function requestCourseGroupAdjustment(User $actor, array $data, callable $audit): AcademicPmcCourseGroupAdjustment
    {
        $group = AcademicPmcCourseGroup::findOrFail($data['course_group_id']);
        $target = ! empty($data['target_course_group_id']) ? AcademicPmcCourseGroup::findOrFail($data['target_course_group_id']) : null;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group->program_id,
            'batch_id' => $group->batch_id,
            'term_id' => $group->term_id,
            'subject_id' => $group->subject_id,
        ]);

        $delta = max(0, (int) ($data['strength_delta'] ?? 0));
        $type = $data['adjustment_type'];
        [$toStrength, $targetToStrength] = match ($type) {
            'split' => [max(0, $group->current_strength - $delta), $target ? $target->current_strength + $delta : $delta],
            'merge' => [0, $target ? $target->current_strength + $group->current_strength : $group->current_strength],
            'rebalance' => [max(0, $group->current_strength - $delta), $target ? $target->current_strength + $delta : 0],
            'move_student' => [max(0, $group->current_strength - 1), $target ? $target->current_strength + 1 : 1],
            'lock', 'unlock' => [$group->current_strength, $target?->current_strength ?? 0],
            default => [$group->current_strength, $target?->current_strength ?? 0],
        };

        $impact = [
            'from_group' => $group->name,
            'target_group' => $target?->name,
            'student_id' => $data['student_id'] ?? null,
            'strength_delta' => $delta,
            'capacity_warning' => $target ? $targetToStrength > $target->max_capacity : false,
        ];

        $adjustment = AcademicPmcCourseGroupAdjustment::create([
            'course_group_id' => $group->id,
            'target_course_group_id' => $target?->id,
            'student_id' => $data['student_id'] ?? null,
            'adjustment_type' => $type,
            'status' => 'requested',
            'from_strength' => $group->current_strength,
            'to_strength' => $toStrength,
            'target_from_strength' => $target?->current_strength ?? 0,
            'target_to_strength' => $targetToStrength,
            'requires_dean_approval' => in_array($type, ['merge', 'unlock'], true) || ($impact['capacity_warning'] ?? false),
            'reason' => $data['reason'] ?? null,
            'impact_summary' => $impact,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.051'],
        ]);

        $audit($actor, 'academic_pmc_v051_course_group_adjustment_requested', 'Course group adjustment requested', $adjustment);
        return $adjustment->fresh();
    }

    public function requestFacultyAssignmentAcknowledgement(User $actor, AcademicPmcGroupFacultyAssignment $assignment, callable $audit): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $group = $assignment->courseGroup;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);

        $ack = AcademicPmcFacultyAssignmentAcknowledgement::updateOrCreate(
            ['group_faculty_assignment_id' => $assignment->id, 'teacher_id' => $assignment->teacher_id],
            [
                'status' => 'pending',
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );

        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => null, 'check_type' => 'faculty_acknowledgements'],
            [
                'status' => 'warn',
                'severity' => 'medium',
                'title' => 'Faculty assignment acknowledgements',
                'description' => 'One or more faculty assignment acknowledgements are pending.',
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );

        $audit($actor, 'academic_pmc_v052_faculty_assignment_ack_requested', 'Faculty assignment acknowledgement requested', $ack);
        return $ack->fresh();
    }

    public function respondFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $responseType, ?string $note, array $constraints, callable $audit): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $teacher = $actor->teacher;
        abort_unless($teacher && $teacher->id === $ack->teacher_id, 403);
        if (in_array($responseType, ['accept_with_constraints', 'raise_concern', 'decline'], true) && ! $note) {
            abort(422, 'Faculty note is required.');
        }

        $status = $responseType === 'accept' ? 'accepted' : 'concern_raised';
        $ack->update([
            'status' => $status,
            'response_type' => $responseType,
            'faculty_note' => $note,
            'constraints_raised' => $constraints,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->refreshFacultyAcknowledgementPublishCheck();
        $audit($actor, 'academic_pmc_v052_faculty_assignment_ack_responded', 'Faculty assignment acknowledgement ' . $responseType, $ack);
        return $ack->fresh();
    }

    public function reviewFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $status, ?string $note, callable $audit): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['concern_accepted', 'revision_required', 'reassigned'], true) && ! $note) {
            abort(422, 'Review note is required.');
        }

        $ack->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        if ($status === 'accepted') {
            $ack->assignment?->update(['approval_status' => 'faculty_accepted']);
        }
        if (in_array($status, ['revision_required', 'reassigned'], true)) {
            $ack->assignment?->update(['approval_status' => $status]);
        }

        $this->refreshFacultyAcknowledgementPublishCheck();
        $audit($actor, 'academic_pmc_v052_faculty_assignment_ack_reviewed', 'Faculty assignment acknowledgement reviewed', $ack);
        return $ack->fresh();
    }

    public function decideCourseGroupAdjustment(User $actor, AcademicPmcCourseGroupAdjustment $adjustment, string $status, ?string $note, callable $audit): AcademicPmcCourseGroupAdjustment
    {
        $group = $adjustment->courseGroup;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);
        if (in_array($status, ['approved', 'rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        if ($adjustment->requires_dean_approval && $status === 'approved' && ! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            abort(403, 'Dean/Academic leadership approval is required for this group adjustment.');
        }

        $adjustment->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            $this->applyCourseGroupAdjustment($actor, $adjustment->fresh());
        }

        $audit($actor, 'academic_pmc_v051_course_group_adjustment_decided', 'Course group adjustment ' . $status, $adjustment);
        return $adjustment->fresh();
    }

    public function bulkAllocateCore(User $actor, array $data, callable $audit): AcademicPmcCourseAllocationBatch
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
        $audit($actor, 'academic_pmc_v041_core_allocated', $batch->title, $batch, ['created' => $created]);
        return $batch->fresh();
    }

    public function allocateElectives(User $actor, array $data, callable $audit): array
    {
        $capacity = max(1, (int) ($data['capacity_per_subject'] ?? 60));
        $maxElectivesPerStudent = max(1, (int) ($data['max_electives_per_student'] ?? 2));
        $termId = $data['term_id'] ?? null;
        $subjectIds = $data['subject_ids'] ?? [];

        $choices = AcademicPmcElectiveChoice::with('student')
            ->when($data['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($data['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id))
            ->when($termId, fn ($q, $id) => $q->where('term_id', $id))
            ->when($subjectIds, fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereIn('status', ['submitted', 'waitlisted'])
            ->orderBy('preference_rank')
            ->orderByDesc('priority_score')
            ->get();

        $batch = AcademicPmcCourseAllocationBatch::create([
            'title' => $data['title'] ?? 'PMC Elective Allocation',
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $termId,
            'owner_user_id' => $actor->id,
            'status' => 'allocated',
            'student_count' => $choices->pluck('student_id')->unique()->count(),
            'rules' => ['capacity_per_subject' => $capacity, 'strategy' => 'preference_priority_capacity'],
        ]);

        $allocated = 0;
        $waitlisted = 0;
        $choicesByStudent = $choices->groupBy('student_id');
        $allocatedPerStudent = AcademicPmcStudentCourseAllocation::query()
            ->where('allocation_type', 'elective')
            ->where('basket_status', 'allocated')
            ->where('waitlisted', false)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->count())
            ->toArray();

        $allocatedPerSubject = AcademicPmcStudentCourseAllocation::query()
            ->where('allocation_type', 'elective')
            ->where('waitlisted', false)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->count())
            ->toArray();

        foreach ($choicesByStudent as $studentId => $studentChoices) {
            $studentAllocated = (int) ($allocatedPerStudent[$studentId] ?? 0);
            $orderedChoices = $studentChoices->sortBy([
                ['preference_rank', 'asc'],
                ['priority_score', 'desc'],
            ]);

            foreach ($orderedChoices as $choice) {
                $subjectId = $choice->subject_id;
                $filled = (int) ($allocatedPerSubject[$subjectId] ?? 0);
                $canAllocateMoreElectives = $studentAllocated < $maxElectivesPerStudent;
                $hasRoom = $filled < $capacity;

                if (! $canAllocateMoreElectives) {
                    $choice->update(['status' => 'waitlisted', 'decision_reason' => 'Student reached max elective allocation limit.']);
                    AcademicPmcStudentCourseAllocation::updateOrCreate(
                        ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                        [
                            'allocation_batch_id' => $batch->id,
                            'student_subject_enrollment_id' => null,
                            'allocation_type' => 'elective',
                            'allocation_source' => 'choice_window',
                            'approval_status' => 'waitlisted',
                            'basket_status' => 'waitlisted',
                            'priority_rank' => $choice->preference_rank,
                            'waitlisted' => true,
                            'validation_flags' => ['max_electives_reached'],
                            'metadata' => ['choice_id' => $choice->id, 'priority_score' => $choice->priority_score],
                        ]
                    );
                    $waitlisted++;
                    continue;
                }

                $isAllocated = $hasRoom;
                $enrollment = null;

                if ($isAllocated) {
                    $enrollment = StudentSubjectEnrollment::firstOrCreate(
                        ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                        ['enrollment_type' => 'elective', 'status' => 'active']
                    );
                    $allocatedPerStudent[$studentId] = ++$studentAllocated;
                    $allocatedPerSubject[$subjectId] = $filled + 1;
                    $allocated++;
                } else {
                    $waitlisted++;
                }

                AcademicPmcStudentCourseAllocation::updateOrCreate(
                    ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                    [
                        'allocation_batch_id' => $batch->id,
                        'student_subject_enrollment_id' => $enrollment?->id,
                        'allocation_type' => 'elective',
                        'allocation_source' => 'choice_window',
                        'approval_status' => $isAllocated ? 'allocated' : 'waitlisted',
                        'basket_status' => $isAllocated ? 'allocated' : 'waitlisted',
                        'priority_rank' => $choice->preference_rank,
                        'waitlisted' => ! $isAllocated,
                        'validation_flags' => $isAllocated ? [] : ['capacity_full'],
                        'metadata' => ['choice_id' => $choice->id, 'priority_score' => $choice->priority_score],
                    ]
                );

                $choice->update([
                    'status' => $isAllocated ? 'allocated' : 'waitlisted',
                    'decision_reason' => $isAllocated ? 'Allocated by preference and capacity.' : 'Capacity full; placed on waitlist.',
                ]);
            }
        }

        $batch->update(['elective_allocations' => $allocated, 'conflict_count' => $waitlisted]);
        $audit($actor, 'academic_pmc_v042_electives_allocated', $batch->title, $batch, ['allocated' => $allocated, 'waitlisted' => $waitlisted]);

        return ['batch' => $batch->fresh(), 'allocated' => $allocated, 'waitlisted' => $waitlisted];
    }

    public function autoBuildGroups(User $actor, array $data, callable $audit): AcademicPmcGroupBuildRun
    {
        $max = max(1, (int) ($data['max_capacity'] ?? 60));
        $min = max(1, (int) ($data['min_capacity'] ?? 1));
        $groupType = $data['group_type'] ?? 'core_section';
        $warnings = [];

        $allocations = AcademicPmcStudentCourseAllocation::with('student')
            ->where('subject_id', $data['subject_id'])
            ->when($data['term_id'] ?? null, fn ($q, $id) => $q->where('term_id', $id))
            ->where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->orderBy('student_id')
            ->get();

        $run = AcademicPmcGroupBuildRun::create([
            'title' => $data['title'] ?? 'PMC Auto Group Build',
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'subject_id' => $data['subject_id'],
            'group_type' => $groupType,
            'strategy' => $data['strategy'] ?? 'balanced_capacity',
            'min_capacity' => $min,
            'max_capacity' => $max,
            'students_considered' => $allocations->count(),
            'created_by' => $actor->id,
        ]);

        $created = 0;
        foreach ($allocations->chunk($max) as $index => $chunk) {
            if ($chunk->count() < $min) {
                $warnings[] = "Group " . ($index + 1) . " has {$chunk->count()} students below minimum {$min}.";
            }

            $group = AcademicPmcCourseGroup::create([
                'name' => ($data['group_prefix'] ?? 'PMC Group') . ' ' . ($index + 1),
                'group_type' => $groupType,
                'program_id' => $data['program_id'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'owner_user_id' => $actor->id,
                'min_capacity' => $min,
                'max_capacity' => $max,
                'current_strength' => $chunk->count(),
                'status' => 'draft',
                'constraints' => ['build_run_id' => $run->id],
            ]);

            foreach ($chunk as $allocation) {
                AcademicPmcCourseGroupMember::updateOrCreate(
                    ['course_group_id' => $group->id, 'student_id' => $allocation->student_id],
                    ['student_course_allocation_id' => $allocation->id, 'status' => 'active', 'moved_by' => $actor->id]
                );
            }
            $created++;
        }

        $run->update(['groups_created' => $created, 'warnings_count' => count($warnings), 'warnings' => $warnings]);
        $audit($actor, 'academic_pmc_v042_groups_auto_built', $run->title, $run, ['groups_created' => $created]);

        return $run->fresh();
    }

    public function createGroup(User $actor, array $data, callable $audit): AcademicPmcCourseGroup
    {
        $group = AcademicPmcCourseGroup::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
        $audit($actor, 'academic_pmc_v041_group_created', $group->name, $group);
        return $group;
    }

    public function assignFaculty(User $actor, array $data, callable $audit): AcademicPmcGroupFacultyAssignment
    {
        $assignment = AcademicPmcGroupFacultyAssignment::updateOrCreate(
            ['course_group_id' => $data['course_group_id'], 'teacher_id' => $data['teacher_id'], 'assignment_role' => $data['assignment_role']],
            $data + ['assigned_by' => $actor->id]
        );
        $audit($actor, 'academic_pmc_v041_group_faculty_assigned', 'Faculty assigned to course group', $assignment);
        return $assignment;
    }

    public function createLockedSlot(User $actor, array $data, callable $audit): AcademicPmcLockedSlot
    {
        $slot = AcademicPmcLockedSlot::create($data + ['created_by' => $actor->id]);
        $audit($actor, 'academic_pmc_v041_locked_slot_created', $slot->title, $slot);
        return $slot;
    }

    private function allocationExceptionFlags(array $data, ?AcademicPmcStudentCourseAllocation $allocation): array
    {
        $flags = [];
        $type = $data['exception_type'];
        if ($type === 'drop' && ! $allocation) {
            $flags[] = 'drop_without_existing_allocation';
        }
        if ($type !== 'drop' && $allocation && ! in_array($allocation->basket_status, ['dropped', 'withdrawn'], true)) {
            $flags[] = 'duplicate_existing_allocation';
        }
        if ((int) ($data['credit_delta'] ?? 0) > 6) {
            $flags[] = 'credit_overload';
        }
        if (in_array($type, ['repeat', 'backlog'], true)) {
            $flags[] = 'repeat_backlog_priority';
        }
        if (in_array($type, ['improvement', 'audit'], true)) {
            $flags[] = 'dean_review_required';
        }

        return $flags;
    }

    private function applyCourseAllocationException(User $actor, AcademicPmcCourseAllocationException $exception): void
    {
        if ($exception->exception_type === 'drop') {
            $exception->allocation?->update([
                'approval_status' => 'drop_approved',
                'basket_status' => 'dropped',
                'override_reason' => $exception->decision_note,
                'metadata' => ['exception_id' => $exception->id, 'version' => 'PMC OS v0.050'],
            ]);
            return;
        }

        $enrollment = StudentSubjectEnrollment::firstOrCreate(
            ['student_id' => $exception->student_id, 'subject_id' => $exception->subject_id, 'term_id' => $exception->term_id],
            ['enrollment_type' => in_array($exception->exception_type, ['open_elective', 'audit'], true) ? 'elective' : 'compulsory', 'status' => 'active']
        );

        AcademicPmcStudentCourseAllocation::updateOrCreate(
            ['student_id' => $exception->student_id, 'subject_id' => $exception->subject_id, 'term_id' => $exception->term_id],
            [
                'student_subject_enrollment_id' => $enrollment->id,
                'allocation_type' => $exception->exception_type,
                'allocation_source' => 'exception_' . $exception->exception_type,
                'approval_status' => 'exception_approved',
                'basket_status' => 'approved',
                'waitlisted' => false,
                'override_reason' => $exception->decision_note,
                'validation_flags' => $exception->validation_flags,
                'metadata' => ['exception_id' => $exception->id, 'approved_by' => $actor->id, 'version' => 'PMC OS v0.050'],
            ]
        );
    }

    private function applyCourseGroupAdjustment(User $actor, AcademicPmcCourseGroupAdjustment $adjustment): void
    {
        $group = $adjustment->courseGroup;
        $target = $adjustment->targetCourseGroup;
        if (! $group) {
            return;
        }

        match ($adjustment->adjustment_type) {
            'lock' => $group->update(['is_locked' => true, 'status' => 'locked']),
            'unlock' => $group->update(['is_locked' => false, 'status' => 'ready']),
            'merge' => $this->mergeGroupIntoTarget($group, $target, $adjustment, $actor),
            'move_student' => $this->moveStudentBetweenGroups($group, $target, $adjustment, $actor),
            default => $this->applyStrengthAdjustment($group, $target, $adjustment),
        };
    }

    private function applyStrengthAdjustment(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment): void
    {
        $group->update(['current_strength' => $adjustment->to_strength, 'status' => $adjustment->adjustment_type === 'split' ? 'rebalanced' : $group->status]);
        if ($target) {
            $target->update(['current_strength' => $adjustment->target_to_strength, 'status' => 'rebalanced']);
        }
    }

    private function mergeGroupIntoTarget(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment, User $actor): void
    {
        if ($target) {
            AcademicPmcCourseGroupMember::where('course_group_id', $group->id)->get()->each(function (AcademicPmcCourseGroupMember $member) use ($target, $actor, $adjustment) {
                $existing = AcademicPmcCourseGroupMember::where('course_group_id', $target->id)->where('student_id', $member->student_id)->first();
                if ($existing) {
                    $existing->update(['moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note, 'status' => 'active']);
                    $member->delete();
                    return;
                }
                $member->update(['course_group_id' => $target->id, 'moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note]);
            });
            $target->update(['current_strength' => $adjustment->target_to_strength, 'status' => 'rebalanced']);
        }
        $group->update(['current_strength' => 0, 'status' => 'merged', 'is_locked' => true]);
    }

    private function moveStudentBetweenGroups(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment, User $actor): void
    {
        if (! $target || ! $adjustment->student_id) {
            return;
        }
        $sourceMember = AcademicPmcCourseGroupMember::where('course_group_id', $group->id)->where('student_id', $adjustment->student_id)->first();
        $targetMember = AcademicPmcCourseGroupMember::where('course_group_id', $target->id)->where('student_id', $adjustment->student_id)->first();
        if ($targetMember) {
            $targetMember->update(['moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note, 'status' => 'active']);
            $sourceMember?->delete();
        } else {
            $sourceMember?->update([
                'course_group_id' => $target->id,
                'moved_by' => $actor->id,
                'move_reason' => $adjustment->decision_note,
            ]);
        }
        $group->update(['current_strength' => $adjustment->to_strength]);
        $target->update(['current_strength' => $adjustment->target_to_strength]);
    }

    private function refreshFacultyAcknowledgementPublishCheck(): void
    {
        $open = AcademicPmcFacultyAssignmentAcknowledgement::whereIn('status', ['pending', 'concern_raised'])->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => null, 'check_type' => 'faculty_acknowledgements'],
            [
                'status' => $open === 0 ? 'pass' : 'warn',
                'severity' => $open === 0 ? 'info' : 'medium',
                'title' => 'Faculty assignment acknowledgements',
                'description' => $open === 0 ? 'Faculty assignment acknowledgements are clear.' : "{$open} faculty acknowledgement(s) are pending or have concerns.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );
    }

}
