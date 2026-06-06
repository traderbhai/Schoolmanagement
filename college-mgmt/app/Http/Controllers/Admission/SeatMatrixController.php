<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Program;
use App\Models\SeatMatrix;
use Illuminate\Http\Request;

class SeatMatrixController extends Controller
{
    public function index(Program $program)
    {
        $matrices = SeatMatrix::where('program_id', $program->id)
            ->with('batch')
            ->orderByDesc('id')
            ->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('admission.seat-matrices.index', compact('program', 'programs', 'matrices'));
    }

    public function create(Program $program)
    {
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        return view('admission.seat-matrices.create', compact('program', 'batches'));
    }

    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'batch_id'         => 'nullable|exists:batches,id',
            'total_seats'      => 'required|integer|min:1',
            'general_seats'    => 'required|integer|min:0',
            'obc_seats'        => 'required|integer|min:0',
            'sc_seats'         => 'required|integer|min:0',
            'st_seats'         => 'required|integer|min:0',
            'ews_seats'        => 'required|integer|min:0',
            'management_quota' => 'required|integer|min:0',
            'nri_quota'        => 'required|integer|min:0',
            'defence_quota'    => 'required|integer|min:0',
        ]);

        $exists = SeatMatrix::where('program_id', $program->id)
            ->where('batch_id', $validated['batch_id'] ?? null)
            ->exists();
        if ($exists) {
            return back()->withErrors(['batch_id' => 'A seat matrix already exists for this program/batch combination.'])->withInput();
        }

        SeatMatrix::create(array_merge($validated, ['program_id' => $program->id]));

        return redirect()->route('admission.seat-matrices.index', $program)
            ->with('success', 'Seat matrix configured successfully.');
    }

    public function edit(SeatMatrix $seatMatrix)
    {
        $program = $seatMatrix->program;
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        return view('admission.seat-matrices.edit', compact('seatMatrix', 'program', 'batches'));
    }

    public function update(Request $request, SeatMatrix $seatMatrix)
    {
        $validated = $request->validate([
            'total_seats'      => 'required|integer|min:1',
            'general_seats'    => 'required|integer|min:0',
            'obc_seats'        => 'required|integer|min:0',
            'sc_seats'         => 'required|integer|min:0',
            'st_seats'         => 'required|integer|min:0',
            'ews_seats'        => 'required|integer|min:0',
            'management_quota' => 'required|integer|min:0',
            'nri_quota'        => 'required|integer|min:0',
            'defence_quota'    => 'required|integer|min:0',
        ]);
        $seatMatrix->update($validated);
        return redirect()->route('admission.seat-matrices.index', $seatMatrix->program)
            ->with('success', 'Seat matrix updated.');
    }
}
