<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Program;
use App\Services\DepartmentHierarchyService;
use App\Services\AdmissionNextActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $programs = Program::where('is_active', true)->get();
        $status = request('status');
        $source = request('source');
        $programId = request('program_id');
        $search = request('search');

        $query = Lead::query();
        $this->hierarchy->applyLeadVisibility($query, $request->user(), 'ADM');

        if ($search) {
            $query->where(function ($scope) use ($search) {
                $scope->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($source) {
            $query->where('source', $source);
        }
        if ($programId) {
            $query->where('program_id', $programId);
        }
        if ($request->counsellor_id) {
            $query->where('assigned_to', $request->counsellor_id);
        }

        $sort = request('sort', 'created_at');
        $direction = request('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'name' => 'name',
            'source' => 'source',
            'status' => 'status',
            'priority' => 'priority',
            'last_contacted_at' => 'last_contacted_at',
            'created_at' => 'created_at',
            'sla_due_at' => 'sla_due_at',
        ];

        $perPage = min(100, max(10, (int) request('per_page', 25)));
        $leads = $query->with(['program', 'convertedApplicant', 'assignedTo'])
            ->orderBy($sortMap[$sort] ?? 'created_at', $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $statsQuery = Lead::query();
        $this->hierarchy->applyLeadVisibility($statsQuery, $request->user(), 'ADM');
        $totalLeads = (clone $statsQuery)->count();
        $convertedLeads = (clone $statsQuery)->where('status', 'converted')->count();
        $stats = [
            'total'         => $totalLeads,
            'new'           => (clone $statsQuery)->where('status', 'new')->count(),
            'contacted'     => (clone $statsQuery)->where('status', 'contacted')->count(),
            'interested'    => (clone $statsQuery)->where('status', 'interested')->count(),
            'converted'     => $convertedLeads,
            'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0,
        ];

        return view('admission.leads.index', compact('leads', 'stats', 'programs', 'status', 'source', 'programId', 'search', 'sort', 'direction'));
    }

    public function show(Lead $lead, AdmissionNextActionService $nextActions)
    {
        if (!$this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $lead->assigned_to, true)) {
            abort(403);
        }

        $lead->load(['program', 'convertedApplicant', 'assignmentEvents.fromUser', 'assignmentEvents.toUser', 'assignmentEvents.assignedBy', 'tags', 'assignedTo']);
        $actionCenter = $nextActions->forLead($lead);

        return view('admission.leads.show', compact('lead', 'actionCenter'));
    }

    public function contactLead(Request $request, Lead $lead)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $lead->markContacted();
        if ($request->notes) {
            $lead->update(['notes' => $request->notes]);
        }

        return back()->with('success', 'Lead marked as contacted.');
    }

    public function markInterested(Request $request, Lead $lead)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $lead->markInterested();
        if ($request->notes) {
            $lead->update(['notes' => $request->notes]);
        }

        return back()->with('success', 'Lead marked as interested.');
    }

    public function markNotInterested(Request $request, Lead $lead)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $lead->markNotInterested();
        if ($request->notes) {
            $lead->update(['notes' => $request->notes]);
        }

        return back()->with('success', 'Lead marked as not interested.');
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:leads,id',
            'status' => 'required|in:contacted,interested,not_interested',
        ]);

        $status = $request->status;
        Lead::whereIn('id', $request->lead_ids)->update([
            'status' => $status,
            'last_contacted_at' => now(),
        ]);

        return back()->with('success', 'Updated ' . count($request->lead_ids) . ' leads.');
    }

    public function analytics()
    {
        $totalLeads = Lead::count();
        $conversionRate = $totalLeads > 0 ? round((Lead::where('status', 'converted')->count() / $totalLeads) * 100, 2) : 0;

        $leadsBySource = DB::table('leads')
            ->selectRaw('source, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('source')
            ->get()
            ->map(function ($item) {
                $item->conversion_rate = $item->total > 0 ? round(($item->converted / $item->total) * 100, 2) : 0;
                return $item;
            });

        $leadsByProgram = DB::table('leads')
            ->leftJoin('programs', 'leads.program_id', '=', 'programs.id')
            ->selectRaw('programs.name, COUNT(leads.id) as total, SUM(CASE WHEN leads.status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('programs.id', 'programs.name')
            ->get()
            ->map(function ($item) {
                $item->conversion_rate = $item->total > 0 ? round(($item->converted / $item->total) * 100, 2) : 0;
                return $item;
            });

        $leadsOverTime = DB::table('leads')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admission.leads.analytics', compact(
            'totalLeads', 'conversionRate', 'leadsBySource', 'leadsByProgram', 'leadsOverTime'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:leads,email',
            'phone'       => 'nullable|string|max:20',
            'program_id'  => 'nullable|exists:programs,id',
            'source'      => 'nullable|in:web_form,referral,advertisement,social_media,event,agent,other',
        ]);

        Lead::create(array_merge($validated, [
            'source' => $validated['source'] ?? 'web_form',
        ]));

        return back()->with('success', 'Lead captured successfully!');
    }

    public function convert(Request $request, Lead $lead)
    {
        if ($lead->isConverted()) {
            return back()->with('error', 'Lead has already been converted.');
        }

        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'batch_id'   => 'nullable|exists:batches,id',
        ]);

        // Reuse existing user or create new one
        $user = \App\Models\User::firstOrCreate(
            ['email' => $lead->email],
            [
                'name'     => $lead->name,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
            ]
        );

        if (!$user->hasRole('applicant')) {
            $user->assignRole('applicant');
        }

        $leadLabel = trim(($lead->name ?: 'Unnamed lead') . ' - ' . ($lead->email ?: $lead->phone ?: $lead->source_label ?: 'No contact on file'));

        $applicant = \App\Models\Applicant::create([
            'user_id'    => $user->id,
            'program_id' => $validated['program_id'],
            'batch_id'   => $validated['batch_id'] ?? null,
            'status'     => 'draft',
            'notes'      => "Converted from lead {$leadLabel}",
        ]);

        $lead->convertToApplicant($applicant);

        return redirect()
            ->route('admission.applicants.show', $applicant)
            ->with('success', "Lead converted to applicant successfully. Application #{$applicant->application_number}");
    }

    public function exportCsv(Request $request)
    {
        $query = Lead::with(['program', 'assignedTo', 'convertedApplicant']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->source) {
            $query->where('source', $request->source);
        }
        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        $filename = 'leads-export-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($leads) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Program', 'Source', 'Status',
                'Assigned To', 'Last Contacted', 'Notes', 'Created At', 'Converted At']);
            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->name,
                    $lead->email,
                    $lead->phone ?? '',
                    $lead->program->name ?? '',
                    $lead->source_label ?? $lead->source,
                    $lead->status,
                    $lead->assignedTo->name ?? '',
                    $lead->last_contacted_at?->format('Y-m-d H:i') ?? '',
                    $lead->notes ?? '',
                    $lead->created_at->format('Y-m-d H:i'),
                    $lead->converted_at?->format('Y-m-d H:i') ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
