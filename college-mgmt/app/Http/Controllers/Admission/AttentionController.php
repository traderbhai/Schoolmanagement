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
        $queues = $this->attention->queuesFor($request->user(), $request->only(['program_id', 'priority', 'counsellor_id']));

        return view('admission.attention.index', compact('queues'));
    }
}
