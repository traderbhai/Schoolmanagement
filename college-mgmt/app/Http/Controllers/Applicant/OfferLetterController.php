<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
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
        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return redirect()->route('applicant.dashboard')->with('error', 'Unauthorized.');
        }

        $offerLetter->load(['program', 'batch']);

        return view('applicant.offer-letters.show', compact('offerLetter'));
    }

    public function downloadPdf(OfferLetter $offerLetter)
    {
        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return redirect()->route('applicant.dashboard')->with('error', 'Unauthorized.');
        }

        $offerLetter->load(['program', 'batch']);

        $pdf = Pdf::loadView('applicant.offer-letters.pdf', ['offerLetter' => $offerLetter]);

        return $pdf->download('offer-letter-' . $offerLetter->offer_number . '.pdf');
    }

    public function accept(Request $request, OfferLetter $offerLetter)
    {
        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($offerLetter->status !== 'issued') {
            return response()->json(['error' => 'This offer cannot be accepted.'], 400);
        }

        if (now()->toDateString() > $offerLetter->acceptance_deadline) {
            return response()->json(['error' => 'Acceptance deadline has passed.'], 400);
        }

        $offerLetter->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        $applicant->update(['status' => 'selected']);

        return response()->json(['success' => true, 'message' => 'Offer accepted successfully!']);
    }

    public function decline(Request $request, OfferLetter $offerLetter)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $applicant = auth()->user()->applicant;

        if (!$applicant || $offerLetter->applicant_id !== $applicant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($offerLetter->status !== 'issued') {
            return response()->json(['error' => 'This offer cannot be declined.'], 400);
        }

        $offerLetter->update([
            'status'          => 'declined',
            'declined_at'     => now(),
            'declined_reason' => $request->reason,
        ]);

        $applicant->update(['status' => 'rejected']);

        // Promote waitlisted applicant
        $this->promoteFromWaitlist($offerLetter->program_id, $offerLetter->batch_id);

        return response()->json(['success' => true, 'message' => 'Offer declined successfully.']);
    }

    protected function promoteFromWaitlist($programId, $batchId)
    {
        $waitlisted = MeritListEntry::where('program_id', $programId)
            ->where('batch_id', $batchId)
            ->where('decision', 'waitlisted')
            ->orderBy('rank')
            ->first();

        if ($waitlisted) {
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
}
