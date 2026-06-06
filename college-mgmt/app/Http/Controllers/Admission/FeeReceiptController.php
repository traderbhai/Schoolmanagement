<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPayment;
use Barryvdh\DomPDF\Facade\Pdf;

class FeeReceiptController extends Controller
{
    public function receipt(AdmissionPayment $payment)
    {
        if ($payment->status !== 'verified') {
            return back()->with('error', 'Receipt is only available for verified payments.');
        }

        $payment->load(['applicant.user', 'applicant.program', 'applicant.batch']);

        $pdf = Pdf::loadView('admission.fee-receipts.template', [
            'payment'     => $payment,
            'applicant'   => $payment->applicant,
            'collegeName' => config('app.name', 'Institute'),
            'receiptNo'   => 'RCP-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
        ])->setPaper('a5', 'portrait');

        $filename = 'fee-receipt-' . ($payment->applicant->application_number ?? $payment->id) . '.pdf';
        return $pdf->stream($filename);
    }
}
