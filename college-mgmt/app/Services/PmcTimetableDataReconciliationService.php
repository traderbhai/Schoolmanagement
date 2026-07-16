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

class PmcTimetableDataReconciliationService
{
    public const RESPONSIBILITY = 'PMC timetable data reconciliation checks, repair actions, exports, and audit surfaces.';

    public function __construct(
        private AcademicPmcAccessPolicyService $policy,
        private PmcTimetableReadModelService $readModels,
        private PmcTimetableBridgeSyncService $bridgeSync,
    ) {}

    public function refreshDataReconciliation(User $actor, callable $audit): array
    {
        $this->policy->authorizeRead($actor);

        $checks = [
            $this->reconcileGeneratedOperationalSync($actor),
            $this->reconcileAllocationEnrollmentLinks($actor),
            $this->reconcileGroupMembershipAllocations($actor),
            $this->reconcileDeliveryTrackers($actor),
            $this->reconcilePublishedNotifications($actor),
        ];

        $audit($actor, 'academic_pmc_v092_data_reconciliation_refreshed', 'PMC data reconciliation checks refreshed.', null, [
            'checks' => count($checks),
            'mismatches' => collect($checks)->sum('mismatch_count'),
        ]);

        return [
            'checks' => count($checks),
            'mismatches' => collect($checks)->sum('mismatch_count'),
            'critical' => collect($checks)->where('severity', 'critical')->count(),
        ];
    }

