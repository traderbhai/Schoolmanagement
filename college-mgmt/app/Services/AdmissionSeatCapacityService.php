<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\SeatMatrix;

class AdmissionSeatCapacityService
{
    public function canPromoteFromWaitlist(MeritListEntry $entry, ?int $releasedApplicantId = null): bool
    {
        $entry->loadMissing('applicant');

        $matrix = $this->seatMatrix($entry->program_id, $entry->batch_id);
        $applicant = $entry->applicant;

        if (!$matrix || !$applicant) {
            return false;
        }

        if (in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true)) {
            return false;
        }

        if ($this->hasActiveOffer($applicant->id)) {
            return false;
        }

        $committedApplicantIds = $this->committedApplicantIds(
            (int) $entry->program_id,
            $entry->batch_id ? (int) $entry->batch_id : null,
            array_filter([$releasedApplicantId])
        );

        if (count($committedApplicantIds) >= (int) $matrix->total_seats) {
            return false;
        }

        $category = $this->normalizedCategory($entry->category ?: $applicant->category);
        $categoryCapacity = $this->categoryCapacity($matrix, $category);

        if ($categoryCapacity <= 0) {
            return false;
        }

        $categoryCommitments = Applicant::whereIn('id', $committedApplicantIds)
            ->get(['id', 'category'])
            ->filter(fn (Applicant $committed) => $this->normalizedCategory($committed->category) === $category)
            ->count();

        return $categoryCommitments < $categoryCapacity;
    }

    private function seatMatrix(int $programId, ?int $batchId): ?SeatMatrix
    {
        return SeatMatrix::where('program_id', $programId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->first();
    }

    private function hasActiveOffer(int $applicantId): bool
    {
        return OfferLetter::where('applicant_id', $applicantId)
            ->whereIn('status', ['issued', 'accepted'])
            ->exists();
    }

    private function committedApplicantIds(int $programId, ?int $batchId, array $excludeApplicantIds = []): array
    {
        $selected = MeritListEntry::query()
            ->where('program_id', $programId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->where('decision', 'selected')
            ->whereHas('applicant', fn ($query) => $query->whereNotIn('status', ['rejected', 'withdrawn', 'enrolled']))
            ->pluck('applicant_id')
            ->all();

        $activeOffers = OfferLetter::query()
            ->where('program_id', $programId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->whereIn('status', ['issued', 'accepted'])
            ->pluck('applicant_id')
            ->all();

        $enrollments = EnrollmentConfirmation::query()
            ->where('batch_id', $batchId)
            ->where('status', 'completed')
            ->whereHas('applicant', fn ($query) => $query->where('program_id', $programId))
            ->pluck('applicant_id')
            ->all();

        return collect([...$selected, ...$activeOffers, ...$enrollments])
            ->reject(fn ($applicantId) => in_array($applicantId, $excludeApplicantIds, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedCategory(?string $category): string
    {
        return match ($category) {
            'obc_nc', 'obc_ncl' => 'obc',
            'management', 'management_quota' => 'management',
            'defence_quota' => 'defence',
            null, '' => 'general',
            default => $category,
        };
    }

    private function categoryCapacity(SeatMatrix $matrix, string $category): int
    {
        return match ($category) {
            'obc' => (int) $matrix->obc_seats,
            'sc' => (int) $matrix->sc_seats,
            'st' => (int) $matrix->st_seats,
            'ews' => (int) $matrix->ews_seats,
            'management' => (int) $matrix->management_quota,
            'nri' => (int) $matrix->nri_quota,
            'defence' => (int) $matrix->defence_quota,
            default => (int) $matrix->general_seats,
        };
    }
}
