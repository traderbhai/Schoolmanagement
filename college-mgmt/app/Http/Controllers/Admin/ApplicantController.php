<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Program;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with(['user', 'program', 'batch'])
            ->latest();

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applicants = $query->paginate(20)->withQueryString();
        $programs   = Program::where('is_active', true)->get();
        $batches    = Batch::where('is_active', true)->get();
        $statuses   = ['draft', 'submitted', 'under_review', 'shortlisted', 'selected', 'rejected', 'withdrawn'];

        return view('admin.applicants.index', compact('applicants', 'programs', 'batches', 'statuses'));
    }

    public function show(Applicant $applicant)
    {
        $applicant->load(['user', 'program', 'batch', 'documents.requiredDocument', 'reviewer']);
        return view('admin.applicants.show', compact('applicant'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|max:20',
            'program_id' => 'required|exists:programs,id',
            'batch_id'   => 'nullable|exists:batches,id',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);

            Role::firstOrCreate(['name' => 'applicant']);
            $user->assignRole('applicant');

            Applicant::create([
                'user_id'    => $user->id,
                'program_id' => $validated['program_id'],
                'batch_id'   => $validated['batch_id'] ?? null,
                'status'     => 'draft',
                'personal_data' => $validated['phone'] ? ['phone' => $validated['phone']] : null,
            ]);
        });

        return redirect()->route('admin.applicants.index')
            ->with('success', 'Applicant created successfully. Default password: password123');
    }

    public function saveNotes(Request $request, Applicant $applicant)
    {
        $request->validate(['notes' => 'nullable|string|max:5000']);
        $applicant->update(['notes' => $request->notes]);
        return back()->with('success', 'Notes saved.');
    }
}
