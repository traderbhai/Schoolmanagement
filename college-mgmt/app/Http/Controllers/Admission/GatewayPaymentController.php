<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionPaymentGatewayService;
use Illuminate\Http\Request;

class GatewayPaymentController extends Controller
{
    public function webhook(Request $request, AdmissionPaymentGatewayService $gateway)
    {
        $event = $gateway->handleWebhook($request->all(), $request->get('provider', 'razorpay_mock'));

        return response()->json([
            'ok' => true,
            'event_id' => $event->id,
            'processed_at' => optional($event->processed_at)->toISOString(),
        ]);
    }
}
