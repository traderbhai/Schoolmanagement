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

class PmcTimetableFacultyReadinessService
{
    public const RESPONSIBILITY = 'PMC faculty availability, faculty load reviews, faculty suitability gates, and room readiness review operations.';

    public function __construct(
        private AcademicPmcAccessPolicyService $policy,
        private PmcTimetableReadModelService $readModels,
    ) {}

    public function facultyAvailabilitySurface(User $user, array $filters = []): array
    {
        $this->policy->authorizeRead($user);
        $termIds = $this->policy->scopedTermIds($user);

        return [
            'title' => 'PMC Faculty Availability Requests',
            'scopeLabel' => $this->policy->scopeLabel($user),
            'requests' => AcademicPmcFacultyAvailabilityRequest::with(['teacher.user', 'term', 'submitter', 'decider'])
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($termIds !== null, fn ($q) => is_array($termIds) ? $q->whereIn('term_id', $termIds) : $q)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'preferences' => AcademicPmcFacultyPreference::with('teacher.user')
                ->when($termIds !== null, fn ($q) => is_array($termIds) ? $q->whereIn('term_id', $termIds) : $q)
                ->latest()
                ->paginate(15, ['*'], 'preferences_page'),
            'selectorOptions' => $this->readModels->selectorOptions(),
        ];
    }

    public function facultyOwnAvailabilitySurface(User $user): array
    {
        $teacher = $user->teacher;
        abort_unless($teacher, 403);

        return [
            'title' => 'My PMC Availability',
            'scopeLabel' => $teacher->user?->name ?? 'Faculty',
            'teacher' => $teacher,
            'requests' => AcademicPmcFacultyAvailabilityRequest::with('term')->where('teacher_id', $teacher->id)->latest()->paginate(15),
            'preferences' => AcademicPmcFacultyPreference::where('teacher_id', $teacher->id)->latest()->get(),
            'selectorOptions' => $this->readModels->selectorOptions(),
        ];
    }

    public function selectorOptionsForFilters(): array
    {
        return $this->readModels->selectorOptions();
    }

    private function selectorOptions(): array
    {
        return $this->readModels->selectorOptions();
    }

    public function submitFacultyAvailability(User $actor, array $data, callable $audit): AcademicPmcFacultyAvailabilityRequest
    {
        $teacher = $actor->teacher;
        if ($teacher) {
            abort_if(! empty($data['teacher_id']) && (int) $data['teacher_id'] !== (int) $teacher->id, 403, 'Teacher users cannot submit availability for another teacher.');
        } elseif (! $this->policy->canWrite($actor)) {
            abort(403);
        }

        $requestedTeacherId = (int) ($data['teacher_id'] ?? 0);
        if (! $teacher) {
            abort_unless($requestedTeacherId > 0, 422, 'teacher_id is required.');
            $teacher = Teacher::findOrFail($requestedTeacherId);
        }

        if (! $actor->teacher) {
            $termIds = $this->policy->scopedTermIds($actor);
            $requestedTermId = (int) ($data['term_id'] ?? 0);
            if (is_array($termIds) && $requestedTermId > 0 && ! in_array($requestedTermId, $termIds, true)) {
                abort(403, 'The selected term is outside your scope.');
            }
        }

        $request = AcademicPmcFacultyAvailabilityRequest::create([
            'teacher_id' => $teacher->id,
            'term_id' => $data['term_id'] ?? null,
            'request_type' => $data['request_type'] ?? 'availability_update',
            'status' => 'submitted',
            'available_days' => $this->csvInts($data['available_days'] ?? []),
            'preferred_slots' => $this->csvInts($data['preferred_slots'] ?? []),
            'unavailable_slots' => $this->slotPairs($data['unavailable_slots'] ?? []),
            'max_classes_per_day' => $data['max_classes_per_day'] ?? null,
            'max_consecutive_classes' => $data['max_consecutive_classes'] ?? null,
            'max_weekly_load' => $data['max_weekly_load'] ?? null,
            'reason' => $data['reason'] ?? null,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.046'],
        ]);

        $audit($actor, 'academic_pmc_v046_faculty_availability_submitted', 'Faculty availability request submitted', $request);
        return $request;
    }

