<?php

namespace App\Services;

use App\Models\AdmissionDataQualityFlag;
use App\Models\Applicant;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdmissionDataQualityService
{
    public function scanLead(Lead $lead): Collection
    {
        return collect([
            !$lead->phone ? $this->flag($lead, 'missing_phone', 'warning', 'Lead has no phone number.', 100) : null,
            $lead->email && !filter_var($lead->email, FILTER_VALIDATE_EMAIL) ? $this->flag($lead, 'invalid_email', 'warning', 'Lead email is invalid.', 100) : null,
            !$lead->source ? $this->flag($lead, 'missing_source', 'info', 'Lead source is missing.', 100) : null,
            $lead->assigned_to && !$lead->current_handler_user_id ? $this->flag($lead, 'stale_owner_chain', 'warning', 'Current handler is not synced.', 90) : null,
        ])->filter()->values();
    }

    public function scanApplicant(Applicant $applicant): Collection
    {
        return collect([
            !$applicant->assigned_to && in_array($applicant->status, ['submitted', 'under_review', 'shortlisted'], true)
                ? $this->flag($applicant, 'stale_owner', 'warning', 'Applicant needs an assigned counsellor.', 90)
                : null,
            $applicant->status === 'selected' && $applicant->outstanding_amount > 0
                ? $this->flag($applicant, 'unpaid_selected_applicant', 'danger', 'Selected applicant has unpaid admission amount.', 95)
                : null,
            $applicant->status === 'selected' && $applicant->documents()->where('status', 'rejected')->exists()
                ? $this->flag($applicant, 'enrollment_blocker_mismatch', 'danger', 'Selected applicant still has rejected documents.', 95)
                : null,
        ])->filter()->values();
    }

    public function duplicateCandidates(Lead $lead): Collection
    {
        $normalizedEmail = Str::lower((string) $lead->email);
        $normalizedPhone = preg_replace('/\D+/', '', (string) $lead->phone);

        return Lead::where('id', '!=', $lead->id)
            ->where(function ($query) use ($lead, $normalizedEmail, $normalizedPhone) {
                if ($normalizedEmail !== '') {
                    $query->orWhereRaw('lower(email) = ?', [$normalizedEmail]);
                }
                if ($normalizedPhone !== '') {
                    $query->orWhere('phone', 'like', '%' . substr($normalizedPhone, -7) . '%');
                }
                $query->orWhere(function ($q) use ($lead) {
                    $q->where('program_id', $lead->program_id)->where('name', 'like', '%' . $lead->name . '%');
                });
            })
            ->limit(10)
            ->get()
            ->map(fn (Lead $candidate) => [
                'lead' => $candidate,
                'confidence' => $this->duplicateConfidence($lead, $candidate),
            ]);
    }

    public function flag(Model $subject, string $type, string $severity, string $message, float $confidence, array $metadata = []): AdmissionDataQualityFlag
    {
        return AdmissionDataQualityFlag::updateOrCreate(
            [
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'flag_type' => $type,
                'status' => 'open',
            ],
            [
                'severity' => $severity,
                'message' => $message,
                'confidence' => $confidence,
                'metadata' => $metadata,
            ]
        );
    }

    private function duplicateConfidence(Lead $lead, Lead $candidate): float
    {
        $score = 0;
        if ($lead->email && Str::lower($lead->email) === Str::lower((string) $candidate->email)) {
            $score += 55;
        }
        if ($lead->phone && substr(preg_replace('/\D+/', '', $lead->phone), -7) === substr(preg_replace('/\D+/', '', (string) $candidate->phone), -7)) {
            $score += 35;
        }
        if ($lead->program_id && $lead->program_id === $candidate->program_id && Str::lower($lead->name) === Str::lower($candidate->name)) {
            $score += 25;
        }

        return min(100, $score);
    }
}
