<?php
namespace App\Http\Controllers\Departmental;
use App\Http\Controllers\Controller;
use App\Models\StudentGrievance;
use Illuminate\Http\Request;

class GrievanceManagementController extends Controller {
    public function index(Request $request) {
        $query = StudentGrievance::with(['student.user','program','assignedTo'])->latest();
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('priority')) $query->where('priority',$request->priority);
        $grievances = $query->paginate(20)->withQueryString();
        return view('departmental.grievances.index', compact('grievances'));
    }
    public function show(StudentGrievance $grievance) {
        $grievance->load(['student.user','program','assignedTo','resolvedBy']);
        return view('departmental.grievances.show', compact('grievance'));
    }
    public function resolve(Request $request, StudentGrievance $grievance) {
        $request->validate(['resolution_notes'=>'required|string|max:1000']);

        if (! $grievance->isOpen()) {
            return back()->with('error', 'Only open, under review, or escalated grievances can be resolved by the department.');
        }

        $grievance->update(['status'=>'resolved','resolved_by'=>auth()->id(),'resolution_notes'=>$request->resolution_notes,'resolved_at'=>now()]);
        return back()->with('success','Grievance resolved.');
    }
    public function escalate(StudentGrievance $grievance) {
        if (! in_array($grievance->status, ['open', 'under_review'], true)) {
            return back()->with('error', 'Only open or under review grievances can be escalated.');
        }

        $grievance->update(['status'=>'escalated']);
        return back()->with('success','Grievance escalated to Dean.');
    }
}
