<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Mail\OfferLetterMail;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Services\AdmissionSeatCapacityService;
use App\Services\DepartmentHierarchyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OfferLetterController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Program $program)
    {
        $batches = Batch::orderBy('name')->get();
        $batchId = request('batch_id');
        $status = request('status');

        $scopedQuery = $this->scopedOfferQuery($program);
        $query = (clone $scopedQuery)->with(['applicant.user', 'issuedBy', 'batch']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $offerLetters = $query->orderBy('created_at', 'desc')->paginate(50);

        $stats = [
            'total'    => (clone $scopedQuery)->count(),
            'issued'   => (clone $scopedQuery)->where('status', 'issued')->count(),
            'accepted' => (clone $scopedQuery)->where('status', 'accepted')->count(),
            'declined' => (clone $scopedQuery)->where('status', 'declined')->count(),
        ];

        return view('admission.offer-letters.index', compact(
            'program', 'batches', 'batchId', 'status', 'offerLetters', 'stats'
        ));
    }

    public function generate(Request $request, Program $program)
    {
        $request->validate([
            'batch_id'              => 'nullable|exists:batches,id',
            'acceptance_days'       => 'required|integer|min:1|max:180',
            'selected_applicant_ids' => 'nullable|array',
            'selected_applicant_ids.*' => 'exists:merit_list_entries,id',
        ]);

        $batchId = $request->batch_id;
        $acceptanceDays = (int) $request->acceptance_days;
        $selectedIds = $request->input('selected_applicant_ids', []);

        // Get selected merit list entries
        $query = MeritListEntry::where('program_id', $program->id)
            ->where('decision', 'selected')
            ->whereHas('applicant', fn ($q) => $this->applyOfferApplicantVisibility($q, $request->user()))
            ->with(['applicant.user']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if (!empty($selectedIds)) {
            $query->whereIn('id', $selectedIds);
        }

        $entries = $query->get();

        $generated = 0;
        foreach ($entries as $entry) {
            if (!$this->applicantCanReceiveOffer($entry->applicant)) {
                continue;
            }

            $existing = OfferLetter::where('applicant_id', $entry->applicant_id)
                ->where('status', '!=', 'declined')
                ->first();

            if (!$existing) {
                $offer = OfferLetter::create([
                    'applicant_id'        => $entry->applicant_id,
                    'program_id'          => $program->id,
                    'batch_id'            => $entry->batch_id,
                    'status'              => 'issued',
                    'acceptance_deadline' => now()->addDays($acceptanceDays)->toDateString(),
                    'issued_by'           => auth()->id(),
                ]);

                Mail::send(new OfferLetterMail($offer));
                $generated++;
            }
        }

        return back()->with('success', "Generated {$generated} offer letter(s).");
    }

    public function show(OfferLetter $offerLetter)
    {
        $this->guardOfferScope($offerLetter);

        $offerLetter->load(['applicant.user', 'program', 'batch', 'issuedBy']);
        $locked = $this->applicantHasTerminalStatus($offerLetter);

        return view('admission.offer-letters.show', compact('offerLetter', 'locked'));
    }

    public function exportPdf(OfferLetter $offerLetter)
    {
        $this->guardOfferScope($offerLetter);

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return back()->with('error', 'This offer letter is locked because the applicant is already in a final admission state.');
        }

        $offerLetter->load(['applicant.user', 'program', 'batch']);

        $pdf = Pdf::loadView('admission.offer-letters.pdf', ['offerLetter' => $offerLetter]);

        return $pdf->download('offer-letter-' . $offerLetter->offer_number . '.pdf');
    }

    public function accept(Request $request, OfferLetter $offerLetter)
    {
        $this->guardOfferScope($offerLetter);

        if ($offerLetter->status !== 'issued') {
            return back()->with('error', 'This offer letter cannot be accepted.');
        }

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return back()->with('error', 'This applicant is in a final admission state and the offer cannot be changed.');
        }

        if (now()->toDateString() > $offerLetter->acceptance_deadline) {
            return back()->with('error', 'Acceptance deadline has passed.');
        }

        $offerLetter->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        $offerLetter->applicant->update(['status' => 'selected']);

        return back()->with('success', 'Offer accepted successfully!');
    }

    public function decline(Request $request, OfferLetter $offerLetter)
    {
        $this->guardOfferScope($offerLetter);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if ($offerLetter->status !== 'issued') {
            return back()->with('error', 'This offer letter cannot be declined.');
        }

        if ($this->applicantHasTerminalStatus($offerLetter)) {
            return back()->with('error', 'This applicant is in a final admission state and the offer cannot be changed.');
        }

        $offerLetter->update([
            'status'           => 'declined',
            'declined_at'      => now(),
            'declined_reason'  => $request->reason,
        ]);

        $offerLetter->applicant->update(['status' => 'rejected']);

        // Auto-promote first waitlisted applicant
        $this->promoteFromWaitlist($offerLetter->program_id, $offerLetter->batch_id, $offerLetter->applicant_id);

        return back()->with('success', 'Offer declined successfully.');
    }

    protected function promoteFromWaitlist($programId, $batchId, ?int $releasedApplicantId = null)
    {
        $waitlisted = MeritListEntry::where('program_id', $programId)
            ->where('batch_id', $batchId)
            ->where('decision', 'waitlisted')
            ->whereHas('applicant', fn ($q) => $this->applyOfferApplicantVisibility($q, request()->user()))
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

                Mail::send(new OfferLetterMail($offer));
            }
        }
    }

    public function bulkGenerateFromMeritList(Request $request)
    {
        $request->validate([
            'program_id'      => 'required|exists:programs,id',
            'applicant_ids'   => 'required|array|min:1',
            'applicant_ids.*' => 'exists:applicants,id',
        ]);

        $program = \App\Models\Program::findOrFail($request->program_id);
        $batchQuery = \App\Models\Batch::where('program_id', $program->id);
        if (\Illuminate\Support\Facades\Schema::hasColumn('batches', 'is_active')) {
            $batchQuery->where('is_active', true);
        } else {
            $batchQuery->where('status', 'active');
        }
        $batch = $batchQuery->first();

        if (!$batch) {
            return back()->with('error', 'No active batch found for this program.');
        }

        $generated = 0;
        $skipped   = 0;
        $submittedApplicantIds = collect($request->applicant_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $visibleApplicantIds = Applicant::whereIn('id', $submittedApplicantIds)
            ->where('program_id', $program->id)
            ->tap(fn ($query) => $this->applyOfferApplicantVisibility($query, $request->user()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        abort_unless($visibleApplicantIds->count() === $submittedApplicantIds->count(), 403);

        foreach ($submittedApplicantIds as $applicantId) {
            $applicant = \App\Models\Applicant::find($applicantId);
            if (!$applicant || $applicant->program_id != $program->id) { $skipped++; continue; }
            if (!$this->applicantCanReceiveOffer($applicant)) { $skipped++; continue; }
            if (\App\Models\OfferLetter::where('applicant_id', $applicant->id)->exists()) { $skipped++; continue; }

            \App\Models\OfferLetter::create([
                'applicant_id'        => $applicant->id,
                'program_id'          => $program->id,
                'batch_id'            => $batch->id,
                'status'              => 'issued',
                'issued_at'           => now(),
                'issued_by'           => auth()->id(),
                'acceptance_deadline' => now()->addDays(14)->toDateString(),
            ]);

            if (!in_array($applicant->status, ['selected', 'enrolled'])) {
                $applicant->update(['status' => 'selected']);
            }

            $generated++;
        }

        return back()->with('success', "Offer letters generated: {$generated}. Skipped (already exist): {$skipped}.");
    }

    public function bulkGenerate(Request $request, Program $program)
    {
        $request->validate([
            'batch_id'              => 'nullable|exists:batches,id',
            'acceptance_days'       => 'required|integer|min:1|max:180',
        ]);

        $batchId = $request->batch_id;
        $acceptanceDays = (int) $request->acceptance_days;

        $query = MeritListEntry::where('program_id', $program->id)
            ->where('decision', 'selected')
            ->whereHas('applicant', fn ($q) => $this->applyOfferApplicantVisibility($q, $request->user()));

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $entries = $query->get();
        $generated = 0;

        foreach ($entries as $entry) {
            $entry->loadMissing('applicant');
            if (!$this->applicantCanReceiveOffer($entry->applicant)) {
                continue;
            }

            $existing = OfferLetter::where('applicant_id', $entry->applicant_id)
                ->where('status', '!=', 'declined')
                ->first();

            if (!$existing) {
                $offer = OfferLetter::create([
                    'applicant_id'        => $entry->applicant_id,
                    'program_id'          => $program->id,
                    'batch_id'            => $entry->batch_id,
                    'status'              => 'issued',
                    'acceptance_deadline' => now()->addDays($acceptanceDays)->toDateString(),
                    'issued_by'           => auth()->id(),
                ]);

                Mail::send(new OfferLetterMail($offer));
                $generated++;
            }
        }

        return back()->with('success', "Generated {$generated} offer letter(s).");
    }

    private function applicantHasTerminalStatus(OfferLetter $offerLetter): bool
    {
        $status = $offerLetter->applicant()->value('status');

        return in_array($status, ['rejected', 'withdrawn', 'enrolled'], true);
    }

    private function applicantCanReceiveOffer(?Applicant $applicant): bool
    {
        return $applicant && !in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true);
    }

    private function scopedOfferQuery(Program $program)
    {
        return OfferLetter::query()
            ->where('program_id', $program->id)
            ->whereHas('applicant', function ($query) {
                $this->applyOfferApplicantVisibility($query, request()->user());
            });
    }

    private function guardOfferScope(OfferLetter $offerLetter): void
    {
        $offerLetter->loadMissing('applicant');
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $offerLetter->applicant?->assigned_to, false),
            403
        );
    }

    private function applyOfferApplicantVisibility($query, $user): void
    {
        if ($user->hasRole('admin') || $this->hierarchy->canSeeAll($user, 'ADM')) {
            return;
        }

        $visibleUserIds = $this->hierarchy->visibleUserIds($user, 'ADM');

        $query->where(function ($scope) use ($visibleUserIds) {
            $scope->whereIn('assigned_to', $visibleUserIds)
                ->orWhereNull('assigned_to');
        });
    }
}
