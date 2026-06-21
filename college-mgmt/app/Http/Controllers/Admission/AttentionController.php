<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionAttentionService;
use Illuminate\Http\Request;

class AttentionController extends Controller
{
    public function __construct(private AdmissionAttentionService $attention) {}

    public function index(Request $request)
    {
        $filters = $request->only(['program_id', 'priority', 'counsellor_id']);
        $allQueues = $this->attention->queuesFor($request->user(), $filters);
        $selectedQueue = $request->string('queue')->toString();
        $selectedQueue = array_key_exists($selectedQueue, $allQueues) ? $selectedQueue : null;
        $queues = $selectedQueue ? [$selectedQueue => $allQueues[$selectedQueue]] : $allQueues;

        return view('admission.attention.index', compact('queues', 'selectedQueue', 'filters'));
    }
}
