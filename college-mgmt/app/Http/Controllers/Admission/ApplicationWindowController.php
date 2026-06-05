<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\ApplicationWindow;
use App\Models\Program;
use Illuminate\Http\Request;

class ApplicationWindowController extends Controller
{
    public function index(Program $program)
    {
        $windows = ApplicationWindow::where('program_id', $program->id)
            ->with(['batch'])
            ->orderBy('opens_at', 'desc')
            ->paginate(20);

        $stats = [
            'total'         => ApplicationWindow::where('program_id', $program->id)->count(),
            'active'        => ApplicationWindow::where('program_id', $program->id)->where('is_active', true)->count(),
            'open'          => ApplicationWindow::where('program_id', $program->id)->where('is_active', true)
                ->whereDate('opens_at', '<=', now())->whereDate('closes_at', '>=', now())->count(),
        ];

        return view('admission.application-windows.index', compact('program', 'windows', 'stats'));
    }

    public function create(Program $program)
    {
        $batches = $program->batches()->get();
        return view('admission.application-windows.create', compact('program', 'batches'));
    }

    public function store(Request $request, Program $program)
    {
        $request->validate([
            'batch_id'          => 'nullable|exists:batches,id',
            'opens_at'          => 'required|date_format:Y-m-d H:i',
            'closes_at'         => 'required|date_format:Y-m-d H:i|after:opens_at',
            'capacity_limit'    => 'nullable|integer|min:1',
            'description'       => 'nullable|string|max:500',
        ]);

        ApplicationWindow::create([
            'program_id'     => $program->id,
            'batch_id'       => $request->batch_id,
            'opens_at'       => $request->opens_at,
            'closes_at'      => $request->closes_at,
            'capacity_limit' => $request->capacity_limit,
            'description'    => $request->description,
            'is_active'      => true,
        ]);

        return redirect()->route('admission.application-windows.index', $program)
            ->with('success', 'Application window created successfully.');
    }

    public function edit(ApplicationWindow $window)
    {
        $program = $window->program;
        $batches = $program->batches()->get();
        return view('admission.application-windows.edit', compact('window', 'program', 'batches'));
    }

    public function update(Request $request, ApplicationWindow $window)
    {
        $request->validate([
            'batch_id'          => 'nullable|exists:batches,id',
            'opens_at'          => 'required|date_format:Y-m-d H:i',
            'closes_at'         => 'required|date_format:Y-m-d H:i|after:opens_at',
            'capacity_limit'    => 'nullable|integer|min:1',
            'is_active'         => 'boolean',
            'description'       => 'nullable|string|max:500',
        ]);

        $window->update($request->only(['batch_id', 'opens_at', 'closes_at', 'capacity_limit', 'is_active', 'description']));

        return redirect()->route('admission.application-windows.index', $window->program)
            ->with('success', 'Application window updated successfully.');
    }

    public function destroy(ApplicationWindow $window)
    {
        $program = $window->program;
        $window->delete();

        return redirect()->route('admission.application-windows.index', $program)
            ->with('success', 'Application window deleted.');
    }

    public function toggleActive(ApplicationWindow $window)
    {
        $window->update(['is_active' => !$window->is_active]);

        return back()->with('success', 'Application window status updated.');
    }
}
