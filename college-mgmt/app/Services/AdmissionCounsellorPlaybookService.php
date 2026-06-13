<?php

namespace App\Services;

use App\Models\AdmissionCounsellorPlaybook;
use App\Models\Applicant;
use App\Models\Lead;
use Illuminate\Support\Collection;

class AdmissionCounsellorPlaybookService
{
    public function forSubject(Lead|Applicant|null $subject = null, ?string $type = null): Collection
    {
        $programId = $subject?->program_id;
        $stage = $subject?->status;

        return AdmissionCounsellorPlaybook::with('steps')
            ->where('is_active', true)
            ->when($type, fn ($q) => $q->where('playbook_type', $type))
            ->where(fn ($q) => $q->whereNull('program_id')->orWhere('program_id', $programId))
            ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', $stage))
            ->orderBy('playbook_type')
            ->limit(8)
            ->get();
    }
}
