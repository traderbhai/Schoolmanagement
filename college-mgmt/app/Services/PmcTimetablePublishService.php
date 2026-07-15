<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class PmcTimetablePublishService
{
    public const RESPONSIBILITY = 'Publish, freeze, unfreeze, rollback, publish checks, impact previews, and official version lifecycle.';

    public function __construct(private PmcTimetableBridgeSyncService $bridgeSync) {}

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
