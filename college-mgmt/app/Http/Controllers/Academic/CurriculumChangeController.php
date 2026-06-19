<?php
namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\{CurriculumChange, Program, Subject};
use App\Services\AcademicAccessPolicyService;
use Illuminate\Http\Request;

class CurriculumChangeController extends Controller {
    public function __construct(private AcademicAccessPolicyService $policy) {}

    public function index(Request $request) {
        $query = CurriculumChange::with(['program','proposedBy','subject'])->latest();
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('program_id')) $query->where('program_id',$request->program_id);
        $changes = $query->paginate(20)->withQueryString();
        $programs = Program::where('is_active',true)->orderBy('name')->get();
        return view('academic.curriculum-changes.index', compact('changes','programs'));
    }
    public function create() {
        $programs = Program::where('is_active',true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        return view('academic.curriculum-changes.create', compact('programs','subjects'));
    }
    public function store(Request $request) {
        $this->policy->authorizeCurriculum($request->user());

        $v = $request->validate([
            'program_id'=>'required|exists:programs,id',
            'subject_id'=>'nullable|exists:subjects,id',
            'title'=>'required|string|max:255',
            'description'=>'required|string',
            'change_type'=>'required|in:add_subject,remove_subject,modify_credits,modify_syllabus,add_elective',
        ]);

        $program = Program::findOrFail($v['program_id']);
        if (! $program->is_active) {
            return back()->withErrors(['program_id' => 'Curriculum changes can be submitted only for active programs.'])->withInput();
        }

        if (! empty($v['subject_id'])) {
            $subject = Subject::findOrFail($v['subject_id']);
            if (! $subject->is_active || (int) $subject->program_id !== (int) $program->id) {
                return back()->withErrors(['subject_id' => 'Selected subject must be active and belong to the selected program.'])->withInput();
            }
        }

        CurriculumChange::create(array_merge($v,['proposed_by'=>auth()->id(),'status'=>'submitted','submitted_at'=>now()]));
        return redirect()->route('academic.curriculum-changes.index')->with('success','Curriculum change submitted.');
    }
    public function show(CurriculumChange $curriculumChange) {
        $curriculumChange->load(['program','subject','proposedBy','reviewedBy']);
        return view('academic.curriculum-changes.show', compact('curriculumChange'));
    }
    public function approve(Request $request, CurriculumChange $curriculumChange) {
        $this->policy->authorizeAcademicGovernanceReview($request->user());
        abort_unless($curriculumChange->isPending(), 422, 'Only pending curriculum changes can be approved.');

        $request->validate(['remarks'=>'nullable|string|max:500']);
        $curriculumChange->update(['status'=>'approved','reviewed_by'=>auth()->id(),'review_remarks'=>$request->remarks,'reviewed_at'=>now()]);
        return back()->with('success','Curriculum change approved.');
    }
    public function reject(Request $request, CurriculumChange $curriculumChange) {
        $this->policy->authorizeAcademicGovernanceReview($request->user());
        abort_unless($curriculumChange->isPending(), 422, 'Only pending curriculum changes can be rejected.');

        $request->validate(['remarks'=>'required|string|max:500']);
        $curriculumChange->update(['status'=>'rejected','reviewed_by'=>auth()->id(),'review_remarks'=>$request->remarks,'reviewed_at'=>now()]);
        return back()->with('error','Curriculum change rejected.');
    }
}
