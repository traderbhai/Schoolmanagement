<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPayment;
use App\Services\AdmissionPaymentGatewayService;
use App\Services\DepartmentHierarchyService;

class GatewayPaymentController extends Controller
{
    public function initiate(AdmissionPayment $payment, AdmissionPaymentGatewayService $gateway, DepartmentHierarchyService $hierarchy)
    {
        abort_unless($hierarchy->isFeatureEnabled('ADM', 'admission.gateway_payments'), 403, 'Online admission payments are currently disabled.');

        $applicant = auth()->user()->applicant;
        if (!$applicant || $payment->applicant_id !== $applicant->id) {
            abort(403);
        }

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending admission payments can be paid online.');
        }

        $order = $gateway->createOrder($payment);

        return back()->with('success', 'Payment gateway order created: ' . $order['order_id']);
    }
}
