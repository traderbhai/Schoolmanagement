<?php

namespace App\Services;

use App\Models\AdmissionJourney;
use App\Models\AdmissionJourneyVersion;
use App\Models\Applicant;
use App\Models\User;

class AdmissionJourneyService
{
    public function publish(AdmissionJourney $journey, array $config, ?User $actor = null): AdmissionJourneyVersion
    {
        $nextVersion = ((int) $journey->versions()->max('version')) + 1;

        return AdmissionJourneyVersion::create([
            'journey_id' => $journey->id,
            'version' => $nextVersion,
            'stages' => $config['stages'] ?? ['draft', 'submitted', 'under_review', 'selected', 'enrolled'],
            'documents' => $config['documents'] ?? [],
            'fee_milestones' => $config['fee_milestones'] ?? [],
            'session_rules' => $config['session_rules'] ?? [],
            'offer_rules' => $config['offer_rules'] ?? [],
            'enrollment_blockers' => $config['enrollment_blockers'] ?? [],
            'applicant_instructions' => $config['applicant_instructions'] ?? null,
            'is_published' => true,
            'published_by' => $actor?->id,
            'published_at' => now(),
        ]);
    }

    public function assignJourney(Applicant $applicant): ?AdmissionJourneyVersion
    {
        $version = AdmissionJourney::with('currentVersion')
            ->where('is_active', true)
            ->where(function ($query) use ($applicant) {
                $query->whereNull('program_id')->orWhere('program_id', $applicant->program_id);
            })
            ->where(function ($query) use ($applicant) {
                $query->whereNull('batch_id')->orWhere('batch_id', $applicant->batch_id);
            })
            ->latest()
            ->get()
            ->pluck('currentVersion')
            ->filter()
            ->first();

        if ($version) {
            $applicant->update(['journey_version_id' => $version->id]);
        }

        return $version;
    }

    public function checklist(Applicant $applicant): array
    {
        $version = $applicant->journeyVersion ?: $this->assignJourney($applicant);
        if (!$version) {
            return ['stages' => [], 'blockers' => ['No published journey is configured.']];
        }

        $documents = $applicant->documents()->get();

        return [
            'version' => $version->version,
            'stages' => $version->stages ?? [],
            'documents_required' => collect($version->documents ?? [])->count(),
            'documents_verified' => $documents->where('status', 'verified')->count(),
            'fee_milestones' => $version->fee_milestones ?? [],
            'blockers' => $this->blockers($applicant, $version),
            'instructions' => $version->applicant_instructions,
        ];
    }

    private function blockers(Applicant $applicant, AdmissionJourneyVersion $version): array
    {
        $blockers = [];
        if (in_array('registration_fee', $version->enrollment_blockers ?? [], true) && !$applicant->hasRegistrationFeePaid()) {
            $blockers[] = 'Registration fee is not paid.';
        }
        if (in_array('verified_documents', $version->enrollment_blockers ?? [], true) && $applicant->documents()->where('status', 'rejected')->exists()) {
            $blockers[] = 'Rejected documents need correction.';
        }

        return $blockers;
    }
}
