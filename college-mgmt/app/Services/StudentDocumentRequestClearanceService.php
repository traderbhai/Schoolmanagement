<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\FeeDemand;
use App\Models\HostelFeeDemand;

class StudentDocumentRequestClearanceService
{
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    public function __construct(private LibraryFineService $libraryFineService) {}

    public function activeStudentBlocker(DocumentRequest $documentRequest): ?string
    {
        $student = $documentRequest->student;

        if (! $student) {
            return 'Document request cannot be processed because the student profile is missing.';
        }

        return $student->status === 'active'
            ? null
            : 'Document request cannot be processed because the student profile is not active.';
    }

    public function nocClearanceBlocker(DocumentRequest $documentRequest): ?string
    {
        if ($documentRequest->document_type !== 'noc') {
            return null;
        }

        $studentUser = $documentRequest->student?->user;
        if (! $studentUser) {
            return 'NOC cannot be processed because the student user record is missing.';
        }

        $openFeeBalance = FeeDemand::where('student_id', $documentRequest->student_id)
            ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
            ->get(['final_amount', 'penalty_amount'])
            ->sum(fn (FeeDemand $demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));

        if ($openFeeBalance > 0) {
            return 'NOC cannot be processed until fee clearance is complete: INR ' . number_format($openFeeBalance, 2) . ' remains open.';
        }

        $openHostelFeeBalance = (float) HostelFeeDemand::where('student_id', $documentRequest->student_id)
            ->where('status', 'pending')
            ->sum('amount');

        if ($openHostelFeeBalance > 0) {
            return 'NOC cannot be processed until hostel fee clearance is complete: INR ' . number_format($openHostelFeeBalance, 2) . ' remains open.';
        }

        $eligibility = $this->libraryFineService->checkNocEligibility($studentUser->id);

        return $eligibility['eligible']
            ? null
            : 'NOC cannot be processed until library clearance is complete: ' . $eligibility['reason'] . '.';
    }
}
