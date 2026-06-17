<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use App\Models\Student;
use App\Services\AcademicAccessPolicyService;
use Illuminate\Http\Request;

class ScholarshipController extends Controller
{
    public function __construct(private AcademicAccessPolicyService $policy) {}

    public function index()
    {
        $scholarships = Scholarship::with('student')->paginate(15);
        return view('academic.scholarships.index', compact('scholarships'));
    }

    public function create()
    {
        $students = Student::select('id', 'name')->get();
        return view('academic.scholarships.create', compact('students'));
    }

    public function store(Request $request)
    {
        $this->policy->authorizeScholarships($request->user());

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'type' => 'required|in:merit,need_based,category,other',
            'status' => 'required|in:active,inactive,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        $validated = $this->normalizeScholarshipPayload($validated);
        $this->assertHasDiscount($validated);
        $this->assertNoDuplicateActiveScholarship($validated);

        Scholarship::create($validated);

        return redirect()->route('academic.scholarships.index')
            ->with('success', 'Scholarship created successfully');
    }

    public function show(Scholarship $scholarship)
    {
        $scholarship->load('student');
        return view('academic.scholarships.show', compact('scholarship'));
    }

    public function edit(Scholarship $scholarship)
    {
        $students = Student::select('id', 'name')->get();
        return view('academic.scholarships.edit', compact('scholarship', 'students'));
    }

    public function update(Request $request, Scholarship $scholarship)
    {
        $this->policy->authorizeScholarships($request->user());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'type' => 'required|in:merit,need_based,category,other',
            'status' => 'required|in:active,inactive,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        $validated = $this->normalizeScholarshipPayload($validated + ['student_id' => $scholarship->student_id]);
        $this->assertHasDiscount($validated);
        $this->assertNoDuplicateActiveScholarship($validated, $scholarship);
        unset($validated['student_id']);

        $scholarship->update($validated);

        return redirect()->route('academic.scholarships.show', $scholarship)
            ->with('success', 'Scholarship updated successfully');
    }

    public function destroy(Request $request, Scholarship $scholarship)
    {
        $this->policy->authorizeScholarships($request->user());

        $scholarship->update(['status' => 'inactive']);

        return redirect()->route('academic.scholarships.index')
            ->with('success', 'Scholarship archived successfully');
    }

    private function normalizeScholarshipPayload(array $validated): array
    {
        $validated['percentage'] = (float) ($validated['percentage'] ?? 0);
        $validated['fixed_amount'] = $validated['fixed_amount'] ?? null;

        return $validated;
    }

    private function assertHasDiscount(array $validated): void
    {
        abort_if(
            (float) ($validated['percentage'] ?? 0) <= 0 && (float) ($validated['fixed_amount'] ?? 0) <= 0,
            422,
            'Scholarship must define either a positive percentage or a fixed amount.'
        );
    }

    private function assertNoDuplicateActiveScholarship(array $validated, ?Scholarship $current = null): void
    {
        if (($validated['status'] ?? null) !== 'active') {
            return;
        }

        $newFrom = $validated['valid_from'] ?? null;
        $newTo = $validated['valid_to'] ?? null;

        $duplicate = Scholarship::query()
            ->where('student_id', $validated['student_id'])
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->where('status', 'active')
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->when($newTo, fn ($query) => $query->where(fn ($date) => $date->whereNull('valid_from')->orWhereDate('valid_from', '<=', $newTo)))
            ->when($newFrom, fn ($query) => $query->where(fn ($date) => $date->whereNull('valid_to')->orWhereDate('valid_to', '>=', $newFrom)))
            ->exists();

        abort_if($duplicate, 422, 'An overlapping active scholarship with the same name and type already exists for this student.');
    }
}
