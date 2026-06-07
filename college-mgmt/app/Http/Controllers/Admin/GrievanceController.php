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

        $openCount   = StudentGrievance::where('status', 'open')->count();
        $urgentCount = StudentGrievance::where('priority', 'urgent')->whereIn('status', ['open', 'in_progress'])->count();

        return view('admin.grievances.index', compact('grievances', 'openCount', 'urgentCount'));
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
            'status'      => 'required|in:open,in_progress,resolved,closed',
            'resolution'  => 'nullable|string|max:3000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($data['status'] === 'resolved' && !$grievance->resolved_at) {
            $data['resolved_at'] = now();
        }

        $grievance->update($data);

        return back()->with('success', 'Grievance updated successfully.');
    }
}
