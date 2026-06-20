<?php

namespace App\Services;

use App\Models\AcademicDeanExportLog;
use App\Models\AcademicDeanOperatingRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicDeanExportService
{
    public function __construct(
        private AcademicDeanCommandService $command,
        private AcademicDeanRiskService $risk,
        private AcademicDeanAttentionService $attention
    ) {}

    public function export(string $report, User $actor, array $filters = []): StreamedResponse
    {
        $rows = $this->rows($report, $actor, $filters);
        AcademicDeanExportLog::create([
            'user_id' => $actor->id,
            'report_key' => $report,
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'Academics OS v0.08', 'formats' => ['csv', 'xlsx_ready', 'pdf_ready_html']],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['title', 'subtitle', 'status', 'severity', 'route']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['title'] ?? $row['label'] ?? '',
                    $row['subtitle'] ?? collect($row['metrics'] ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->join('; '),
                    $row['status'] ?? $row['band'] ?? '',
                    $row['severity'] ?? $row['band'] ?? '',
                    $row['route'] ?? '',
                ]);
            }
            fclose($out);
        }, 'dean-' . $report . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function rows(string $report, User $actor, array $filters = []): Collection
    {
        return match ($report) {
            'branch_health' => $this->command->branchHealth($actor),
            'program_risk' => $this->risk->programRisks()->map(fn ($risk) => [
                'title' => $risk['program']->name,
                'subtitle' => $risk['reasons']->join('; '),
                'band' => $risk['band'],
                'route' => $risk['route'],
            ]),
            'approval_sla' => $this->attention->queue('pending_dean_approvals')['items'],
            'handoff_readiness' => $this->attention->queue('admission_handoff_blockers')['items'],
            default => $this->operatingRows($report, $filters),
        };
    }

    private function operatingRows(string $report, array $filters = []): Collection
    {
        $sortMap = [
            'title' => 'title',
            'status' => 'status',
            'severity' => 'severity',
            'score' => 'score',
            'due_at' => 'due_at',
            'created_at' => 'created_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'due_at'] ?? 'due_at';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return AcademicDeanOperatingRecord::with(['owner', 'program'])
            ->where('record_type', $report)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($q, $severity) => $q->where('severity', $severity))
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->when($filters['owner_user_id'] ?? null, fn ($q, $ownerId) => $q->where('owner_user_id', $ownerId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($scope) use ($search) {
                    $scope->where('title', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%")
                        ->orWhere('source_key', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (AcademicDeanOperatingRecord $record) => [
                'title' => $record->title,
                'subtitle' => trim(($record->program?->code ?? 'All programs') . ' | Owner: ' . ($record->owner?->name ?? 'Unassigned')),
                'status' => $record->status,
                'severity' => $record->severity,
                'route' => route('academics.dean-os.reports'),
            ]);
    }
}
