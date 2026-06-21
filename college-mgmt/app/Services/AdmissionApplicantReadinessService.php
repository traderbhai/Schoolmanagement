<?php

namespace App\Services;

use App\Models\AdmissionFeeInstallment;
use App\Models\Applicant;
use App\Models\RequiredDocument;
use Illuminate\Support\Facades\DB;

class AdmissionApplicantReadinessService
{
    public function checklist(Applicant $applicant): array
    {
        $applicant->loadMissing(['documents.requiredDocument', 'payments.installment', 'offerLetter', 'enrollmentConfirmation']);

        return [
            'profile' => $this->profile($applicant),
            'documents' => $this->documents($applicant),
            'registration_fee' => $this->registrationFee($applicant),
            'selection' => $this->selection($applicant),
            'admission_payment' => $this->admissionPayment($applicant),
            'assessment' => $this->assessment($applicant),
            'offer' => $this->offer($applicant),
            'seat_hold' => $this->seatHold($applicant),
            'joining_kit' => $this->joiningKit($applicant),
            'enrollment' => $this->enrollment($applicant),
            'handoff' => $this->handoff($applicant),
        ];
    }

    public function isEnrollmentReady(Applicant $applicant): bool
    {
        $items = $this->checklist($applicant);

        return $items['documents']['ready']
            && $items['selection']['ready']
            && $items['admission_payment']['ready']
            && $items['assessment']['ready']
            && $items['offer']['ready']
            && $items['seat_hold']['ready']
            && $items['joining_kit']['ready'];
    }

    private function profile(Applicant $applicant): array
    {
        $sections = ['personal', 'academic', 'family', 'additional'];
        $missing = [];

        foreach ($sections as $section) {
            if (empty($applicant->getFormDataForSection($section))) {
                $missing[] = ucfirst($section) . ' section is incomplete.';
            }
        }

        return $this->item('Profile', empty($missing), $missing, 'applicant.application.show');
    }

    private function documents(Applicant $applicant): array
    {
        $mandatory = RequiredDocument::where('program_id', $applicant->program_id)
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->get();

        $documentsByRequiredId = $applicant->documents->keyBy('required_document_id');
        $blockers = [];

        foreach ($mandatory as $requiredDocument) {
            $document = $documentsByRequiredId->get($requiredDocument->id);
            if (!$document) {
                $blockers[] = $requiredDocument->name . ' has not been uploaded.';
                continue;
            }

            if ($document->status === 'rejected') {
                $reason = $document->rejection_reason ? ' Reason: ' . $document->rejection_reason : '';
                $blockers[] = $requiredDocument->name . ' was rejected.' . $reason;
                continue;
            }

            if ($document->status !== 'verified') {
                $blockers[] = $requiredDocument->name . ' is pending verification.';
            }
        }

        return $this->item('Mandatory Documents', empty($blockers), $blockers, 'applicant.documents.index');
    }

    private function registrationFee(Applicant $applicant): array
    {
        if ($applicant->hasRegistrationFeePaid()) {
            return $this->item('Registration Fee', true, [], 'applicant.registration-fee.show');
        }

        if ($applicant->status !== 'draft') {
            return $this->item(
                'Registration Fee Not Recorded',
                false,
                ['Your application has already been submitted. Track the registration fee with the admission team instead of submitting details again.'],
                'applicant.status',
                'Track Status'
            );
        }

        return $this->item(
            'Registration Fee',
            false,
            ['Registration fee is not recorded.'],
            'applicant.registration-fee.show',
            'Submit Fee Details'
        );
    }

    private function selection(Applicant $applicant): array
    {
        $ready = in_array($applicant->status, ['selected', 'enrolled'], true);

        return $this->item(
            'Selection',
            $ready,
            $ready ? [] : ['Applicant is not selected yet. Current status: ' . $applicant->status_label . '.'],
            'applicant.status'
        );
    }

    private function admissionPayment(Applicant $applicant): array
    {
        $installments = AdmissionFeeInstallment::where('program_id', $applicant->program_id)
            ->where(function ($query) use ($applicant) {
                $query->whereNull('batch_id')->orWhere('batch_id', $applicant->batch_id);
            })
            ->where('is_active', true)
            ->get();

        if ($installments->isEmpty()) {
            return $this->item('Admission Payments', true, [], 'applicant.fees.index');
        }

        $verifiedByInstallment = $applicant->payments
            ->where('status', 'verified')
            ->keyBy('admission_fee_installment_id');

        $blockers = [];
        foreach ($installments as $installment) {
            if (!$verifiedByInstallment->has($installment->id)) {
                $blockers[] = $installment->name . ' is not verified.';
            }
        }

        return $this->item('Admission Payments', empty($blockers), $blockers, 'applicant.fees.index');
    }

