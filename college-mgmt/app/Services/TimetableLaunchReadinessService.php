<?php

namespace App\Services;

use App\Models\AcademicCalendar;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcFacultyTimetablePolicy;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomCapability;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableLaunchReadinessService
{
    public function evaluate(?User $user = null, array $scope = []): array
    {
        $scope = $this->cleanScope($scope);
        $checks = collect([
            $this->academicScopeCheck($scope),
            $this->curriculumCheck($scope),
            $this->studentBasketCheck($scope),
            $this->groupCheck($scope),
            $this->facultyCheck($scope),
            $this->facultyRuleCheck($scope),
            $this->roomAndSlotCheck($scope),
            $this->calendarAndLockCheck($scope),
            $this->draftFreshnessCheck($scope),
        ]);

        $blocked = $checks->where('status', 'blocked')->count();
        $warnings = $checks->where('status', 'warning')->count();

        return [
            'status' => $blocked > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready'),
            'ready_count' => $checks->where('status', 'ready')->count(),
            'warning_count' => $warnings,
            'blocked_count' => $blocked,
            'hard_blockers' => $checks->where('status', 'blocked')->values()->all(),
            'warnings' => $checks->where('status', 'warning')->values()->all(),
            'checks' => $checks->values()->all(),
            'scope' => $scope,
        ];
    }

    public function generationBlockers(?User $user = null, array $scope = []): array
    {
        return collect($this->evaluate($user, $scope)['hard_blockers'])
            ->map(fn (array $check): string => $check['label'] . ': ' . $check['message'])
            ->values()
            ->all();
    }

    public function publishBlockers(AcademicPmcTimetableGenerationRun $run, ?User $user = null): array
    {
        $scope = [
            'program_id' => $run->program_id,
            'batch_id' => $run->batch_id,
            'term_id' => $run->term_id,
        ];

        $blockers = $this->generationBlockers($user, $scope);

        if ((int) $run->unscheduled_count > 0) {
            $blockers[] = "Unscheduled canonical sessions remain: {$run->unscheduled_count}.";
        }

        if ((int) $run->hard_conflict_count > 0 || AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('severity', 'hard')->exists()) {
            $blockers[] = 'Hard timetable conflicts are still open for this generation run.';
        }

        if (AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->whereIn('status', ['block', 'blocked', 'pending', 'open'])->exists()) {
            $blockers[] = 'Publish checks still contain blocking or pending items.';
        }

        if ((int) $run->scheduled_count > 0 && ! AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $run->id)->exists()) {
            $blockers[] = 'Impact preview must be refreshed before publish.';
        }

        return array_values(array_unique($blockers));
    }

    public function postPublishDashboard(?User $user = null, array $scope = []): array
    {
        $scope = $this->cleanScope($scope);
        $publishedItems = $this->scopeItemQuery($scope)
            ->whereIn('official_status', ['published', 'locked'])
            ->whereIn('status', ['scheduled', 'published', 'locked']);

        $publishedItemIds = (clone $publishedItems)->pluck('id');
        $publishedGroupIds = (clone $publishedItems)->pluck('course_group_id')->filter()->unique();
        $publishedTeacherIds = (clone $publishedItems)->pluck('teacher_id')->filter()->unique();

        $frequentChangeGroups = \App\Models\AcademicPmcTimetableChangeRequest::query()
            ->selectRaw('pmc_generation_item_id, COUNT(*) as change_count')
            ->whereNotNull('pmc_generation_item_id')
            ->whereIn('status', ['requested', 'pending', 'approved', 'revision_requested'])
            ->whereHas('pmcGenerationItem', fn (Builder $query) => $this->applyItemScope($query, $scope))
            ->groupBy('pmc_generation_item_id')
            ->havingRaw('COUNT(*) >= 2')
            ->count();

        return [
            'published_sessions' => $publishedItemIds->count(),
            'bridge_failures' => (clone $publishedItems)->whereNull('operational_timetable_entry_id')->count(),
            'unacknowledged_faculty' => AcademicPmcFacultyAssignmentAcknowledgement::whereIn('teacher_id', $publishedTeacherIds)
                ->whereIn('status', ['requested', 'pending', 'concern_raised', 'revision_required'])
                ->count(),
            'uncovered_substitutions' => \App\Models\AcademicPmcSubstitutionRecommendation::whereIn('pmc_generation_item_id', $publishedItemIds)
                ->where(fn (Builder $query) => $query->whereNull('substitute_teacher_id')->orWhereIn('status', ['uncovered', 'pending', 'open']))
                ->count(),
            'room_readiness_issues' => $this->scopeRoomReviews($scope)
                ->where(fn (Builder $query) => $query->whereIn('status', ['review_required', 'revision_required', 'rejected'])->orWhereIn('readiness_band', ['blocked', 'warning']))
                ->count(),
            'same_day_changes' => \App\Models\AcademicPmcTimetableChangeRequest::whereIn('pmc_generation_item_id', $publishedItemIds)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'frequent_change_sessions' => $frequentChangeGroups,
            'sessions_missing_attendance' => \App\Models\Attendance::whereIn('pmc_generation_item_id', $publishedItemIds)->distinct('pmc_generation_item_id')->count('pmc_generation_item_id') < $publishedItemIds->count()
                ? max(0, $publishedItemIds->count() - \App\Models\Attendance::whereIn('pmc_generation_item_id', $publishedItemIds)->distinct('pmc_generation_item_id')->count('pmc_generation_item_id'))
                : 0,
            'affected_groups' => $publishedGroupIds->count(),
        ];
    }

    private function academicScopeCheck(array $scope): array
    {
        $programs = Program::query()->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id));
        $batches = Batch::query()->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id));
        $terms = Term::query()->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id));

        $programCount = (clone $programs)->where('is_active', true)->count();
        $batchCount = (clone $batches)->whereIn('status', ['active', 'ongoing', 'current'])->count() ?: (clone $batches)->count();
        $termCount = (clone $terms)->where('is_current', true)->count() ?: (clone $terms)->count();
        $blocked = ($scope['program_id'] ?? null) && $programCount === 0;
        $blocked = $blocked || (($scope['batch_id'] ?? null) && $batchCount === 0) || (($scope['term_id'] ?? null) && $termCount === 0);

        return $this->check('academic_scope', 'Academic year, term, program, batch active', $blocked ? 'blocked' : 'ready', $programCount + $batchCount + $termCount, 0, $blocked ? 1 : 0, $blocked ? 'Selected academic scope is inactive or missing.' : 'Academic scope is usable for launch.', 'academics.pmc.timetable-os.index', $scope);
    }

    private function curriculumCheck(array $scope): array
    {
        $subjects = Subject::query()
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where('program_id', $id))
            ->where('is_active', true)
            ->count();
        $groupsWithSubjects = $this->scopeGroupQuery($scope)->whereNotNull('subject_id')->count();
        $status = ($subjects + $groupsWithSubjects) === 0 ? 'blocked' : 'ready';

        return $this->check('curriculum', 'Subjects and curriculum mapped', $status, $subjects + $groupsWithSubjects, 0, $status === 'blocked' ? 1 : 0, $status === 'blocked' ? 'No active subjects or subject-linked groups found.' : 'Subject mappings exist.', 'academics.pmc.course-groups.index', $scope);
    }

    private function studentBasketCheck(array $scope): array
    {
        $allocations = AcademicPmcStudentCourseAllocation::query()
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('student', fn (Builder $student) => $student->where('program_id', $id)))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('student', fn (Builder $student) => $student->where('batch_id', $id)));

        $total = (clone $allocations)->count();
        $notFinal = (clone $allocations)->whereNotIn('basket_status', ['approved', 'locked', 'allocated'])->count();
        $ungrouped = (clone $allocations)->whereDoesntHave('groupMemberships', fn (Builder $query) => $query->where('status', 'active'))->count();
        $status = $total === 0 ? 'warning' : (($notFinal + $ungrouped) > 0 ? 'warning' : 'ready');

        return $this->check('student_baskets', 'Student baskets finalized', $status, max(0, $total - $notFinal - $ungrouped), $notFinal + $ungrouped + ($total === 0 ? 1 : 0), 0, $status === 'ready' ? 'Student basket records are finalized and grouped.' : 'Finalize baskets and link allocations to timetable groups.', 'academics.pmc.student-course-baskets.index', $scope);
    }

    private function groupCheck(array $scope): array
    {
        $groups = $this->scopeGroupQuery($scope)->with(['members', 'facultyAssignments'])->get();
        $missing = $groups->isEmpty() ? 1 : 0;
        $unlocked = $groups->where('is_locked', false)->count();
        $withoutMembers = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->members->where('status', 'active')->isEmpty())->count();
        $withoutFaculty = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->isEmpty())->count();
        $capacity = $groups->filter(fn (AcademicPmcCourseGroup $group) => (int) $group->current_strength < (int) $group->min_capacity || (int) $group->current_strength > (int) $group->max_capacity)->count();
        $hard = $missing + $withoutFaculty;
        $warnings = $unlocked + $withoutMembers + $capacity;

        return $this->check('groups', 'Course groups, sections, electives, lab groups locked', $hard > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready'), max(0, $groups->count() - $hard), $warnings, $hard, $hard > 0 ? 'Create groups and assign faculty before generation.' : ($warnings > 0 ? 'Lock groups and resolve membership/capacity warnings.' : 'Groups are ready.'), 'academics.pmc.course-groups.index', $scope);
    }

    private function facultyCheck(array $scope): array
    {
        $assignments = AcademicPmcGroupFacultyAssignment::query()
            ->whereHas('courseGroup', fn (Builder $query) => $this->applyGroupScope($query, $scope));
        $total = (clone $assignments)->count();
        $unapproved = (clone $assignments)->whereNotIn('approval_status', ['pmc_approved', 'approved', 'accepted'])->count();
        $pendingAck = AcademicPmcFacultyAssignmentAcknowledgement::query()
            ->whereHas('assignment.courseGroup', fn (Builder $query) => $this->applyGroupScope($query, $scope))
            ->whereIn('status', ['requested', 'pending', 'concern_raised', 'revision_required'])
            ->count();
        $hard = $total === 0 ? 1 : $unapproved;

        return $this->check('faculty', 'Faculty assigned, approved, and acknowledged', $hard > 0 ? 'blocked' : ($pendingAck > 0 ? 'warning' : 'ready'), max(0, $total - $unapproved), $pendingAck, $hard, $hard > 0 ? 'Approved faculty assignments are missing.' : ($pendingAck > 0 ? 'Faculty acknowledgement is pending or has concerns.' : 'Faculty assignments are approved.'), 'academics.pmc.section-faculty-allocation.index', $scope);
    }

    private function facultyRuleCheck(array $scope): array
    {
        $teacherIds = AcademicPmcGroupFacultyAssignment::query()
            ->whereHas('courseGroup', fn (Builder $query) => $this->applyGroupScope($query, $scope))
            ->pluck('teacher_id')
            ->filter()
            ->unique();
        $preferences = AcademicPmcFacultyPreference::query()
            ->whereIn('teacher_id', $teacherIds)
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->count();
        $policies = class_exists(AcademicPmcFacultyTimetablePolicy::class)
            ? AcademicPmcFacultyTimetablePolicy::query()->whereIn('teacher_id', $teacherIds)->count()
            : 0;
        $missingPreferences = max(0, $teacherIds->count() - $preferences);
        $status = $teacherIds->isEmpty() ? 'warning' : (($missingPreferences > 0 || $policies === 0) ? 'warning' : 'ready');

        return $this->check('faculty_rules', 'Faculty availability, preferences, and rule policies complete', $status, $preferences + $policies, $missingPreferences + ($policies === 0 ? 1 : 0), 0, $status === 'ready' ? 'Faculty preferences and policies are available.' : 'Capture missing faculty preferences/policies before final optimization.', 'academics.pmc.faculty-preferences.index', $scope);
    }

    private function roomAndSlotCheck(array $scope): array
    {
        $activeRooms = Classroom::where('is_active', true)->count();
        $teachingSlots = TimetableSlot::where('is_active', true)->where('is_break', false)->count();
        $capabilities = class_exists(AcademicPmcRoomCapability::class) ? AcademicPmcRoomCapability::count() : 0;
        $roomBlockers = $this->scopeRoomReviews($scope)
            ->where(fn (Builder $query) => $query->whereIn('status', ['review_required', 'revision_required', 'rejected'])->orWhereIn('readiness_band', ['blocked']))
            ->count();
        $hard = ($activeRooms === 0 ? 1 : 0) + ($teachingSlots === 0 ? 1 : 0) + $roomBlockers;
        $warnings = $capabilities === 0 ? 1 : 0;

        return $this->check('rooms_slots', 'Rooms, labs, capacity, capability, and slots reviewed', $hard > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready'), $activeRooms + $teachingSlots + $capabilities, $warnings, $hard, $hard > 0 ? 'Active rooms, teaching slots, or room readiness are blocking launch.' : ($warnings > 0 ? 'Room capability metadata is incomplete.' : 'Rooms and slots are ready.'), 'academics.pmc.locked-slots.index', $scope);
    }

    private function calendarAndLockCheck(array $scope): array
    {
        $events = AcademicCalendar::query()
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id));
        $holidayClosures = (clone $events)->where('is_holiday', true)->whereDate('event_date', '>=', now()->toDateString())->count();
        $examEvents = (clone $events)->whereIn('event_type', ['exam', 'exam_week'])->whereDate('event_date', '>=', now()->toDateString())->count();
        $hardLockCollisions = $this->hardLockCollisionCount($this->scopeLockedSlots($scope)->where('status', 'active')->get());
        $status = $hardLockCollisions > 0 ? 'blocked' : (($holidayClosures + $examEvents) > 0 ? 'warning' : 'ready');

        return $this->check('calendar_locks', 'Calendar exceptions and locked institutional slots reviewed', $status, (clone $events)->count(), $holidayClosures + $examEvents, $hardLockCollisions, $hardLockCollisions > 0 ? 'Hard locked slots collide.' : (($holidayClosures + $examEvents) > 0 ? 'Holiday/exam calendar exceptions must be reviewed for effective dates.' : 'Calendar and locked slots are ready.'), 'academics.pmc.locked-slots.index', $scope);
    }

    private function draftFreshnessCheck(array $scope): array
    {
        $latestRun = AcademicPmcTimetableGenerationRun::query()
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where('program_id', $id))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->where('batch_id', $id))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->latest()
            ->first();

        if (! $latestRun) {
            return $this->check('draft_freshness', 'Existing draft run freshness reviewed', 'ready', 0, 0, 0, 'No previous run exists; generation can start after prerequisites pass.', 'academics.pmc.timetable-generator.index', $scope);
        }

        $stale = collect([
            AcademicPmcCourseGroup::where('updated_at', '>', $latestRun->updated_at)->exists(),
            AcademicPmcCourseGroupMember::where('updated_at', '>', $latestRun->updated_at)->exists(),
            AcademicPmcGroupFacultyAssignment::where('updated_at', '>', $latestRun->updated_at)->exists(),
            AcademicPmcFacultyPreference::where('updated_at', '>', $latestRun->updated_at)->exists(),
            AcademicPmcLockedSlot::where('updated_at', '>', $latestRun->updated_at)->exists(),
            AcademicPmcRoomReadinessReview::where('updated_at', '>', $latestRun->updated_at)->exists(),
        ])->filter()->count();

        return $this->check('draft_freshness', 'Existing draft run freshness reviewed', $stale > 0 ? 'warning' : 'ready', 1, $stale, 0, $stale > 0 ? 'Inputs changed after the latest generation; regenerate before publish.' : 'Latest run is current against source inputs.', 'academics.pmc.timetable-generator.index', $scope);
    }

    private function check(string $key, string $label, string $status, int $ready, int $warnings, int $blockers, string $message, string $route, array $filters): array
    {
        return compact('key', 'label', 'status', 'ready', 'warnings', 'blockers', 'message', 'route', 'filters');
    }

    private function cleanScope(array $scope): array
    {
        return collect(['program_id', 'batch_id', 'term_id', 'subject_id'])
            ->mapWithKeys(fn (string $key) => [$key => filled($scope[$key] ?? null) ? (int) $scope[$key] : null])
            ->all();
    }

    private function scopeGroupQuery(array $scope): Builder
    {
        return $this->applyGroupScope(AcademicPmcCourseGroup::query(), $scope);
    }

    private function scopeItemQuery(array $scope): Builder
    {
        return $this->applyItemScope(\App\Models\AcademicPmcTimetableGenerationItem::query(), $scope);
    }

    private function scopeLockedSlots(array $scope): Builder
    {
        return AcademicPmcLockedSlot::query()
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->whereNull('program_id')->orWhere('program_id', $id)))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->whereNull('batch_id')->orWhere('batch_id', $id)))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->whereNull('term_id')->orWhere('term_id', $id)));
    }

    private function scopeRoomReviews(array $scope): Builder
    {
        return AcademicPmcRoomReadinessReview::query()
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('generationRun', fn (Builder $run) => $run->where('program_id', $id)))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('generationRun', fn (Builder $run) => $run->where('batch_id', $id)))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('generationRun', fn (Builder $run) => $run->where('term_id', $id)));
    }

    private function applyGroupScope(Builder $query, array $scope): Builder
    {
        return $query
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where('program_id', $id))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->where('batch_id', $id))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->when($scope['subject_id'] ?? null, fn (Builder $query, int $id) => $query->where('subject_id', $id));
    }

    private function applyItemScope(Builder $query, array $scope): Builder
    {
        return $query
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->where('program_id', $id)->orWhereHas('courseGroup', fn (Builder $group) => $group->where('program_id', $id))))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->where('batch_id', $id)->orWhereHas('courseGroup', fn (Builder $group) => $group->where('batch_id', $id))))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->where('term_id', $id)->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $id))))
            ->when($scope['subject_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $inner) => $inner->where('subject_id', $id)->orWhereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $id))));
    }

    private function hardLockCollisionCount(Collection $lockedSlots): int
    {
        $collisions = collect();

        foreach (['teacher_id', 'classroom_id', 'course_group_id'] as $field) {
            $lockedSlots
                ->filter(fn (AcademicPmcLockedSlot $slot) => filled($slot->{$field}) && $slot->is_hard_lock)
                ->groupBy(fn (AcademicPmcLockedSlot $slot) => $field . ':' . $slot->day_of_week . ':' . $slot->timetable_slot_id . ':' . $slot->{$field})
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->keys()
                ->each(fn (string $key) => $collisions->push($key));
        }

        return $collisions->unique()->count();
    }
}
