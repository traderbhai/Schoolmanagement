<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Services\AdmissionSeatCapacityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OfferLetterController extends Controller
{
    public function index()
    {
        $applicant = auth()->user()->applicant;

        if (!$applicant) {
            return redirect()->route('applicant.dashboard')->with('error', 'No application found.');
        }

        $offerLetters = OfferLetter::where('applicant_id', $applicant->id)
            ->with(['program', 'batch'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('applicant.offer-letters.index', compact('applicant', 'offerLetters'));
    }

    public function show(OfferLetter $offerLetter)
    {
        if (request()->is('applicant/offer-letters/*/pdf')) {
            return $this->downloadPdf($offerLetter);
        }

        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return redirect()->route('applicant.dashboard')->with('error', 'Unauthorized.');
        }

        $offerLetter->load(['program', 'batch']);
        $locked = $this->applicantHasTerminalStatus($offerLetter);

        return view('applicant.offer-letters.show', compact('offerLetter', 'locked'));
    }

    public function downloadPdf(OfferLetter $offerLetter)
    {
        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return redirect()->route('applicant.dashboard')->with('error', 'Unauthorized.');
        }

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return redirect()->route('applicant.offer-letters.show', $offerLetter)
                ->with('error', 'This offer letter is no longer downloadable because your admission application is already in a final state.');
        }

        $offerLetter->load(['program', 'batch']);

        $pdf = Pdf::loadView('applicant.offer-letters.pdf', ['offerLetter' => $offerLetter]);

        return $pdf->download('offer-letter-' . $offerLetter->offer_number . '.pdf');
    }

    public function accept(Request $request, OfferLetter $offerLetter)
    {
        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return $this->offerResponse($request, 'Unauthorized.', false, 403);
        }

        if ($offerLetter->status !== 'issued') {
            return $this->offerResponse($request, 'This offer cannot be accepted.', false, 400);
        }

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return $this->offerResponse($request, 'This applicant is in a final admission state and the offer cannot be changed.', false, 400);
        }

        if (now()->toDateString() > $offerLetter->acceptance_deadline) {
            return $this->offerResponse($request, 'Acceptance deadline has passed.', false, 400);
        }

        $offerLetter->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        $applicant->update(['status' => 'selected']);

        return $this->offerResponse($request, 'Offer accepted successfully!');
    }

    public function decline(Request $request, OfferLetter $offerLetter)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return $this->offerResponse($request, 'Unauthorized.', false, 403);
        }

        if ($offerLetter->status !== 'issued') {
            return $this->offerResponse($request, 'This offer cannot be declined.', false, 400);
        }

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return $this->offerResponse($request, 'This applicant is in a final admission state and the offer cannot be changed.', false, 400);
        }

        $offerLetter->update([
            'status'          => 'declined',
            'declined_at'     => now(),
            'declined_reason' => $request->reason,
        ]);

        $applicant->update(['status' => 'rejected']);

        // Promote waitlisted applicant
        $this->promoteFromWaitlist($offerLetter->program_id, $offerLetter->batch_id, $offerLetter->applicant_id);

        return $this->offerResponse($request, 'Offer declined successfully.');
    }

    protected function promoteFromWaitlist($programId, $batchId, ?int $releasedApplicantId = null)
    {
        $waitlisted = MeritListEntry::where('program_id', $programId)
            ->where('batch_id', $batchId)
            ->where('decision', 'waitlisted')
            ->with('applicant')
            ->orderBy('rank')
            ->first();

        if ($waitlisted && app(AdmissionSeatCapacityService::class)->canPromoteFromWaitlist($waitlisted, $releasedApplicantId)) {
            $waitlisted->update(['decision' => 'selected', 'decided_by' => auth()->id(), 'decided_at' => now()]);

            $existing = OfferLetter::where('applicant_id', $waitlisted->applicant_id)
                ->where('status', '!=', 'declined')
                ->first();

            if (!$existing) {
                $offer = OfferLetter::create([
                    'applicant_id'        => $waitlisted->applicant_id,
                    'program_id'          => $programId,
                    'batch_id'            => $batchId,
                    'status'              => 'issued',
                    'acceptance_deadline' => now()->addDays(7)->toDateString(),
                    'issued_by'           => auth()->id(),
                ]);

                Mail::send(new \App\Mail\OfferLetterMail($offer));
            }
        }
    }

    private function applicantHasTerminalStatus(OfferLetter $offerLetter): bool
    {
        $status = $offerLetter->applicant()->value('status');

        return in_array($status, ['rejected', 'withdrawn', 'enrolled'], true);
    }

    private function offerResponse(Request $request, string $message, bool $success = true, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(
                $success
                    ? ['success' => true, 'message' => $message]
                    : ['error' => $message],
                $status
            );
        }

        return back()->with($success ? 'success' : 'error', $message);
    }
}
