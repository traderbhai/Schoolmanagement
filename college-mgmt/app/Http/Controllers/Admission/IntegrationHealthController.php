<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionIntegrationHealthService;
use App\Services\AdmissionVendorAdapterRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationHealthController extends Controller
{
    public function index(AdmissionVendorAdapterRegistry $registry)
    {
        $registry->ensureDefaults();
        return view('admission.v0038.integration-health', [
            'providers' => DB::table('admission_integration_providers')->orderBy('channel')->get(),
            'health' => DB::table('admission_integration_health_checks')->latest()->limit(30)->get(),
            'retries' => DB::table('admission_integration_retry_queue')->latest()->limit(30)->get(),
            'docs' => $registry->expectedAdapters(),
        ]);
    }

    public function check(AdmissionIntegrationHealthService $service)
    {
        $service->checkAll();
        return back()->with('success', 'Provider health check completed.');
    }

    public function retry(int $retryId, AdmissionIntegrationHealthService $service)
    {
        $service->retryFailed($retryId);
        return back()->with('success', 'Retry queue entry updated.');
    }
}
