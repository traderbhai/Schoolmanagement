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

class PmcTimetableStudentPortalService
{
    public const RESPONSIBILITY = 'Student-facing PMC timetable, course basket, elective choice, acknowledgement, and add/drop request workflow.';

    public function __construct(
        private AcademicPmcAccessPolicyService $policy,
        private PmcTimetableReadModelService $readModels,
    ) {}

    public function studentScopedTimetable(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $groupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)->where('status', 'active')->pluck('course_group_id');
        $items = $this->readModels->officialTimetableItemsQuery()
            ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->whereIn('course_group_id', $groupIds)
            ->when($filters['day_of_week'] ?? null, fn ($q, $day) => $q->where('day_of_week', $day))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->paginate(25)
            ->withQueryString();

        return [
            'title' => 'My PMC Group Timetable',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'items' => $items,
            'groupCount' => $groupIds->count(),
            'filters' => $filters,
            'mode' => 'student',
            'selectorOptions' => $this->readModels->selectorOptions(),
        ];
    }

    public function studentCourseBasketSelfService(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $allocations = AcademicPmcStudentCourseAllocation::with([
            'subject',
            'term',
            'allocationBatch',
            'groupMemberships.courseGroup.subject',
        ])
            ->where('student_id', $student->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('basket_status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('allocation_type', $type))
            ->orderByRaw('case when waitlisted = 1 then 1 else 0 end')
            ->orderBy('allocation_type')
            ->orderBy('priority_rank')
            ->paginate(20)
            ->withQueryString();

        $allocationIds = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->pluck('id');
        $groupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('course_group_id');

        $timetableItems = $this->readModels->officialTimetableItemsQuery()
            ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->whereIn('course_group_id', $groupIds)
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->limit(12)
            ->get();

        $acknowledgements = AcademicPmcStudentBasketAcknowledgement::with(['allocation.subject', 'decider'])
            ->where('student_id', $student->id)
            ->latest()
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString();

        $openRequests = AcademicPmcStudentBasketAcknowledgement::where('student_id', $student->id)
            ->whereIn('status', ['submitted', 'objection_submitted', 'under_review'])
            ->count();

        return [
            'title' => 'My Course Basket',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'student' => $student,
            'allocations' => $allocations,
            'timetableItems' => $timetableItems,
            'acknowledgements' => $acknowledgements,
            'allocationOptions' => AcademicPmcStudentCourseAllocation::with('subject')
                ->whereIn('id', $allocationIds)
                ->orderBy('allocation_type')
                ->get(),
            'metrics' => [
                'allocated' => AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('waitlisted', false)->count(),
                'waitlisted' => AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('waitlisted', true)->count(),
                'grouped' => $groupIds->count(),
                'classes' => $timetableItems->count(),
                'open_requests' => $openRequests,
            ],
            'filters' => $filters,
        ];
    }

    public function submitStudentBasketAcknowledgement(User $user, array $data, callable $audit): AcademicPmcStudentBasketAcknowledgement
    {
        $student = $user->student;
        abort_unless($student, 403);

        $allocation = null;
        if (! empty($data['student_course_allocation_id'])) {
            $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)
                ->findOrFail($data['student_course_allocation_id']);
        }

        $ack = AcademicPmcStudentBasketAcknowledgement::create([
            'student_id' => $student->id,
            'student_course_allocation_id' => $allocation?->id,
            'timetable_version_id' => $data['timetable_version_id'] ?? null,
            'generation_run_id' => $data['generation_run_id'] ?? null,
            'acknowledgement_type' => $data['acknowledgement_type'],
            'status' => in_array($data['acknowledgement_type'], ['objection', 'add_drop_request'], true)
                ? 'objection_submitted'
                : 'acknowledged',
            'reason' => $data['reason'] ?? null,
            'student_note' => $data['student_note'] ?? null,
            'submitted_at' => now(),
            'metadata' => [
                'source' => 'student_course_basket_v090',
                'student_user_id' => $user->id,
            ],
        ]);

        if ($allocation && in_array($data['acknowledgement_type'], ['objection', 'add_drop_request'], true)) {
            AcademicPmcCourseAllocationException::create([
                'student_course_allocation_id' => $allocation->id,
                'student_id' => $student->id,
                'subject_id' => $allocation->subject_id,
                'term_id' => $allocation->term_id,
                'exception_type' => $data['acknowledgement_type'] === 'add_drop_request' ? 'add_drop' : 'student_objection',
                'status' => 'pending',
                'credit_delta' => 0,
                'requires_dean_approval' => false,
                'reason' => $data['reason'] ?: ($data['student_note'] ?? 'Student-submitted basket request.'),
                'requested_by' => $user->id,
                'requested_at' => now(),
                'metadata' => [
                    'student_basket_acknowledgement_id' => $ack->id,
                    'source' => 'student_course_basket_v090',
                ],
            ]);
        }

        $audit($user, 'student_basket_acknowledgement_submitted', 'Student submitted PMC course basket acknowledgement/request.', $ack, [
            'student_id' => $student->id,
            'allocation_id' => $allocation?->id,
            'type' => $ack->acknowledgement_type,
        ]);

        return $ack;
    }

    public function studentElectiveChoicePortal(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $termId = $filters['term_id'] ?? $student->current_term_id ?? Term::where('program_id', $student->program_id)->where('is_current', true)->value('id');
        $window = ElectiveRegistrationWindow::where('program_id', $student->program_id)
            ->where('term_id', $termId)
            ->where('status', 'open')
            ->orderByDesc('closes_at')
            ->first();

        $subjects = Subject::where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('program_id', $student->program_id)->orWhereNull('program_id');
            })
            ->where(function ($query) {
                $query->where('type', 'elective')
                    ->orWhere('code', 'like', '%ELEC%')
                    ->orWhere('name', 'like', '%Elective%')
                    ->orWhere('name', 'like', '%Analytics%')
                    ->orWhere('name', 'like', '%Lab%');
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $choices = AcademicPmcElectiveChoice::with(['subject', 'term'])
            ->where('student_id', $student->id)
            ->when($termId, fn ($q, $id) => $q->where('term_id', $id))
            ->orderBy('preference_rank')
            ->paginate(15)
            ->withQueryString();

        return [
            'title' => 'My Elective Choices',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'student' => $student,
            'window' => $window,
            'isOpen' => $window?->isOpen() ?? false,
            'termId' => $termId,
            'subjects' => $subjects,
            'choices' => $choices,
            'terms' => Term::where('program_id', $student->program_id)->orderByDesc('id')->limit(20)->get(),
            'metrics' => [
                'submitted' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->count(),
                'allocated' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->where('status', 'allocated')->count(),
                'waitlisted' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->where('status', 'waitlisted')->count(),
                'max_selections' => $window?->max_selections ?? 0,
            ],
            'filters' => $filters,
        ];
    }

    public function submitStudentElectiveChoices(User $user, array $data, callable $audit): void
    {
        $student = $user->student;
        abort_unless($student, 403);

        $termId = $data['term_id'] ?? $student->current_term_id;
        $window = ElectiveRegistrationWindow::where('program_id', $student->program_id)
            ->where('term_id', $termId)
            ->where('status', 'open')
            ->orderByDesc('closes_at')
            ->first();
        abort_unless($window && $window->isOpen(), 422, 'The elective choice window is not open.');

        $subjectIds = array_values(array_filter(array_map('intval', $data['subject_ids'] ?? [])));
        if (count($subjectIds) !== count(array_unique($subjectIds))) {
            abort(422, 'Each elective preference must be unique.');
        }
        if (empty($subjectIds) || count($subjectIds) > (int) $window->max_selections) {
            abort(422, 'Submit between 1 and ' . $window->max_selections . ' elective choices.');
        }

        $validCount = Subject::whereIn('id', $subjectIds)
            ->where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('program_id', $student->program_id)->orWhereNull('program_id');
            })
            ->count();
        abort_unless($validCount === count($subjectIds), 422, 'One or more selected electives are not valid for your program.');

        foreach ($subjectIds as $index => $subjectId) {
            AcademicPmcElectiveChoice::updateOrCreate(
                ['student_id' => $student->id, 'term_id' => $termId, 'subject_id' => $subjectId],
                [
                    'program_id' => $student->program_id,
                    'batch_id' => $student->batch_id,
                    'preference_rank' => $index + 1,
                    'priority_score' => max(0, 100 - ($index * 10)),
                    'status' => 'submitted',
                    'choice_source' => 'student_self_service',
                    'decision_reason' => null,
                    'metadata' => [
                        'window_id' => $window->id,
                        'submitted_by_user_id' => $user->id,
                        'version' => 'PMC OS v0.091',
                    ],
                ]
            );
        }

        AcademicPmcElectiveChoice::where('student_id', $student->id)
            ->where('term_id', $termId)
            ->whereNotIn('subject_id', $subjectIds)
            ->whereIn('status', ['submitted', 'waitlisted'])
            ->update(['status' => 'withdrawn', 'decision_reason' => 'Withdrawn by updated student preference submission.']);

        $audit($user, 'student_elective_choices_submitted', 'Student submitted ranked elective choices.', $student, [
            'term_id' => $termId,
            'subject_ids' => $subjectIds,
            'window_id' => $window->id,
        ]);
    }

    public function reviewStudentBasketAcknowledgement(User $actor, AcademicPmcStudentBasketAcknowledgement $ack, string $status, ?string $note, callable $audit): AcademicPmcStudentBasketAcknowledgement
    {
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $ack->student?->program_id,
            'batch_id' => $ack->student?->batch_id,
            'term_id' => $ack->allocation?->term_id,
            'subject_id' => $ack->allocation?->subject_id,
        ]);

        $ack->update([
            'status' => $status,
            'pmc_note' => $note,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        $audit($actor, 'student_basket_acknowledgement_reviewed', 'PMC reviewed a student course basket acknowledgement/request.', $ack, [
            'student_id' => $ack->student_id,
            'status' => $status,
        ]);

        return $ack;
    }

}
