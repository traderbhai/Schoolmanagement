<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index()
    {
        $programs = Program::where('is_active', true)->get();
        $status = request('status');
        $source = request('source');
        $programId = request('program_id');

        $query = Lead::query();

        if ($status) {
            $query->where('status', $status);
        }
        if ($source) {
            $query->where('source', $source);
        }
        if ($programId) {
            $query->where('program_id', $programId);
        }

        $leads = $query->with(['program', 'convertedApplicant'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $stats = [
            'total'         => Lead::count(),
            'new'           => Lead::where('status', 'new')->count(),
            'contacted'     => Lead::where('status', 'contacted')->count(),
            'interested'    => Lead::where('status', 'interested')->count(),
            'converted'     => Lead::where('status', 'converted')->count(),
            'conversion_rate' => Lead::count() > 0 ? round((Lead::where('status', 'converted')->count() / Lead::count()) * 100, 2) : 0,
        ];

        return view('admission.leads.index', compact('leads', 'stats', 'programs', 'status', 'source', 'programId'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['program', 'convertedApplicant']);
        return view('admission.leads.show', compact('lead'));
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
}
