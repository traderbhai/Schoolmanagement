<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionIntegrationWebhookEvent;
use App\Models\AdmissionProviderDeliveryAttempt;
use App\Models\Lead;
use App\Services\AdmissionIntegrationService;
use App\Services\AdmissionProviderRegistry;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(AdmissionProviderRegistry $registry)
    {
        return view('admission.v0037.integrations', [
            'providers' => $registry->allStatuses(),
            'webhooks' => AdmissionIntegrationWebhookEvent::latest()->limit(30)->get(),
            'failed' => AdmissionCommunicationLog::where('status', 'failed')->latest()->limit(20)->get(),
            'attempts' => AdmissionProviderDeliveryAttempt::latest()->limit(30)->get(),
        ]);
    }

    public function test(Request $request, AdmissionIntegrationService $service)
    {
        $data = $request->validate(['channel' => ['required', 'in:email,sms,whatsapp,dialer,video,signature']]);
        $lead = Lead::firstOrFail();
        $service->sendSandbox($lead, $data['channel'], $request->user());

        return back()->with('success', ucfirst($data['channel']) . ' sandbox provider test completed.');
    }

    public function retry(AdmissionCommunicationLog $log, AdmissionIntegrationService $service)
    {
        $service->dispatchLog($log);

        return back()->with('success', 'Delivery retried.');
    }
}
