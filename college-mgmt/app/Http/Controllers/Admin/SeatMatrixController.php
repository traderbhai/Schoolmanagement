<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\Program;
use App\Models\ProgramSeatMatrix;
use Illuminate\Http\Request;

class SeatMatrixController extends Controller
{
    public function index(Program $program)
    {
        $matrices = ProgramSeatMatrix::where('program_id', $program->id)
            ->with('batch')
            ->get();

        return view('admin.seat-matrix.index', compact('program', 'matrices'));
    }

    public function create(Program $program)
    {
        $batches  = Batch::where('program_id', $program->id)->orderBy('name')->get();
        $defaults = ProgramSeatMatrix::defaults();

        return view('admin.seat-matrix.create', compact('program', 'batches', 'defaults'));
    }

    public function store(Request $request, Program $program)
    {
        $request->validate([
            'batch_id'               => 'nullable|exists:batches,id',
            'general_seats'          => 'required|integer|min:0',
            'obc_seats'              => 'required|integer|min:0',
            'obc_nc_seats'           => 'required|integer|min:0',
            'sc_seats'               => 'required|integer|min:0',
            'st_seats'               => 'required|integer|min:0',
            'ews_seats'              => 'required|integer|min:0',
            'pwd_seats'              => 'required|integer|min:0',
            'nri_seats'              => 'required|integer|min:0',
            'management_quota_seats' => 'required|integer|min:0',
            'state_quota_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->only([
            'batch_id','general_seats','obc_seats','obc_nc_seats','sc_seats','st_seats',
            'ews_seats','pwd_seats','nri_seats','management_quota_seats','state_quota_percentage',
        ]);

        $data['program_id'] = $program->id;
        $data['total_seats'] = array_sum(array_map(fn($k) => (int)($data[$k] ?? 0), [
            'general_seats','obc_seats','obc_nc_seats','sc_seats','st_seats',
            'ews_seats','nri_seats','management_quota_seats',
        ]));
        $data['is_active'] = true;
        $data['batch_id']  = $data['batch_id'] ?: null;

        ProgramSeatMatrix::create($data);

        return redirect()->route('admin.seat-matrix.index', $program)
            ->with('success', 'Seat matrix created successfully.');
    }

    public function edit(ProgramSeatMatrix $matrix)
    {
        $program = $matrix->program;
        $batches = Batch::where('program_id', $program->id)->orderBy('name')->get();

        return view('admin.seat-matrix.edit', compact('matrix', 'program', 'batches'));
    }

    public function update(Request $request, ProgramSeatMatrix $matrix)
    {
        $request->validate([
            'general_seats'          => 'required|integer|min:0',
            'obc_seats'              => 'required|integer|min:0',
            'obc_nc_seats'           => 'required|integer|min:0',
            'sc_seats'               => 'required|integer|min:0',
            'st_seats'               => 'required|integer|min:0',
            'ews_seats'              => 'required|integer|min:0',
            'pwd_seats'              => 'required|integer|min:0',
            'nri_seats'              => 'required|integer|min:0',
            'management_quota_seats' => 'required|integer|min:0',
            'state_quota_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->only([
            'general_seats','obc_seats','obc_nc_seats','sc_seats','st_seats',
            'ews_seats','pwd_seats','nri_seats','management_quota_seats','state_quota_percentage',
        ]);

        $data['total_seats'] = array_sum(array_map(fn($k) => (int)($data[$k] ?? 0), [
            'general_seats','obc_seats','obc_nc_seats','sc_seats','st_seats',
            'ews_seats','nri_seats','management_quota_seats',
        ]));

        $matrix->update($data);

        return redirect()->route('admin.seat-matrix.index', $matrix->program)
            ->with('success', 'Seat matrix updated successfully.');
    }

    public function destroy(ProgramSeatMatrix $matrix)
    {
        $program = $matrix->program;

        // Check if any selections have been made for this matrix's program+batch
        $hasSelections = MeritListEntry::where('program_id', $matrix->program_id)
            ->when($matrix->batch_id, fn($q) => $q->where('batch_id', $matrix->batch_id))
            ->where('decision', 'selected')
            ->exists();

        if ($hasSelections) {
            return back()->with('error', 'Cannot delete seat matrix — selections have already been made.');
        }

        $matrix->delete();

        return redirect()->route('admin.seat-matrix.index', $program)
            ->with('success', 'Seat matrix deleted.');
    }
}