    private function offer(Applicant $applicant): array
    {
        $offer = $applicant->offerLetter;
        if (!$offer) {
            return $this->item('Offer', false, ['Offer letter has not been issued.'], 'applicant.offer-letters.index');
        }

        if ($offer->status !== 'accepted') {
            $message = $offer->status === 'issued'
                ? 'Offer is issued and awaiting acceptance before ' . optional($offer->acceptance_deadline)->format('d M Y') . '.'
                : 'Offer status is ' . ucfirst($offer->status) . '.';

            return $this->item('Offer', false, [$message], 'applicant.offer-letters.index');
        }

        return $this->item('Offer', true, [], 'applicant.offer-letters.index');
    }

    private function assessment(Applicant $applicant): array
    {
        $slot = DB::table('admission_assessment_slot_assignments')->where('applicant_id', $applicant->id)->latest()->first();
        $pendingSubmission = DB::table('admission_assessment_submissions')
            ->where('applicant_id', $applicant->id)
            ->whereIn('status', ['pending', 'missing', 'late'])
            ->exists();

        $blockers = [];
        if (! $slot && in_array($applicant->status, ['shortlisted', 'under_review'], true)) {
            $blockers[] = 'Assessment slot is not assigned yet.';
        }
        if ($pendingSubmission) {
            $blockers[] = 'Assessment submission is pending or marked for review.';
        }

        return $this->item('Assessment Slot & Submission', empty($blockers), $blockers, 'applicant.admission-operations.index');
    }

    private function seatHold(Applicant $applicant): array
    {
        $hold = DB::table('admission_seat_holds')->where('applicant_id', $applicant->id)->latest()->first();
        if (! $hold && in_array($applicant->status, ['selected', 'enrolled'], true)) {
            return $this->item('Seat Hold', false, ['Seat hold is not recorded yet.'], 'applicant.admission-operations.index');
        }

        if ($hold && $hold->status === 'held' && $hold->expires_at && now()->greaterThan($hold->expires_at)) {
            return $this->item('Seat Hold', false, ['Seat hold has expired and needs staff review.'], 'applicant.admission-operations.index');
        }

        return $this->item('Seat Hold', true, [], 'applicant.admission-operations.index');
    }

    private function joiningKit(Applicant $applicant): array
    {
        $tasks = DB::table('admission_joining_kit_tasks')->where('applicant_id', $applicant->id)->get();
        $pending = $tasks->where('status', '!=', 'completed');

        return $this->item(
            'Joining Kit',
            $pending->isEmpty(),
            $pending->pluck('title')->map(fn ($title) => $title . ' is pending.')->values()->all(),
            'applicant.admission-operations.index'
        );
    }

    private function enrollment(Applicant $applicant): array
    {
        if ($applicant->isEnrolled()) {
            return $this->item('Enrollment', true, [], 'applicant.status');
        }

        $blockers = [];
        if (!$this->isEnrollmentPrecheckReady($applicant)) {
            $blockers[] = 'Selection, mandatory documents, admission payments, accepted offer, assessment, seat hold, and joining kit must be complete.';
        }

        return $this->item('Enrollment', false, $blockers, 'applicant.status');
    }

    private function handoff(Applicant $applicant): array
    {
        $handoff = DB::table('admission_handoff_records')->where('applicant_id', $applicant->id)->first();
        if (! $handoff) {
            return $this->item('Academics/PMC Handoff', false, ['Handoff has not been prepared yet.'], 'applicant.admission-operations.index');
        }

        $blockers = json_decode($handoff->blockers ?? '[]', true) ?: [];

        return $this->item(
            'Academics/PMC Handoff',
            in_array($handoff->status, ['ready_for_academics', 'handed_off'], true),
            $blockers ?: ['Current handoff status is ' . str_replace('_', ' ', $handoff->status) . '.'],
            'applicant.admission-operations.index'
        );
    }

    private function isEnrollmentPrecheckReady(Applicant $applicant): bool
    {
        $items = [
            $this->documents($applicant),
            $this->selection($applicant),
            $this->admissionPayment($applicant),
            $this->assessment($applicant),
            $this->offer($applicant),
            $this->seatHold($applicant),
            $this->joiningKit($applicant),
        ];

        foreach ($items as $item) {
            if (!$item['ready']) {
                return false;
            }
        }

        return true;
    }

    private function item(string $label, bool $ready, array $blockers, string $route, ?string $actionLabel = null): array
    {
        return [
            'label' => $label,
            'ready' => $ready,
            'blockers' => array_values($blockers),
            'route' => $route,
            'action_label' => $actionLabel,
        ];
    }
}
