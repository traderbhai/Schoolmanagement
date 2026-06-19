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

        $hierarchy = app(DepartmentHierarchyService::class);
        if (! $request->user()->hasRole('admin') && ! $hierarchy->canSeeAll($request->user(), 'ADM')) {
            $visibleUserIds = $hierarchy->visibleUserIds($request->user(), 'ADM');
            $query->where(function ($scope) use ($visibleUserIds) {
                $scope->whereIn('assigned_to', $visibleUserIds)
                    ->orWhereNull('assigned_to')
                    ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->whereIn('assigned_to', $visibleUserIds)->orWhereNull('assigned_to'));
            });
        }

        if ($counsellorId) {
            abort_unless($hierarchy->canViewAssignedUser($request->user(), 'ADM', (int) $counsellorId, true), 403);
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
        abort_unless($this->canAccessLead($request, $lead), 403);

        $validated = $request->validate([
            'type'         => 'required|in:call,email,meeting,visit',
            'scheduled_at' => 'required|date|after:now',
            'assigned_to'  => 'nullable|exists:users,id',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['assigned_to'])) {
            abort_unless(app(DepartmentHierarchyService::class)->canViewAssignedUser($request->user(), 'ADM', (int) $validated['assigned_to'], true), 403);
        }

        $lead->followUps()->create($validated);

        return back()->with('success', 'Follow-up scheduled successfully.');
    }

    // Mark a follow-up as completed
    public function complete(Request $request, LeadFollowUp $followUp)
    {
        abort_unless($this->canAccessFollowUp($request, $followUp), 403);

        if ($followUp->completed_at) {
            return back()->with('error', 'This follow-up is already completed and cannot be completed again.');
        }

        $followUp->update(['completed_at' => now()]);
        return back()->with('success', 'Follow-up marked as completed.');
    }

    // Assign a lead to a counsellor
    public function assign(Request $request, Lead $lead)
    {
        abort_unless($this->canAccessLead($request, $lead), 403);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);
        abort_unless(app(DepartmentHierarchyService::class)->canViewAssignedUser($request->user(), 'ADM', (int) $validated['assigned_to'], true), 403);

        $lead->update([
            'assigned_to' => $validated['assigned_to'],
            'assigned_at' => now(),
        ]);
        return back()->with('success', 'Lead assigned to counsellor.');
    }

    private function canAccessFollowUp(Request $request, LeadFollowUp $followUp): bool
    {
        $followUp->loadMissing('lead');

        return app(DepartmentHierarchyService::class)->canViewAssignedUser($request->user(), 'ADM', $followUp->assigned_to, true)
            || ($followUp->lead && $this->canAccessLead($request, $followUp->lead));
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        return app(DepartmentHierarchyService::class)->canViewAssignedUser($request->user(), 'ADM', $lead->assigned_to, true);
    }
}
