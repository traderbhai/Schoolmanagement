<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeadFollowUpController extends Controller
{
    // Show the follow-up calendar
    public function calendar(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        try {
            $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            $startOfMonth = now()->startOfMonth();
        }
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $counsellorId = $request->get('counsellor_id');
        $counsellors = User::whereHas('roles', function ($query) {
            $query->whereIn('name', DepartmentHierarchyService::ADMISSION_ROLE_NAMES);
        })->orderBy('name')->get();

        $query = LeadFollowUp::with(['lead.program', 'counsellor'])
            ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
            ->orderBy('scheduled_at');

        if ($counsellorId) {
            $query->where('assigned_to', $counsellorId);
        }

        $followUps = $query->get();

        // Group by date string for calendar rendering
        $byDate = $followUps->groupBy(fn($f) => $f->scheduled_at->format('Y-m-d'));

        return view('admission.leads.follow-ups.calendar', compact(
            'followUps', 'byDate', 'counsellors', 'counsellorId',
            'month', 'startOfMonth', 'endOfMonth'
        ));
    }

    // Schedule a follow-up for a lead
    public function store(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type'         => 'required|in:call,email,meeting,visit',
            'scheduled_at' => 'required|date|after:now',
            'assigned_to'  => 'nullable|exists:users,id',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $lead->followUps()->create($validated);

        return back()->with('success', 'Follow-up scheduled successfully.');
    }

    // Mark a follow-up as completed
    public function complete(LeadFollowUp $followUp)
    {
        $followUp->update(['completed_at' => now()]);
        return back()->with('success', 'Follow-up marked as completed.');
    }

    // Assign a lead to a counsellor
    public function assign(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);
        $lead->update([
            'assigned_to' => $validated['assigned_to'],
            'assigned_at' => now(),
        ]);
        return back()->with('success', 'Lead assigned to counsellor.');
    }
}
