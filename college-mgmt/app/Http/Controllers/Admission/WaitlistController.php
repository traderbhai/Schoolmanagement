<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\MeritListEntry;
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

        $totalSeats = $seatMatrix?->total_seats ?? PHP_INT_MAX;

        if ($selectedCount >= $totalSeats) {
            return back()->with('error', 'No available seats to promote this candidate.');
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
}
