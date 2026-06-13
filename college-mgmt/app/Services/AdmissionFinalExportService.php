<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionFinalExportService
{
    public function rows(string $type, array $filters = []): array
    {
        return match ($type) {
            'handoff' => DB::table('admission_handoff_records')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
            'communication-safety' => DB::table('admission_blocked_communications')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
            'consent' => DB::table('admission_consent_records')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
            'assessment-scheduling' => DB::table('admission_assessment_slot_assignments')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
            'offer-seat-control' => DB::table('admission_seat_holds')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
            'route-policy' => app(AdmissionAccessPolicyService::class)->auditCoverage(),
            default => DB::table('admission_export_view_logs')->latest()->limit(500)->get()->map(fn ($r) => (array) $r)->all(),
        };
    }

    public function log(string $type, string $surface, array $filters, int $rowCount, ?User $actor = null): void
    {
        DB::table('admission_export_view_logs')->insert([
            'export_type' => $type,
            'surface' => $surface,
            'actor_user_id' => $actor?->id,
            'filters' => json_encode($filters),
            'row_count' => $rowCount,
            'file_name' => "admission-{$type}-".now()->format('YmdHis').'.csv',
            'metadata' => json_encode(['v' => '0.039']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
