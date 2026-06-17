<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\EnrollmentConfirmation;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\SeatMatrix;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $committed = $this->committedSeatUsage($seatMatrix);
        if ((int) $validated['total_seats'] < $committed['total']) {
            throw ValidationException::withMessages([
                'total_seats' => "Total seats cannot be reduced below {$committed['total']} already selected/offered/enrolled applicant(s).",
            ]);
        }

        foreach ($this->seatCategoryColumns() as $category => $column) {
            $used = $committed['categories'][$category] ?? 0;
            if ((int) $validated[$column] < $used) {
                throw ValidationException::withMessages([
                    $column => "Seats for {$category} cannot be reduced below {$used} already selected/offered/enrolled applicant(s).",
                ]);
            }
        }

        $seatMatrix->update($validated);
        return redirect()->route('admission.seat-matrices.index', $seatMatrix->program)
            ->with('success', 'Seat matrix updated.');
    }

    public function destroy(SeatMatrix $seatMatrix)
    {
        $program = $seatMatrix->program;
        if ($this->hasSeatDecisionHistory($seatMatrix)) {
            return redirect()->route('admission.seat-matrices.index', $program)
                ->with('error', 'This seat matrix has selections, offers, waitlist, or enrollment history and cannot be deleted.');
        }

        $seatMatrix->delete();
        return redirect()->route('admission.seat-matrices.index', $program)
            ->with('success', 'Seat matrix deleted.');
    }

    private function hasSeatDecisionHistory(SeatMatrix $seatMatrix): bool
    {
        return MeritListEntry::where('program_id', $seatMatrix->program_id)
            ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
            ->whereIn('decision', ['selected', 'waitlisted'])
            ->exists()
            || OfferLetter::where('program_id', $seatMatrix->program_id)
                ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
                ->exists()
            || EnrollmentConfirmation::where('status', 'completed')
                ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
                ->whereHas('applicant', fn ($query) => $query->where('program_id', $seatMatrix->program_id))
                ->exists();
    }

    private function committedSeatUsage(SeatMatrix $seatMatrix): array
    {
        $applicantIds = collect();

        $applicantIds = $applicantIds->merge(
            MeritListEntry::where('program_id', $seatMatrix->program_id)
                ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
                ->where('decision', 'selected')
                ->pluck('applicant_id')
        );

        $applicantIds = $applicantIds->merge(
            OfferLetter::where('program_id', $seatMatrix->program_id)
                ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
                ->whereIn('status', ['issued', 'accepted'])
                ->pluck('applicant_id')
        );

        $applicantIds = $applicantIds->merge(
            EnrollmentConfirmation::where('status', 'completed')
                ->when($seatMatrix->batch_id, fn ($query) => $query->where('batch_id', $seatMatrix->batch_id))
                ->whereHas('applicant', fn ($query) => $query->where('program_id', $seatMatrix->program_id))
                ->pluck('applicant_id')
        );

        $categories = Applicant::whereIn('id', $applicantIds->unique()->values())
            ->get(['id', 'category'])
            ->map(fn ($applicant) => $this->seatCategoryForApplicant($applicant->category ?? 'general'))
            ->countBy()
            ->all();

        return [
            'total' => array_sum($categories),
            'categories' => $categories,
        ];
    }

    private function seatCategoryForApplicant(string $category): string
    {
        return match ($category) {
            'obc', 'obc_nc', 'obc_ncl' => 'obc',
            'sc' => 'sc',
            'st' => 'st',
            'ews' => 'ews',
            'management_quota' => 'management',
            'nri' => 'nri',
            default => 'general',
        };
    }

    private function seatCategoryColumns(): array
    {
        return [
            'general' => 'general_seats',
            'obc' => 'obc_seats',
            'sc' => 'sc_seats',
            'st' => 'st_seats',
            'ews' => 'ews_seats',
            'management' => 'management_quota',
            'nri' => 'nri_quota',
        ];
    }
}