    public function dataReconciliationSurface(User $user, array $filters = []): array
    {
        $this->policy->authorizeRead($user);

        $auditActorIds = DepartmentActivityLog::query()
            ->whereIn('action', [
                'academic_pmc_v092_data_reconciliation_refreshed',
                'academic_pmc_v093_data_reconciliation_repaired',
                'academic_pmc_v105_reconciliation_stale_run_closed',
            ])
            ->whereNotNull('actor_user_id')
            ->distinct()
            ->pluck('actor_user_id');
        $auditActors = User::whereIn('id', $auditActorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $checks = $this->dataReconciliationQuery($filters)
            ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->latest('checked_at')
            ->paginate(20)
            ->withQueryString();

        $runsQuery = AcademicPmcDataReconciliationRun::with('starter')
            ->when($filters['run_status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $lastCompletedRun = AcademicPmcDataReconciliationRun::where('status', 'completed')->latest('finished_at')->first();
        $lastFailedRun = AcademicPmcDataReconciliationRun::where('status', 'failed')->latest('finished_at')->first();
        $staleRunningRuns = AcademicPmcDataReconciliationRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->count();

        return [
            'title' => 'PMC Data Reconciliation',
            'scopeLabel' => $this->policy->scopeLabel($user),
            'checks' => $checks,
            'filters' => $filters,
            'runs' => (clone $runsQuery)
                ->latest('started_at')
                ->limit(8)
                ->get(),
            'auditTrail' => $this->reconciliationAuditTrailQuery($filters)
                ->latest()
                ->limit(8)
                ->get(),
            'runSummary' => [
                'total' => AcademicPmcDataReconciliationRun::count(),
                'completed' => AcademicPmcDataReconciliationRun::where('status', 'completed')->count(),
                'failed' => AcademicPmcDataReconciliationRun::where('status', 'failed')->count(),
                'running' => AcademicPmcDataReconciliationRun::where('status', 'running')->count(),
                'manual_repairs' => AcademicPmcDataReconciliationRun::where('source', 'manual_ui_repair')->count(),
            ],
            'schedulerHealth' => [
                'status' => $staleRunningRuns > 0 ? 'warning' : ($lastCompletedRun ? 'healthy' : 'no_runs'),
                'label' => $staleRunningRuns > 0 ? 'Attention Needed' : ($lastCompletedRun ? 'Healthy' : 'No Runs'),
                'last_completed_at' => $lastCompletedRun?->finished_at,
                'last_failed_at' => $lastFailedRun?->finished_at,
                'stale_running' => $staleRunningRuns,
                'recommendation' => $staleRunningRuns > 0
                    ? 'Review stale running reconciliation jobs and rerun after confirming no process is active.'
                    : ($lastCompletedRun ? 'Scheduler has at least one completed reconciliation run.' : 'Run reconciliation once to establish scheduler baseline.'),
            ],
            'auditActors' => $auditActors,
            'summary' => [
                'total' => AcademicPmcDataReconciliationCheck::count(),
                'ok' => AcademicPmcDataReconciliationCheck::where('status', 'ok')->count(),
                'warn' => AcademicPmcDataReconciliationCheck::where('status', 'warn')->count(),
                'block' => AcademicPmcDataReconciliationCheck::where('status', 'block')->count(),
                'mismatches' => AcademicPmcDataReconciliationCheck::sum('mismatch_count'),
            ],
        ];
    }

    public function repairDataReconciliation(User $actor, AcademicPmcDataReconciliationCheck $check, callable $audit): array
    {
        $this->policy->authorizeWrite($actor);

        $result = match ($check->check_key) {
            'generated_operational_sync' => $this->repairGeneratedOperationalSync($actor),
            'allocation_enrollment_links' => $this->repairAllocationEnrollmentLinks($actor),
            'group_membership_allocation_links' => $this->repairGroupMembershipAllocationLinks($actor),
            'scheduled_groups_delivery_trackers' => $this->repairScheduledGroupDeliveryTrackers($actor),
            'published_version_notifications' => $this->repairPublishedVersionNotifications($actor),
            default => ['repaired' => 0, 'message' => 'No automated repair is available for this check.'],
        };

        $this->refreshDataReconciliation($actor, $audit);

        $audit($actor, 'academic_pmc_v093_data_reconciliation_repaired', 'PMC reconciliation repair executed.', $check, [
            'check_key' => $check->check_key,
            'repaired' => $result['repaired'],
            'message' => $result['message'],
        ]);

        return $result;
    }

    public function exportDataReconciliation(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = $this->dataReconciliationQuery($filters)
            ->latest('checked_at')
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation',
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.095', 'surface' => 'data_reconciliation'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['check', 'group', 'status', 'severity', 'expected', 'actual', 'mismatch', 'recommended_action', 'sample_mismatches', 'checked_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->title,
                    $row->check_group,
                    $row->status,
                    $row->severity,
                    $row->expected_count,
                    $row->actual_count,
                    $row->mismatch_count,
                    $row->recommended_action,
                    collect(data_get($row->details, 'sample_mismatches', []))->pluck('label')->filter()->join(' | '),
                    optional($row->checked_at)->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportDataReconciliationRuns(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = AcademicPmcDataReconciliationRun::with('starter')
            ->when($filters['run_status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('started_at')
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation_runs',
            'filters' => ['run_status' => $filters['run_status'] ?? null],
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.102', 'surface' => 'data_reconciliation_run_history'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['started_at', 'finished_at', 'source', 'status', 'repair_requested', 'checks', 'mismatches', 'critical', 'repaired', 'actor', 'failure_reason']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->started_at)->toDateTimeString(),
                    optional($row->finished_at)->toDateTimeString(),
                    $row->source,
                    $row->status,
                    $row->repair_requested ? 'yes' : 'no',
                    $row->checks_count,
                    $row->mismatch_count,
                    $row->critical_count,
                    $row->repaired_count,
                    $row->starter?->name,
                    $row->failure_reason,
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-runs-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportDataReconciliationAudit(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = $this->reconciliationAuditTrailQuery($filters)
            ->latest()
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation_audit',
            'filters' => [
                'action' => $filters['audit_action'] ?? null,
                'actor_user_id' => $filters['audit_actor_id'] ?? null,
                'from' => $filters['audit_from'] ?? null,
                'to' => $filters['audit_to'] ?? null,
            ],
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.110', 'surface' => 'data_reconciliation_audit_trail'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'actor', 'action', 'description', 'details', 'subject_type', 'subject_id']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->created_at)->toDateTimeString(),
                    $row->actor?->name ?: 'System',
                    $row->action,
                    $row->description,
                    data_get($row->metadata, 'reason') ?: data_get($row->metadata, 'message') ?: data_get($row->metadata, 'check_key') ?: '',
                    $row->subject_type ? class_basename($row->subject_type) : '',
                    $row->subject_id,
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-audit-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function dataReconciliationQuery(array $filters): Builder
    {
        return AcademicPmcDataReconciliationCheck::with('checker')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('check_group', $group));
    }

    private function reconciliationAuditTrailQuery(array $filters = []): Builder
    {
        return DepartmentActivityLog::with('actor')
            ->where(function ($query) {
                $query->whereIn('action', [
                    'academic_pmc_v092_data_reconciliation_refreshed',
                    'academic_pmc_v093_data_reconciliation_repaired',
                    'academic_pmc_v105_reconciliation_stale_run_closed',
                ])
                    ->orWhere('subject_type', AcademicPmcDataReconciliationCheck::class)
                    ->orWhere('subject_type', AcademicPmcDataReconciliationRun::class);
            })
            ->when($filters['audit_action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['audit_actor_id'] ?? null, fn ($q, $actorId) => $q->where('actor_user_id', $actorId))
            ->when($filters['audit_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['audit_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
    }

    private function repairGeneratedOperationalSync(User $actor): array
    {
        $canonicalRepair = app(TimetableCanonicalRepairService::class)->repairPublishedRunItems($actor);
        $runs = AcademicPmcTimetableGenerationRun::whereIn('timetable_version_id', $this->readModels->officialPublishedVersionIds())
            ->with('items')
            ->get();
        $repaired = 0;

        foreach ($runs as $run) {
            $version = TimetableVersion::find($run->timetable_version_id);
            if (! $version) {
                continue;
            }
            $repaired += $this->bridgeSync->syncRunToOperationalTimetable($run, $version, $actor);
        }

        return [
            'repaired' => $repaired + (int) $canonicalRepair['repaired'],
            'message' => "{$canonicalRepair['repaired']} canonical item(s) repaired and {$repaired} generated timetable item(s) synced to operational timetable entries.",
        ];
    }

    private function repairAllocationEnrollmentLinks(User $actor): array
    {
        $repaired = 0;
        AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNull('student_subject_enrollment_id')
            ->whereNotNull('student_id')
            ->whereNotNull('subject_id')
            ->chunkById(100, function ($allocations) use (&$repaired) {
                foreach ($allocations as $allocation) {
                    $enrollment = StudentSubjectEnrollment::firstOrCreate(
                        [
                            'student_id' => $allocation->student_id,
                            'subject_id' => $allocation->subject_id,
                            'term_id' => $allocation->term_id,
                        ],
                        [
                            'enrollment_type' => $allocation->allocation_type === 'elective' ? 'elective' : 'compulsory',
                            'status' => 'active',
                        ]
                    );
                    $allocation->update([
                        'student_subject_enrollment_id' => $enrollment->id,
                        'metadata' => array_merge($allocation->metadata ?: [], [
                            'reconciliation_repair' => 'student_subject_enrollment_linked',
                            'repaired_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                    $repaired++;
                }
            });

        return ['repaired' => $repaired, 'message' => "{$repaired} course allocations were linked to student subject enrollments."];
    }

    private function repairGroupMembershipAllocationLinks(User $actor): array
    {
        $repaired = 0;
        AcademicPmcCourseGroupMember::with('courseGroup')
            ->where('status', 'active')
            ->whereNull('student_course_allocation_id')
            ->chunkById(100, function ($memberships) use (&$repaired, $actor) {
                foreach ($memberships as $membership) {
                    $group = $membership->courseGroup;
                    if (! $group) {
                        continue;
                    }
                    $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $membership->student_id)
                        ->where('subject_id', $group->subject_id)
                        ->where('term_id', $group->term_id)
                        ->where('waitlisted', false)
                        ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
                        ->first();
                    if (! $allocation) {
                        continue;
                    }
                    $membership->update([
                        'student_course_allocation_id' => $allocation->id,
                        'moved_by' => $membership->moved_by ?: $actor->id,
                        'metadata' => array_merge($membership->metadata ?: [], [
                            'reconciliation_repair' => 'allocation_linked',
                            'repaired_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                    $repaired++;
                }
            });

        return ['repaired' => $repaired, 'message' => "{$repaired} active group memberships were linked to matching allocations."];
    }

    private function repairScheduledGroupDeliveryTrackers(User $actor): array
    {
        $groupIds = AcademicPmcTimetableGenerationItem::where('status', 'scheduled')
            ->whereIn('generation_run_id', $this->readModels->officialPublishedGenerationRunIds())
            ->whereNotNull('course_group_id')
            ->distinct()
            ->pluck('course_group_id');
        $groups = AcademicPmcCourseGroup::whereIn('id', $groupIds)->get();
        $repaired = 0;

        foreach ($groups as $group) {
            $primaryAssignment = AcademicPmcGroupFacultyAssignment::where('course_group_id', $group->id)
                ->where('assignment_role', 'primary')
                ->first();
            $tracker = \App\Models\AcademicPmcGroupDeliveryTracker::firstOrCreate(
                ['course_group_id' => $group->id],
                [
                    'program_id' => $group->program_id,
                    'batch_id' => $group->batch_id,
                    'term_id' => $group->term_id,
                    'subject_id' => $group->subject_id,
                    'teacher_id' => $primaryAssignment?->teacher_id,
                    'owner_user_id' => $primaryAssignment?->teacher?->user_id ?: $actor->id,
                    'planned_sessions' => AcademicPmcTimetableGenerationItem::where('course_group_id', $group->id)->where('status', 'scheduled')->whereIn('generation_run_id', $this->readModels->officialPublishedGenerationRunIds())->count(),
                    'status' => 'monitoring',
                    'risk_band' => 'low',
                    'risk_reasons' => ['Created by PMC data reconciliation repair.'],
                    'recommended_actions' => ['Review delivery plan and assign session logs.'],
                    'metadata' => ['reconciliation_repair' => 'delivery_tracker_created', 'repaired_at' => now()->toDateTimeString()],
                ]
            );
            if ($tracker->wasRecentlyCreated) {
                $repaired++;
            }
        }

        return ['repaired' => $repaired, 'message' => "{$repaired} missing group delivery trackers were created."];
    }

    private function repairPublishedVersionNotifications(User $actor): array
    {
        $repaired = 0;
        TimetableVersion::where('status', 'published')->chunkById(100, function ($versions) use (&$repaired, $actor) {
            foreach ($versions as $version) {
                $notification = AcademicPmcTimetableNotification::firstOrCreate(
                    [
                        'notification_type' => 'publish',
                        'recipient_type' => 'audience',
                        'source_type' => 'timetable_version',
                        'source_key' => (string) $version->id,
                    ],
                    [
                        'recipient_user_id' => null,
                        'title' => 'Timetable publish notification audit repaired',
                        'message' => 'Recreated missing publish notification audit row for timetable version #' . $version->version_number . '.',
                        'status' => 'queued',
                        'metadata' => [
                            'reconciliation_repair' => 'publish_notification_created',
                            'repaired_by' => $actor->id,
                            'repaired_at' => now()->toDateTimeString(),
                        ],
                    ]
                );
                if ($notification->wasRecentlyCreated) {
                    $repaired++;
                }
            }
        });

        return ['repaired' => $repaired, 'message' => "{$repaired} missing publish notification audit rows were queued."];
    }

    private function reconcileGeneratedOperationalSync(User $actor): array
    {
        $publishedRuns = $this->readModels->officialPublishedGenerationRunIds();
        $scheduled = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->whereIn('status', ['scheduled', 'published', 'locked'])->count();
        $synced = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->whereIn('status', ['scheduled', 'published', 'locked'])->whereNotNull('operational_timetable_entry_id')->count();
        $samples = AcademicPmcTimetableGenerationItem::with(['generationRun', 'courseGroup.subject', 'teacher.user', 'classroom', 'slot'])
            ->whereIn('generation_run_id', $publishedRuns)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNull('operational_timetable_entry_id')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => trim(($item->courseGroup?->name ?: 'Course group') . ' / ' . ($item->courseGroup?->subject?->code ?: 'subject') . ' / day ' . $item->day_of_week),
                'status' => $item->status,
                'source' => 'generation_run:' . $item->generation_run_id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'generated_operational_sync',
            'check_group' => 'timetable',
            'title' => 'Published generated classes synced to operational timetable',
            'description' => 'Every published PMC generation item should point to a legacy operational timetable entry.',
            'expected_count' => $scheduled,
            'actual_count' => $synced,
            'mismatch_count' => max(0, $scheduled - $synced),
            'recommended_action' => 'Republish or repair operational timetable sync for affected generation items.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileAllocationEnrollmentLinks(User $actor): array
    {
        $allocations = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->count();
        $linked = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNotNull('student_subject_enrollment_id')
            ->count();
        $samples = AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term'])
            ->where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNull('student_subject_enrollment_id')
            ->limit(5)
            ->get()
            ->map(fn ($allocation) => [
                'id' => $allocation->id,
                'label' => trim(($allocation->student?->user?->name ?: 'Student') . ' / ' . ($allocation->subject?->code ?: 'subject') . ' / ' . ($allocation->term?->name ?: 'term')),
                'status' => $allocation->basket_status,
                'source' => 'allocation:' . $allocation->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'allocation_enrollment_links',
            'check_group' => 'course_basket',
            'title' => 'Approved allocations linked to student subject enrollments',
            'description' => 'Approved/non-waitlisted course basket allocations should have matching student subject enrollment links.',
            'expected_count' => $allocations,
            'actual_count' => $linked,
            'mismatch_count' => max(0, $allocations - $linked),
            'recommended_action' => 'Refresh course basket enrollment links or review exception-created allocations.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileGroupMembershipAllocations(User $actor): array
    {
        $memberships = AcademicPmcCourseGroupMember::where('status', 'active')->count();
        $linked = AcademicPmcCourseGroupMember::where('status', 'active')
            ->whereNotNull('student_course_allocation_id')
            ->whereHas('courseGroup')
            ->count();
        $samples = AcademicPmcCourseGroupMember::with(['student.user', 'courseGroup.subject'])
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('student_course_allocation_id')
                    ->orWhereDoesntHave('courseGroup');
            })
            ->limit(5)
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'label' => trim(($member->student?->user?->name ?: 'Student') . ' / ' . ($member->courseGroup?->name ?: 'missing group') . ' / ' . ($member->courseGroup?->subject?->code ?: 'subject')),
                'status' => $member->status,
                'source' => 'group_member:' . $member->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'group_membership_allocation_links',
            'check_group' => 'sections_groups',
            'title' => 'Active group memberships linked to course basket allocations',
            'description' => 'Every active course group membership should point back to an allocation so student-specific timetable visibility remains correct.',
            'expected_count' => $memberships,
            'actual_count' => $linked,
            'mismatch_count' => max(0, $memberships - $linked),
            'recommended_action' => 'Repair unlinked group memberships or re-run group builder from approved allocations.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileDeliveryTrackers(User $actor): array
    {
        $publishedRuns = $this->readModels->officialPublishedGenerationRunIds();
        $scheduledGroups = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->where('status', 'scheduled')->whereNotNull('course_group_id')->distinct('course_group_id')->count('course_group_id');
        $trackedGroups = \App\Models\AcademicPmcGroupDeliveryTracker::whereIn('course_group_id', function ($query) {
            $query->select('course_group_id')
                ->from('academic_pmc_timetable_generation_items')
                ->whereIn('generation_run_id', $this->readModels->officialPublishedGenerationRunIds())
                ->where('status', 'scheduled')
                ->whereNotNull('course_group_id');
        })->distinct('course_group_id')->count('course_group_id');
        $trackedGroupIds = \App\Models\AcademicPmcGroupDeliveryTracker::pluck('course_group_id');
        $samples = AcademicPmcCourseGroup::with(['subject', 'program', 'term'])
            ->whereIn('id', function ($query) {
                $query->select('course_group_id')
                    ->from('academic_pmc_timetable_generation_items')
                    ->whereIn('generation_run_id', $this->readModels->officialPublishedGenerationRunIds())
                    ->where('status', 'scheduled')
                    ->whereNotNull('course_group_id');
            })
            ->whereNotIn('id', $trackedGroupIds)
            ->limit(5)
            ->get()
            ->map(fn ($group) => [
                'id' => $group->id,
                'label' => trim($group->name . ' / ' . ($group->subject?->code ?: 'subject') . ' / ' . ($group->term?->name ?: 'term')),
                'status' => $group->status,
                'source' => 'course_group:' . $group->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'scheduled_groups_delivery_trackers',
            'check_group' => 'course_delivery',
            'title' => 'Scheduled course groups have delivery trackers',
            'description' => 'Course delivery monitoring should cover every scheduled course group.',
            'expected_count' => $scheduledGroups,
            'actual_count' => $trackedGroups,
            'mismatch_count' => max(0, $scheduledGroups - $trackedGroups),
            'recommended_action' => 'Refresh PMC course delivery checkpoints and group delivery trackers.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcilePublishedNotifications(User $actor): array
    {
        $publishedVersions = TimetableVersion::where('status', 'published')->count();
        $versionNotifications = AcademicPmcTimetableNotification::where('source_type', 'timetable_version')
            ->where('notification_type', 'publish')
            ->distinct('source_key')
            ->count('source_key');
        $notifiedVersionIds = AcademicPmcTimetableNotification::where('source_type', 'timetable_version')
            ->where('notification_type', 'publish')
            ->pluck('source_key')
            ->map(fn ($id) => (int) $id)
            ->all();
        $samples = TimetableVersion::where('status', 'published')
            ->whereNotIn('id', $notifiedVersionIds)
            ->limit(5)
            ->get()
            ->map(fn ($version) => [
                'id' => $version->id,
                'label' => 'Timetable version #' . $version->version_number . ' / ' . ($version->name ?: 'Published timetable'),
                'status' => $version->status,
                'source' => 'timetable_version:' . $version->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'published_version_notifications',
            'check_group' => 'notifications',
            'title' => 'Published timetable versions have notification records',
            'description' => 'Every published timetable version should have at least one publish notification audit record.',
            'expected_count' => $publishedVersions,
            'actual_count' => $versionNotifications,
            'mismatch_count' => max(0, $publishedVersions - $versionNotifications),
            'recommended_action' => 'Requeue publish notifications or review missing notification audit rows.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function storeReconciliationCheck(User $actor, array $data): array
    {
        $mismatch = (int) ($data['mismatch_count'] ?? 0);
        $status = $mismatch === 0 ? 'ok' : ($mismatch >= 5 ? 'block' : 'warn');
        $severity = $mismatch === 0 ? 'low' : ($mismatch >= 5 ? 'critical' : 'medium');

        $record = AcademicPmcDataReconciliationCheck::updateOrCreate(
            [
                'check_key' => $data['check_key'],
                'source_type' => $data['source_type'] ?? 'global',
                'source_key' => $data['source_key'] ?? 'all',
            ],
            [
                'check_group' => $data['check_group'],
                'status' => $status,
                'severity' => $severity,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'expected_count' => $data['expected_count'] ?? 0,
                'actual_count' => $data['actual_count'] ?? 0,
                'mismatch_count' => $mismatch,
                'recommended_action' => $data['recommended_action'] ?? null,
                'details' => ['version' => 'PMC OS v0.092'] + ($data['details'] ?? []),
                'checked_by' => $actor->id,
                'checked_at' => now(),
            ]
        );

        return $record->only(['check_key', 'status', 'severity', 'mismatch_count']);
    }

}
