<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentGrievance;
use App\Models\User;
use Illuminate\Http\Request;

class GrievanceController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentGrievance::with(['student.user', 'assignedTo'])->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('priority')) $query->where('priority', $request->priority);

        $grievances = $query->paginate(25)->withQueryString();

        $openCount   = StudentGrievance::whereIn('status', ['open', 'under_review', 'escalated'])->count();
        $urgentCount = StudentGrievance::where('priority', 'urgent')->whereIn('status', ['open', 'under_review', 'escalated'])->count();
        $overdueCount = StudentGrievance::whereIn('status', ['open', 'under_review', 'escalated'])
            ->where('created_at', '<', now()->subDays(7))
            ->count();
        $resolvedCount = StudentGrievance::where('status', 'resolved')->count();
        $grievancePriority = $this->grievancePriority($urgentCount, $overdueCount, $openCount);

        return view('admin.grievances.index', compact('grievances', 'openCount', 'urgentCount', 'overdueCount', 'resolvedCount', 'grievancePriority'));
    }

    public function show(StudentGrievance $grievance)
    {
        $grievance->load(['student.user', 'student.program', 'assignedTo']);
        $staffUsers = User::whereHas('roles', fn($q) =>
            $q->whereIn('name', ['admin', 'dean_academics', 'exam_cell', 'hod'])
        )->get(['id', 'name']);

        return view('admin.grievances.show', compact('grievance', 'staffUsers'));
    }

    public function update(Request $request, StudentGrievance $grievance)
    {
        $data = $request->validate([
            'status'      => 'required|in:open,under_review,escalated,resolved,closed',
            'resolution_notes'  => 'nullable|string|max:3000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $resolutionNotes = trim((string) ($data['resolution_notes'] ?? $grievance->resolution_notes ?? ''));

        if (in_array($data['status'], ['resolved', 'closed'], true) && $resolutionNotes === '') {
            return back()->withErrors(['resolution_notes' => 'Resolution notes are required before resolving or closing a grievance.']);
        }

        if (in_array($data['status'], ['resolved', 'closed'], true) && !$grievance->resolved_at) {
            $data['resolved_at'] = now();
            $data['resolved_by'] = auth()->id();
        }

        if (! in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        $grievance->update($data);

        return back()->with('success', 'Grievance updated successfully.');
    }

    private function grievancePriority(int $urgentCount, int $overdueCount, int $openCount): array
    {
        if ($urgentCount > 0) {
            return [
                'level' => 'danger',
                'title' => "Handle {$urgentCount} urgent grievance" . ($urgentCount === 1 ? '' : 's'),
                'body' => 'Urgent grievances should be assigned and moved to review before they become escalation risks.',
                'route' => route('admin.grievances.index', ['priority' => 'urgent']),
                'action' => 'Review Urgent',
            ];
        }

        if ($overdueCount > 0) {
            return [
                'level' => 'warning',
                'title' => "Follow up {$overdueCount} overdue grievance" . ($overdueCount === 1 ? '' : 's'),
                'body' => 'Open grievances older than seven days need ownership, response notes, or escalation.',
                'route' => route('admin.grievances.index', ['status' => 'open']),
                'action' => 'Review Overdue',
            ];
        }

        if ($openCount > 0) {
            return [
                'level' => 'info',
                'title' => "Monitor {$openCount} active grievance" . ($openCount === 1 ? '' : 's'),
                'body' => 'Keep active grievances assigned, updated, and resolved with clear notes.',
                'route' => route('admin.grievances.index', ['status' => 'open']),
                'action' => 'Open Queue',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No active grievances',
            'body' => 'Use filters to review resolved and closed grievance history.',
            'route' => route('admin.grievances.index'),
            'action' => 'Review History',
        ];
    }
}
