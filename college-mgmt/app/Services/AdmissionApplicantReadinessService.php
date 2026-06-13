<?php

namespace App\Services;

use App\Models\AdmissionFeeInstallment;
use App\Models\Applicant;
use App\Models\RequiredDocument;

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
            'offer' => $this->offer($applicant),
            'enrollment' => $this->enrollment($applicant),
        ];
    }

    public function isEnrollmentReady(Applicant $applicant): bool
    {
        $items = $this->checklist($applicant);

        return $items['documents']['ready']
            && $items['selection']['ready']
            && $items['admission_payment']['ready']
            && $items['offer']['ready'];
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
        return $this->item(
            'Registration Fee',
            $applicant->hasRegistrationFeePaid(),
            $applicant->hasRegistrationFeePaid() ? [] : ['Registration fee is not recorded.'],
            'applicant.registration-fee.show'
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

    private function enrollment(Applicant $applicant): array
    {
        if ($applicant->isEnrolled()) {
            return $this->item('Enrollment', true, [], 'applicant.status');
        }

        $blockers = [];
        if (!$this->isEnrollmentPrecheckReady($applicant)) {
            $blockers[] = 'Selection, mandatory documents, admission payments, and accepted offer must be complete.';
        }

        return $this->item('Enrollment', false, $blockers, 'applicant.status');
    }

    private function isEnrollmentPrecheckReady(Applicant $applicant): bool
    {
        $items = [
            $this->documents($applicant),
            $this->selection($applicant),
            $this->admissionPayment($applicant),
            $this->offer($applicant),
        ];

        foreach ($items as $item) {
            if (!$item['ready']) {
                return false;
            }
        }

        return true;
    }

    private function item(string $label, bool $ready, array $blockers, string $route): array
    {
        return [
            'label' => $label,
            'ready' => $ready,
            'blockers' => array_values($blockers),
            'route' => $route,
        ];
    }
}
