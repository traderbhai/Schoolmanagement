<?php

namespace App\Services;

use App\Models\AdmissionForecastSnapshot;
use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\OfferLetter;
use App\Models\User;

class AdmissionForecastingService
{
    public function snapshot(array $filters = [], ?User $actor = null): AdmissionForecastSnapshot
    {
        $leadQuery = Lead::query()
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source));
        $applicantQuery = Applicant::query()
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($filters['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id));

        $leadCount = (clone $leadQuery)->count();
        $applicationCount = (clone $applicantQuery)->count();
        $selectionCount = (clone $applicantQuery)->where('status', 'selected')->count();
        $applicantIds = (clone $applicantQuery)->pluck('id');
        $offerCount = OfferLetter::whereIn('applicant_id', $applicantIds)->count();
        $enrollmentCount = EnrollmentConfirmation::whereIn('applicant_id', $applicantIds)->where('status', 'completed')->count();
        $rate = $leadCount > 0 ? round($applicationCount / $leadCount * 100, 2) : 0;
        $targetSeats = (int) ($filters['target_seats'] ?? max(0, $selectionCount));
        $projected = (int) round($leadCount * ($rate / 100));

        return AdmissionForecastSnapshot::create([
            'program_id' => $filters['program_id'] ?? null,
            'batch_id' => $filters['batch_id'] ?? null,
            'source' => $filters['source'] ?? null,
            'target_seats' => $targetSeats,
            'lead_count' => $leadCount,
            'application_count' => $applicationCount,
            'selection_count' => $selectionCount,
            'offer_count' => $offerCount,
            'enrollment_count' => $enrollmentCount,
            'expected_conversion_rate' => $rate,
            'projected_enrollments' => $projected,
            'projected_gap' => $projected - $targetSeats,
            'metadata' => ['filters' => $filters],
            'created_by' => $actor?->id,
        ]);
    }
}
