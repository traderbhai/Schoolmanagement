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

class WaitlistController extends Controller
{
    public function index(Program $program)
    {
        $batches  = Batch::orderBy('name')->get();
        $batchId  = request('batch_id');
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        $query = MeritListEntry::where('program_id', $program->id)
            ->where('decision', 'waitlisted')
            ->with(['applicant.user', 'applicant.batch'])
            ->orderBy('rank');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $waitlisted = $query->get();

        // Count selected seats vs total capacity
        $selectedCount = MeritListEntry::where('program_id', $program->id)
            ->where('decision', 'selected')
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->count();

        $seatMatrix = SeatMatrix::where('program_id', $program->id)
            ->when($batchId,
                fn($q) => $q->where('batch_id', $batchId),
                fn($q) => $q->whereNull('batch_id')
            )
            ->first();

        $totalSeats    = $seatMatrix?->total_seats ?? 0;
        $availableSeats = max(0, $totalSeats - $selectedCount);

        return view('admission.waitlist.index', compact(
            'program', 'programs', 'batches', 'batchId',
            'waitlisted', 'selectedCount', 'totalSeats', 'availableSeats'
        ));
    }

    public function promote(Request $request, MeritListEntry $entry)
    {
        if ($entry->decision !== 'waitlisted') {
            return back()->with('error', 'This entry is not on the waitlist.');
        }

        // Check available seat
        $selectedCount = MeritListEntry::where('program_id', $entry->program_id)
            ->where('decision', 'selected')
            ->when($entry->batch_id, fn($q) => $q->where('batch_id', $entry->batch_id))
            ->count();

        $seatMatrix = SeatMatrix::where('program_id', $entry->program_id)
            ->when($entry->batch_id,
                fn($q) => $q->where('batch_id', $entry->batch_id),
                fn($q) => $q->whereNull('batch_id')
            )
            ->first();

        if (! $seatMatrix) {
            return back()->with('error', 'No seat matrix is configured for this program/batch.');
        }

        $totalSeats = (int) $seatMatrix->total_seats;

        if ($selectedCount >= $totalSeats) {
            return back()->with('error', 'No available seats to promote this candidate.');
        }

        $category = $this->seatCategoryForApplicant($entry->applicant?->category ?? 'general');
        $categoryCapacity = $this->seatCapacityForCategory($seatMatrix, $category);
        $categoryCommitted = $this->committedApplicants($entry->program_id, $entry->batch_id)
            ->filter(fn (Applicant $applicant) => $this->seatCategoryForApplicant($applicant->category ?? 'general') === $category)
            ->count();

        if ($categoryCommitted >= $categoryCapacity) {
            return back()->with('error', 'No available seats in this applicant category.');
        }

        $entry->update([
            'decision'    => 'selected',
            'decided_by'  => auth()->id(),
            'decided_at'  => now(),
            'notes'       => 'Promoted from waitlist on ' . now()->format('d M Y'),
        ]);

        // Update applicant status
        $entry->applicant->update(['status' => 'selected']);

        // Send notification
        app(\App\Services\AdmissionNotificationService::class)
            ->notifyApplicantStatusChanged($entry->applicant->fresh(), 'selected');

        return back()->with('success', $entry->applicant->user->name . ' promoted from waitlist to selected.');
    }

    private function committedApplicants(int $programId, ?int $batchId)
    {
        $applicantIds = collect()
            ->merge(MeritListEntry::where('program_id', $programId)
                ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
                ->where('decision', 'selected')
                ->pluck('applicant_id'))
            ->merge(OfferLetter::where('program_id', $programId)
                ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
                ->whereIn('status', ['issued', 'accepted'])
                ->pluck('applicant_id'))
            ->merge(EnrollmentConfirmation::where('status', 'completed')
                ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
                ->whereHas('applicant', fn ($query) => $query->where('program_id', $programId))
                ->pluck('applicant_id'))
            ->unique()
            ->values();

        return Applicant::whereIn('id', $applicantIds)->get();
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

    private function seatCapacityForCategory(SeatMatrix $matrix, string $category): int
    {
        return match ($category) {
            'obc' => (int) $matrix->obc_seats,
            'sc' => (int) $matrix->sc_seats,
            'st' => (int) $matrix->st_seats,
            'ews' => (int) $matrix->ews_seats,
            'management' => (int) $matrix->management_quota,
            'nri' => (int) $matrix->nri_quota,
            default => (int) $matrix->general_seats,
        };
    }
}
