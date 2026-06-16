<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ScholarshipScheme;
use Illuminate\Http\Request;

class ScholarshipSchemeController extends Controller
{
    public function index()
    {
        $schemes = ScholarshipScheme::with('program')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(20);
        return view('admission.scholarship-schemes.index', compact('schemes'));
    }

    public function create()
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('admission.scholarship-schemes.create', compact('programs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id'      => 'nullable|exists:programs,id',
            'name'            => 'required|string|max:255',
            'scheme_code'     => 'required|string|max:50|unique:scholarship_schemes,scheme_code',
            'type'            => 'required|in:merit,need_based,government,aicte,institution',
            'criteria'        => 'nullable|string|max:2000',
            'min_cgpa'        => 'nullable|numeric|min:0|max:10',
            'max_family_income' => 'nullable|numeric|min:0',
            'requires_document' => 'boolean',
            'max_amount'      => 'required|numeric|min:0',
            'available_seats' => 'nullable|integer|min:1',
            'is_active'       => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['requires_document'] = $request->boolean('requires_document');

        ScholarshipScheme::create($validated);

        return redirect()->route('admission.scholarship-schemes.index')
            ->with('success', 'Scholarship scheme created successfully.');
    }

    public function edit(ScholarshipScheme $scholarshipScheme)
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('admission.scholarship-schemes.edit', compact('scholarshipScheme', 'programs'));
    }

    public function update(Request $request, ScholarshipScheme $scholarshipScheme)
    {
        $validated = $request->validate([
            'program_id'      => 'nullable|exists:programs,id',
            'name'            => 'required|string|max:255',
            'scheme_code'     => 'required|string|max:50|unique:scholarship_schemes,scheme_code,' . $scholarshipScheme->id,
            'type'            => 'required|in:merit,need_based,government,aicte,institution',
            'criteria'        => 'nullable|string|max:2000',
            'min_cgpa'        => 'nullable|numeric|min:0|max:10',
            'max_family_income' => 'nullable|numeric|min:0',
            'requires_document' => 'boolean',
            'max_amount'      => 'required|numeric|min:0',
            'available_seats' => 'nullable|integer|min:1',
            'is_active'       => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['requires_document'] = $request->boolean('requires_document');

        $scholarshipScheme->update($validated);

        return redirect()->route('admission.scholarship-schemes.index')
            ->with('success', 'Scholarship scheme updated.');
    }

    public function toggle(ScholarshipScheme $scholarshipScheme)
    {
        $scholarshipScheme->update(['is_active' => !$scholarshipScheme->is_active]);
        $label = $scholarshipScheme->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Scheme {$label}.");
    }
}
