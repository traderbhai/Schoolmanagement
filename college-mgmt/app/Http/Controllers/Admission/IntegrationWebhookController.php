<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionWebhookService;
use Illuminate\Http\Request;

class IntegrationWebhookController extends Controller
{
    public function store(string $provider, Request $request, AdmissionWebhookService $service)
    {
        $event = $service->record($provider, $request->input('event_type', 'delivery_update'), $request->all());

        return response()->json(['ok' => true, 'event_id' => $event->id]);
    }
}
