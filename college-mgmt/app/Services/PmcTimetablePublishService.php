<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class PmcTimetablePublishService
{
    public const RESPONSIBILITY = 'Publish, freeze, unfreeze, rollback, publish checks, impact previews, and official version lifecycle.';

    public function __construct(private PmcTimetableBridgeSyncService $bridgeSync) {}

    public function publishRun(User $actor, AcademicPmcTimetableGenerationRun $run, array $data, callable $refreshConstraintsAndQuality, callable $refreshGenerationImpactPreview): TimetableVersion
    {
        $refreshConstraintsAndQuality($run);
        $blocking = AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->where('status', 'block')->get();
        $canOverride = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);

        if ($blocking->isNotEmpty() && ! ($canOverride && ! empty($data['override_reason']))) {
            $blockedChecks = $blocking->pluck('title')->filter()->implode(', ');
            abort(422, 'Publish is blocked by hard timetable checks: ' . ($blockedChecks ?: 'unresolved publish checks') . '. Dean/Admin override requires a reason.');
        }

        $impactRecords = $refreshGenerationImpactPreview($actor, $run);
        $impactSummary = $run->fresh()->input_summary['impact_preview'] ?? [];

        $lastVersion = TimetableVersion::where('program_id', $run->program_id)
            ->where('term_id', $run->term_id)
            ->when($run->batch_id, fn ($q) => $q->where('batch_id', $run->batch_id))
            ->max('version_number') ?: 0;

        TimetableVersion::where('program_id', $run->program_id)
            ->where('term_id', $run->term_id)
            ->when($run->batch_id, fn ($q) => $q->where('batch_id', $run->batch_id))
            ->where('status', 'published')
            ->get()
            ->each(fn (TimetableVersion $publishedVersion) => $this->archiveOperationalVersion($publishedVersion));

        $version = TimetableVersion::create([
            'program_id' => $run->program_id,
            'term_id' => $run->term_id,
            'batch_id' => $run->batch_id,
            'version_number' => $lastVersion + 1,
            'status' => 'published',
            'created_by' => $actor->id,
            'published_by' => $actor->id,
            'published_at' => now(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'notes' => $data['decision_reason'] ?? 'Published from PMC timetable generation run #' . $run->id,
        ]);

        $run->update(['timetable_version_id' => $version->id, 'status' => $blocking->isNotEmpty() ? 'published_with_dean_override' : 'published']);
        $this->bridgeSync->markRunItemsOfficial($run->fresh(), $version, $actor);
        $syncedEntries = $this->bridgeSync->syncRunToOperationalTimetable($run->fresh(), $version, $actor);

        AcademicPmcTimetableVersionWorkflow::create([
            'timetable_version_id' => $version->id,
            'generation_run_id' => $run->id,
            'lifecycle_status' => 'published',
            'approval_status' => $blocking->isNotEmpty() ? 'dean_override_published' : 'pmc_published',
            'published_by' => $actor->id,
            'published_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'override_reason' => $data['override_reason'] ?? null,
            'publish_summary' => [
                'scheduled' => $run->scheduled_count,
                'unscheduled' => $run->unscheduled_count,
                'hard_conflicts' => $run->hard_conflict_count,
                'soft_warnings' => $run->soft_warning_count,
                'quality_score' => $run->quality_score,
                'blocking_checks' => $blocking->pluck('title')->values(),
                'operational_entries_synced' => $syncedEntries,
                'impact_preview' => array_merge($impactSummary, ['impact_records' => $impactRecords->count(), 'version' => 'PMC OS v0.070']),
            ],
        ]);

        $publishNotificationMetadata = [
            'version' => 'PMC OS v0.071',
            'generation_run_id' => $run->id,
            'impact_preview' => array_merge($impactSummary, ['impact_records' => $impactRecords->count(), 'version' => 'PMC OS v0.070']),
            'scheduled' => $run->scheduled_count,
            'unscheduled' => $run->unscheduled_count,
            'hard_conflicts' => $run->hard_conflict_count,
            'soft_warnings' => $run->soft_warning_count,
            'quality_score' => $run->quality_score,
            'operational_entries_synced' => $syncedEntries,
        ];
        $recipientNotificationCounts = $this->logPublishRecipientNotifications($version, $run->fresh(), $publishNotificationMetadata);
        $this->logLifecycleNotification($version, 'publish', 'Timetable version published', 'students', $publishNotificationMetadata + ['audience_count' => $impactSummary['affected_students'] ?? 0]);
        $this->logLifecycleNotification($version, 'publish', 'Timetable version published', 'faculty', $publishNotificationMetadata + ['audience_count' => $impactSummary['affected_faculty'] ?? 0]);
        $this->audit($actor, 'academic_pmc_v043_timetable_published', 'Published timetable version #' . $version->version_number, $version, ['run_id' => $run->id, 'recipient_notifications' => $recipientNotificationCounts]);

        return $version;
    }

    public function freezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        $this->authorizeDeanLifecycle($actor, 'freeze');

        $workflow = AcademicPmcTimetableVersionWorkflow::updateOrCreate(
            ['timetable_version_id' => $version->id],
            [
                'generation_run_id' => AcademicPmcTimetableGenerationRun::where('timetable_version_id', $version->id)->latest()->value('id'),
                'lifecycle_status' => 'frozen',
                'approval_status' => 'dean_frozen',
                'frozen_by' => $actor->id,
                'frozen_at' => now(),
                'decision_reason' => $data['decision_reason'] ?? 'Frozen by Dean/Academic leadership.',
                'impact_summary' => $this->versionImpactSummary($version),
            ]
        );

        $this->logLifecycleNotification($version, 'freeze', 'Timetable version frozen', 'faculty');
        $this->audit($actor, 'academic_pmc_v043_timetable_frozen', 'Frozen timetable version #' . $version->version_number, $workflow);

        return $workflow->fresh();
    }

    public function unfreezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        $this->authorizeDeanLifecycle($actor, 'unfreeze');
        if (empty($data['decision_reason'])) {
            abort(422, 'Unfreeze reason is required.');
        }

        $workflow = AcademicPmcTimetableVersionWorkflow::updateOrCreate(
            ['timetable_version_id' => $version->id],
            [
                'generation_run_id' => AcademicPmcTimetableGenerationRun::where('timetable_version_id', $version->id)->latest()->value('id'),
                'lifecycle_status' => 'revision_requested',
                'approval_status' => 'dean_unfrozen',
                'unfrozen_by' => $actor->id,
                'unfrozen_at' => now(),
                'decision_reason' => $data['decision_reason'],
                'impact_summary' => $this->versionImpactSummary($version),
            ]
        );

        $this->audit($actor, 'academic_pmc_v043_timetable_unfrozen', 'Unfrozen timetable version #' . $version->version_number, $workflow);

        return $workflow->fresh();
    }

    public function rollbackVersion(User $actor, TimetableVersion $version, array $data): TimetableVersion
    {
        $this->authorizeDeanLifecycle($actor, 'rollback');
        abort_unless(in_array($version->status, ['published', 'archived'], true), 422, 'Only a previously published timetable version can be rolled back.');
        if (empty($data['decision_reason'])) {
            abort(422, 'Rollback reason is required.');
        }

        TimetableVersion::where('program_id', $version->program_id)
            ->where('term_id', $version->term_id)
            ->when($version->batch_id, fn ($q) => $q->where('batch_id', $version->batch_id))
            ->where('status', 'published')
            ->where('id', '!=', $version->id)
            ->get()
            ->each(fn (TimetableVersion $publishedVersion) => $this->archiveOperationalVersion($publishedVersion));

        if ($version->status === 'published') {
            $version->update(['status' => 'archived']);
        }

        $rollback = TimetableVersion::create([
            'program_id' => $version->program_id,
            'term_id' => $version->term_id,
            'batch_id' => $version->batch_id,
            'version_number' => (TimetableVersion::where('program_id', $version->program_id)->where('term_id', $version->term_id)->max('version_number') ?: $version->version_number) + 1,
            'status' => 'published',
            'created_by' => $actor->id,
            'published_by' => $actor->id,
            'published_at' => now(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'notes' => 'Rollback from version #' . $version->version_number . ': ' . $data['decision_reason'],
        ]);

        AcademicPmcTimetableVersionWorkflow::create([
            'timetable_version_id' => $rollback->id,
            'rollback_from_version_id' => $version->id,
            'lifecycle_status' => 'rollback_published',
            'approval_status' => 'dean_rollback',
            'published_by' => $actor->id,
            'published_at' => now(),
            'decision_reason' => $data['decision_reason'],
            'impact_summary' => $this->versionImpactSummary($version),
        ]);

        $syncedEntries = $this->repointRollbackOperationalEntries($actor, $version, $rollback);
        $this->logLifecycleNotification($rollback, 'rollback', 'Timetable rollback published', 'students');
        $this->audit($actor, 'academic_pmc_v043_timetable_rollback', 'Rollback published from timetable version #' . $version->version_number, $rollback, [
            'rollback_from_version_id' => $version->id,
            'operational_entries_synced' => $syncedEntries,
        ]);

        return $rollback;
    }

    public function archiveOperationalVersion(TimetableVersion $version): void
    {
        TimetableEntry::where('timetable_version_id', $version->id)->update([
            'status' => 'archived',
            'is_active' => false,
            'updated_at' => now(),
        ]);

        AcademicPmcTimetableGenerationItem::where('timetable_version_id', $version->id)->update([
            'official_status' => 'archived',
            'updated_at' => now(),
        ]);

        if ($version->status !== 'archived') {
            $version->update(['status' => 'archived']);
        }
    }

    public function logLifecycleNotification(TimetableVersion $version, string $type, string $title, string $recipientType, array $metadata = []): void
    {
        AcademicPmcTimetableNotification::create([
            'notification_type' => $type,
            'recipient_type' => $recipientType,
            'title' => $title,
            'message' => 'Program timetable version #' . $version->version_number . ' is now ' . $type . '.',
            'status' => 'queued',
            'source_type' => 'timetable_version',
            'source_key' => (string) $version->id,
            'metadata' => $metadata,
        ]);
    }

    private function logPublishRecipientNotifications(TimetableVersion $version, AcademicPmcTimetableGenerationRun $run, array $baseMetadata): array
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup', 'teacher.user'])
            ->where('generation_run_id', $run->id)
            ->where('status', 'scheduled')
            ->get();
        $facultyCreated = 0;
        $studentCreated = 0;

        $items->whereNotNull('teacher_id')
            ->groupBy('teacher_id')
            ->each(function (Collection $teacherItems) use ($version, $baseMetadata, &$facultyCreated) {
                $teacher = $teacherItems->first()?->teacher;
                if (! $teacher?->user_id) {
                    return;
                }

                AcademicPmcTimetableNotification::create([
                    'notification_type' => 'publish',
                    'recipient_type' => 'faculty',
                    'recipient_user_id' => $teacher->user_id,
                    'title' => 'Timetable version published for your assigned classes',
                    'message' => 'Your assigned course groups are included in timetable version #' . $version->version_number . '.',
                    'status' => 'queued',
                    'source_type' => 'timetable_version',
                    'source_key' => (string) $version->id,
                    'metadata' => array_merge($baseMetadata, [
                        'version' => 'PMC OS v0.073',
                        'recipient_scope' => 'individual_faculty',
                        'audience_count' => 1,
                        'course_group_ids' => $teacherItems->pluck('course_group_id')->filter()->unique()->values()->all(),
                    ]),
                ]);
                $facultyCreated++;
            });

        $groupIds = $items->pluck('course_group_id')->filter()->unique()->values();
        AcademicPmcCourseGroupMember::with('student.user')
            ->whereIn('course_group_id', $groupIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('student.user_id')
            ->each(function (Collection $members, mixed $userId) use ($version, $baseMetadata, &$studentCreated) {
                if (! $userId) {
                    return;
                }

                AcademicPmcTimetableNotification::create([
                    'notification_type' => 'publish',
                    'recipient_type' => 'student',
                    'recipient_user_id' => (int) $userId,
                    'title' => 'Timetable version published for your enrolled groups',
                    'message' => 'Your course groups are included in timetable version #' . $version->version_number . '.',
                    'status' => 'queued',
                    'source_type' => 'timetable_version',
                    'source_key' => (string) $version->id,
                    'metadata' => array_merge($baseMetadata, [
                        'version' => 'PMC OS v0.073',
                        'recipient_scope' => 'individual_student',
                        'audience_count' => 1,
                        'course_group_ids' => $members->pluck('course_group_id')->filter()->unique()->values()->all(),
                        'student_ids' => $members->pluck('student_id')->filter()->unique()->values()->all(),
                    ]),
                ]);
                $studentCreated++;
            });

        return ['faculty' => $facultyCreated, 'students' => $studentCreated];
    }

    private function repointRollbackOperationalEntries(User $actor, TimetableVersion $source, TimetableVersion $rollback): int
    {
        $updated = TimetableEntry::where('timetable_version_id', $source->id)->update([
            'timetable_version_id' => $rollback->id,
            'status' => 'published',
            'is_active' => true,
            'updated_at' => now(),
        ]);

        if ($updated > 0) {
            return $updated;
        }

        $run = AcademicPmcTimetableGenerationRun::where('timetable_version_id', $source->id)->latest()->first();

        if (! $run) {
            return 0;
        }

        return $this->bridgeSync->syncRunToOperationalTimetable($run, $rollback, $actor);
    }

    private function authorizeDeanLifecycle(User $actor, string $action): void
    {
        if (! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            throw new AuthorizationException("Only Dean/Academic leadership can {$action} timetable versions.");
        }
    }

    private function versionImpactSummary(TimetableVersion $version): array
    {
        return [
            'program_id' => $version->program_id,
            'batch_id' => $version->batch_id,
            'term_id' => $version->term_id,
            'affected_groups' => AcademicPmcCourseGroup::where('program_id', $version->program_id)->where('term_id', $version->term_id)->count(),
            'affected_students' => AcademicPmcCourseGroupMember::whereHas('courseGroup', fn ($q) => $q->where('program_id', $version->program_id)->where('term_id', $version->term_id))->distinct('student_id')->count('student_id'),
            'affected_faculty' => AcademicPmcGroupFacultyAssignment::whereHas('courseGroup', fn ($q) => $q->where('program_id', $version->program_id)->where('term_id', $version->term_id))->distinct('teacher_id')->count('teacher_id'),
        ];
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
