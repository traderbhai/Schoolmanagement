<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\ApplicationWindow;
use App\Models\Batch;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $this->validateBatchBelongsToProgram($program, $request->batch_id);

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
        $data = $request->validate([
            'batch_id'          => 'nullable|exists:batches,id',
            'opens_at'          => 'required|date_format:Y-m-d H:i',
            'closes_at'         => 'required|date_format:Y-m-d H:i|after:opens_at',
            'capacity_limit'    => 'nullable|integer|min:1',
            'is_active'         => 'boolean',
            'description'       => 'nullable|string|max:500',
        ]);

        $this->validateBatchBelongsToProgram($window->program, $data['batch_id'] ?? null);

        if ($this->hasIntakeHistory($window)) {
            $message = $this->lockedWindowChangeMessage($window, $data);
            if ($message) {
                return back()
                    ->withErrors(['application_window' => $message])
                    ->withInput();
            }
        }

        $window->update($data);

        return redirect()->route('admission.application-windows.index', $window->program)
            ->with('success', 'Application window updated successfully.');
    }

    public function destroy(ApplicationWindow $window)
    {
        $program = $window->program;
        if ($this->hasIntakeHistory($window)) {
            return back()->with('error', 'Application windows with applicant intake history cannot be deleted. Deactivate the window or create a new intake window instead.');
        }

        $window->delete();

        return redirect()->route('admission.application-windows.index', $program)
            ->with('success', 'Application window deleted.');
    }

    public function toggleActive(ApplicationWindow $window)
    {
        $window->update(['is_active' => !$window->is_active]);

        return back()->with('success', 'Application window status updated.');
    }

    private function hasIntakeHistory(ApplicationWindow $window): bool
    {
        return (int) $window->current_applications > 0;
    }

    private function lockedWindowChangeMessage(ApplicationWindow $window, array $data): ?string
    {
        if ((int) ($data['batch_id'] ?? 0) !== (int) ($window->batch_id ?? 0)
            || $window->opens_at->format('Y-m-d H:i') !== (string) $data['opens_at']
            || $window->closes_at->format('Y-m-d H:i') !== (string) $data['closes_at']) {
            return 'Application windows with applicant intake history cannot change batch or open/close dates. Create a new intake window for changed admissions timelines.';
        }

        $capacity = $data['capacity_limit'] ?? null;
        if ($capacity !== null && (int) $capacity < (int) $window->current_applications) {
            return 'Capacity cannot be lower than the number of applications already received for this window.';
        }

        return null;
    }

    private function validateBatchBelongsToProgram(Program $program, mixed $batchId): void
    {
        if ($batchId === null || $batchId === '') {
            return;
        }

        $belongs = Batch::whereKey((int) $batchId)
            ->where('program_id', $program->id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'batch_id' => 'Select a batch that belongs to the selected program.',
            ]);
        }
    }
}
