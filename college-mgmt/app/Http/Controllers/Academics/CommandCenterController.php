<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicAttentionService;
use Illuminate\Http\Request;

class CommandCenterController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicAttentionService $attention
    ) {}

    public function index(Request $request)
    {
        $this->policy->authorizeRead($request->user());

        $data = $this->attention->commandCenter($request->user(), 'command');

        return view('academics.command-center.index', $data + ['title' => 'Academics Command Center']);
    }

    public function workspace(Request $request, string $workspace)
    {
        $this->policy->authorizeRead($request->user());
        abort_unless(in_array($workspace, ['dean', 'pmc', 'coe', 'iqac', 'program'], true), 404);

        $data = $this->attention->commandCenter($request->user(), $workspace);

        return view('academics.command-center.index', $data + ['title' => $this->workspaceTitle($workspace)]);
    }

    public function queue(Request $request, string $queue)
    {
        $this->policy->authorizeRead($request->user());

        $queueData = $this->attention->queue($request->user(), $queue);

        return view('academics.command-center.queue', compact('queueData'));
    }

    private function workspaceTitle(string $workspace): string
    {
        return match ($workspace) {
            'dean' => 'Dean Workspace',
            'pmc' => 'PMC Workspace',
            'coe' => 'CoE / Examination Workspace',
            'iqac' => 'IQAC Workspace',
            'program' => 'Program Leadership Workspace',
            default => 'Academics Workspace',
        };
    }
}