    public function decideFacultyAvailability(User $actor, AcademicPmcFacultyAvailabilityRequest $request, string $status, ?string $note, callable $audit): AcademicPmcFacultyAvailabilityRequest
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $request->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            AcademicPmcFacultyPreference::updateOrCreate(
                ['teacher_id' => $request->teacher_id, 'term_id' => $request->term_id],
                [
                    'faculty_type' => $request->teacher?->employment_type === 'visiting' ? 'adjunct' : 'regular',
                    'available_days' => $request->available_days,
                    'preferred_slots' => $request->preferred_slots,
                    'unavailable_slots' => $request->unavailable_slots,
                    'max_classes_per_day' => $request->max_classes_per_day ?: 4,
                    'max_consecutive_classes' => $request->max_consecutive_classes ?: 3,
                    'max_weekly_load' => $request->max_weekly_load ?: 18,
                    'metadata' => ['source_request_id' => $request->id, 'approved_by' => $actor->id, 'version' => 'PMC OS v0.046'],
                ]
            );
        }

        $audit($actor, 'academic_pmc_v046_faculty_availability_decided', 'Faculty availability request ' . $status, $request);
        return $request->fresh();
    }

    public function refreshFacultyLoadReviews(User $actor, array $data, callable $audit, callable $syncFacultySuitabilityPublishCheck): array
    {
        $this->policy->authorizeWrite($actor);
        $run = ! empty($data['generation_run_id'])
            ? AcademicPmcTimetableGenerationRun::findOrFail($data['generation_run_id'])
            : AcademicPmcTimetableGenerationRun::latest()->first();
        abort_unless($run, 422, 'No timetable generation run is available for load review.');

        $teachers = AcademicPmcGroupFacultyAssignment::query()
            ->whereHas('courseGroup', fn ($q) => $q->when($run->term_id, fn ($termQuery) => $termQuery->where('term_id', $run->term_id)))
            ->pluck('teacher_id')
            ->merge(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->whereNotNull('teacher_id')->pluck('teacher_id'))
            ->unique()
            ->values();

        $created = 0;
        foreach ($teachers as $teacherId) {
            $assignments = AcademicPmcGroupFacultyAssignment::where('teacher_id', $teacherId)
                ->whereHas('courseGroup', fn ($q) => $q->when($run->term_id, fn ($termQuery) => $termQuery->where('term_id', $run->term_id)))
                ->get();
            $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('teacher_id', $teacherId)->where('status', 'scheduled')->get();
            $preference = AcademicPmcFacultyPreference::where('teacher_id', $teacherId)->where(fn ($q) => $q->where('term_id', $run->term_id)->orWhereNull('term_id'))->first();
            $daily = $items->groupBy('day_of_week')->map->count()->all();
            $assignedHours = (int) $assignments->sum('weekly_hours');
            $weeklyLimit = (int) ($preference?->max_weekly_load ?: 18);
            $dailyLimit = (int) ($preference?->max_classes_per_day ?: 4);
            $maxDay = (int) (empty($daily) ? 0 : max($daily));
            $maxConsecutive = $this->maxConsecutiveForItems($items);
            $reasons = [];
            $band = 'normal';

            if ($assignedHours > $weeklyLimit || $maxDay > $dailyLimit) {
                $band = 'overload';
                $reasons[] = 'load_exceeds_configured_limit';
            }
            if ($assignedHours > ($weeklyLimit + 4) || $maxDay > ($dailyLimit + 2)) {
                $band = 'critical';
                $reasons[] = 'critical_overload';
            }
            if ($assignedHours > 0 && $assignedHours < 8) {
                $band = 'underload';
                $reasons[] = 'underload';
            }
            if ($preference && $preference->faculty_type === 'adjunct' && $maxDay > $dailyLimit) {
                $band = 'critical';
                $reasons[] = 'adjunct_daily_limit_breach';
            }

            AcademicPmcFacultyLoadReview::updateOrCreate(
                ['teacher_id' => $teacherId, 'term_id' => $run->term_id, 'generation_run_id' => $run->id],
                [
                    'assigned_weekly_hours' => $assignedHours,
                    'scheduled_classes' => $items->count(),
                    'max_classes_in_day' => $maxDay,
                    'max_consecutive_classes' => $maxConsecutive,
                    'configured_weekly_limit' => $weeklyLimit,
                    'configured_daily_limit' => $dailyLimit,
                    'load_band' => $band,
                    'status' => in_array($band, ['overload', 'critical'], true) ? 'approval_required' : 'review_required',
                    'risk_reasons' => $reasons,
                    'daily_distribution' => $daily,
                    'metadata' => ['version' => 'PMC OS v0.047', 'assignment_count' => $assignments->count()],
                ]
            );
            $created++;
        }

        $audit($actor, 'academic_pmc_v047_faculty_load_reviews_refreshed', 'Faculty load reviews refreshed', $run, ['reviews' => $created]);
        return ['run' => $run, 'reviews' => $created];
    }

    public function decideFacultyLoadReview(User $actor, AcademicPmcFacultyLoadReview $review, string $status, ?string $note, callable $audit, callable $syncFacultySuitabilityPublishCheck): AcademicPmcFacultyLoadReview
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['approved_overload', 'rejected', 'revision_required'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $review->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'decision_note' => $note,
        ]);

        $audit($actor, 'academic_pmc_v047_faculty_load_review_decided', 'Faculty load review ' . $status, $review);
        return $review->fresh();
    }

    public function refreshRoomReadinessReviews(User $actor, array $data, callable $audit): array
    {
        $this->policy->authorizeWrite($actor);
        $run = ! empty($data['generation_run_id'])
            ? AcademicPmcTimetableGenerationRun::findOrFail($data['generation_run_id'])
            : AcademicPmcTimetableGenerationRun::latest()->first();
        abort_unless($run, 422, 'No timetable generation run is available for room readiness review.');

        $itemsByRoom = AcademicPmcTimetableGenerationItem::with(['courseGroup', 'classroom'])
            ->where('generation_run_id', $run->id)
            ->where('status', 'scheduled')
            ->whereNotNull('classroom_id')
            ->get()
            ->groupBy('classroom_id');

        $created = 0;
        foreach ($itemsByRoom as $roomId => $items) {
            $room = $items->first()?->classroom ?: Classroom::find($roomId);
            if (! $room) {
                continue;
            }

            $maxStrength = (int) $items->map(fn ($item) => (int) ($item->courseGroup?->current_strength ?? 0))->max();
            $roomCapacity = (int) ($room->capacity ?? 0);
            $labRequired = $items->contains(fn ($item) => str_contains((string) ($item->courseGroup?->group_type ?? ''), 'lab'));
            $labReady = ! $labRequired || (bool) ($room->has_lab ?? false) || $room->type === 'lab';
            $capacityOk = $roomCapacity >= $maxStrength;
            $usage = $items->groupBy('day_of_week')->map(fn ($dayItems) => $dayItems->count())->all();
            $reasons = [];

            if (! $capacityOk) {
                $reasons[] = 'capacity_below_largest_group';
            }
            if (! $labReady) {
                $reasons[] = 'lab_group_in_non_lab_room';
            }
            if ($items->count() >= 6) {
                $reasons[] = 'high_room_utilization';
            }

            $band = (! $capacityOk || ! $labReady) ? 'blocked' : ($reasons ? 'warning' : 'ready');
            AcademicPmcRoomReadinessReview::updateOrCreate(
                ['classroom_id' => $room->id, 'generation_run_id' => $run->id],
                [
                    'scheduled_classes' => $items->count(),
                    'max_group_strength' => $maxStrength,
                    'room_capacity' => $roomCapacity,
                    'lab_required' => $labRequired,
                    'lab_ready' => $labReady,
                    'capacity_ok' => $capacityOk,
                    'readiness_band' => $band,
                    'status' => $band === 'ready' ? 'review_required' : 'review_required',
                    'risk_reasons' => $reasons,
                    'usage_distribution' => $usage,
                    'metadata' => ['version' => 'PMC OS v0.048', 'run_title' => $run->title],
                ]
            );
            $created++;
        }

        $blocked = AcademicPmcRoomReadinessReview::where('generation_run_id', $run->id)
            ->where('readiness_band', 'blocked')
            ->whereNotIn('status', ['approved', 'approved_with_exception'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $run->id, 'check_type' => 'room_readiness'],
            [
                'status' => $blocked === 0 ? 'pass' : 'block',
                'severity' => $blocked === 0 ? 'info' : 'high',
                'title' => 'Room and lab readiness',
                'description' => $blocked === 0 ? 'Rooms/labs are ready or reviewed for this generation run.' : "{$blocked} room/lab readiness blocker(s) require PMC/Dean decision.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.048'],
            ]
        );

        $audit($actor, 'academic_pmc_v048_room_readiness_refreshed', 'Room readiness reviews refreshed', $run, ['reviews' => $created, 'blocked' => $blocked]);
        return ['reviews' => $created, 'blocked' => $blocked];
    }

    public function decideRoomReadinessReview(User $actor, AcademicPmcRoomReadinessReview $review, string $status, ?string $note, callable $audit): AcademicPmcRoomReadinessReview
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['approved_with_exception', 'rejected', 'revision_required'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $review->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'decision_note' => $note,
        ]);

        $blocked = AcademicPmcRoomReadinessReview::where('generation_run_id', $review->generation_run_id)
            ->where('readiness_band', 'blocked')
            ->whereNotIn('status', ['approved', 'approved_with_exception'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $review->generation_run_id, 'check_type' => 'room_readiness'],
            [
                'status' => $blocked === 0 ? 'pass' : 'block',
                'severity' => $blocked === 0 ? 'info' : 'high',
                'title' => 'Room and lab readiness',
                'description' => $blocked === 0 ? 'Room/lab readiness decisions are clear for publish.' : "{$blocked} room/lab readiness blocker(s) remain open.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.048'],
            ]
        );

        $audit($actor, 'academic_pmc_v048_room_readiness_decided', 'Room readiness review ' . $status, $review);
        return $review->fresh();
    }

    private function csvInts(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }

    private function slotPairs(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return collect(explode(',', (string) $value))
            ->map(function ($pair) {
                [$day, $slot] = array_pad(explode(':', trim($pair)), 2, null);
                return ['day' => (int) $day, 'slot_id' => (int) $slot];
            })
            ->filter(fn ($pair) => $pair['day'] > 0 && $pair['slot_id'] > 0)
            ->values()
            ->all();
    }

}
