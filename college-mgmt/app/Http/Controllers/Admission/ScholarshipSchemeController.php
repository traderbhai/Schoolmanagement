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

        if ($this->hasActiveScholarshipRecords($scholarshipScheme) && $this->changesEligibilityContract($scholarshipScheme, $validated)) {
            return back()
                ->withErrors(['scholarship_scheme' => 'Scholarship schemes with active applications or awards cannot have eligibility, program, proof, type, or active status changed. Create a new scheme version or close existing applications first.'])
                ->withInput();
        }

        if ($validated['available_seats'] !== null && $validated['available_seats'] < $scholarshipScheme->awardsCount()) {
            return back()
                ->withErrors(['available_seats' => 'Available seats cannot be reduced below existing awarded, approved, or disbursed scholarships.'])
                ->withInput();
        }

        $highestAwardedAmount = max(
            (float) $scholarshipScheme->applicantScholarships()->whereIn('status', ['awarded', 'disbursed'])->max('awarded_amount'),
            (float) $scholarshipScheme->studentScholarshipApplications()->whereIn('status', ['approved', 'disbursed'])->max('disbursed_amount')
        );

        if ($highestAwardedAmount > 0 && (float) $validated['max_amount'] < $highestAwardedAmount) {
            return back()
                ->withErrors(['max_amount' => 'Maximum amount cannot be reduced below existing approved, awarded, or disbursed scholarship amounts.'])
                ->withInput();
        }

        $scholarshipScheme->update($validated);

        return redirect()->route('admission.scholarship-schemes.index')
            ->with('success', 'Scholarship scheme updated.');
    }

    public function toggle(ScholarshipScheme $scholarshipScheme)
    {
        if ($this->hasActiveScholarshipRecords($scholarshipScheme)) {
            return back()->with('error', 'Scholarship schemes with active applications or awards cannot be activated or deactivated directly. Create a new scheme version or close existing applications first.');
        }

        $scholarshipScheme->update(['is_active' => !$scholarshipScheme->is_active]);
        $label = $scholarshipScheme->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Scheme {$label}.");
    }

    private function hasActiveScholarshipRecords(ScholarshipScheme $scheme): bool
    {
        return $scheme->applicantScholarships()
                ->whereIn('status', ['awarded', 'disbursed'])
                ->exists()
            || $scheme->studentScholarshipApplications()
                ->whereIn('status', ['pending', 'shortlisted', 'approved', 'disbursed'])
                ->exists();
    }

    private function changesEligibilityContract(ScholarshipScheme $scheme, array $validated): bool
    {
        foreach (['program_id', 'type', 'min_cgpa', 'max_family_income', 'requires_document', 'is_active'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($this->normaliseContractValue($scheme->{$field}) !== $this->normaliseContractValue($validated[$field])) {
                return true;
            }
        }

        return false;
    }

    private function normaliseContractValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }

        return (string) $value;
    }
}
