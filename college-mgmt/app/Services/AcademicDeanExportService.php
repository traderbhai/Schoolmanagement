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
        $rows = $this->rows($report, $actor);
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

    public function rows(string $report, User $actor): Collection
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
            default => $this->operatingRows($report),
        };
    }

    private function operatingRows(string $report): Collection
    {
        return AcademicDeanOperatingRecord::with(['owner', 'program'])
            ->where('record_type', $report)
            ->latest()
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
