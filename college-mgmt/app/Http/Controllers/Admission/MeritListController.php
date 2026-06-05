<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\Program;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MeritListController extends Controller
{
    public function index(Program $program)
    {
        $batches = Batch::orderBy('name')->get();
        $batchId = request('batch_id');

        $query = MeritListEntry::where('program_id', $program->id);
        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $entries = $query->with(['applicant.user', 'decidedBy'])->orderBy('rank')->get();

        $stats = [
            'total'      => $entries->count(),
            'selected'   => $entries->where('decision', 'selected')->count(),
            'waitlisted' => $entries->where('decision', 'waitlisted')->count(),
            'rejected'   => $entries->where('decision', 'rejected')->count(),
            'pending'    => $entries->where('decision', 'pending')->count(),
        ];

        $latestVersion = $entries->max('merit_list_version');

        return view('admission.merit-list.index', compact(
            'program', 'batches', 'batchId', 'entries', 'stats', 'latestVersion'
        ));
    }

    public function generate(Request $request, Program $program)
    {
        $request->validate([
            'batch_id'          => 'nullable|exists:batches,id',
            'academic_weight'   => 'nullable|numeric|min:0|max:100',
        ]);

        $batchId        = $request->batch_id;
        $academicWeight = (float) ($request->academic_weight ?? 20) / 100;
        $selectionWeight = 1 - $academicWeight;

        // Get applicants
        $query = Applicant::where('program_id', $program->id)
            ->whereIn('status', ['shortlisted', 'under_review', 'selected']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $applicants = $query->with(['scores.step'])->get();

        // Determine current version
        $currentVersion = MeritListEntry::where('program_id', $program->id)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->max('merit_list_version') ?? 0;
        $newVersion = $currentVersion + 1;

        $ranked = [];

        foreach ($applicants as $applicant) {
            $stepScores = [];
            $selectionWeightedTotal = 0;

            foreach ($applicant->scores as $score) {
                $step = $score->step;
                if (!$step) continue;

                $stepScores[$step->id] = [
                    'total'      => $score->total_score,
                    'percentage' => $score->percentage,
                    'weightage'  => $step->weightage,
                ];
                $selectionWeightedTotal += $score->percentage * ($step->weightage / 100);
            }

            // Academic score
            $academicData = $applicant->academic_data ?? [];
            $academicScore = null;
            if (!empty($academicData['graduation_percentage'])) {
                $academicScore = min((float) $academicData['graduation_percentage'], 100);
            } elseif (!empty($academicData['cgpa'])) {
                $academicScore = min((float) $academicData['cgpa'] * 10, 100);
            }

            $compositeScore = ($selectionWeightedTotal * $selectionWeight)
                + (($academicScore ?? 0) * $academicWeight);

            $ranked[] = [
                'applicant_id'         => $applicant->id,
                'batch_id'             => $applicant->batch_id ?? $batchId,
                'total_weighted_score' => round($selectionWeightedTotal, 2),
                'step_scores'          => $stepScores,
                'academic_score'       => $academicScore,
                'composite_score'      => round($compositeScore, 2),
            ];
        }

        // Sort descending by composite_score
        usort($ranked, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        foreach ($ranked as $rank => $data) {
            MeritListEntry::updateOrCreate(
                [
                    'program_id'   => $program->id,
                    'applicant_id' => $data['applicant_id'],
                ],
                array_merge($data, [
                    'program_id'          => $program->id,
                    'rank'                => $rank + 1,
                    'merit_list_version'  => $newVersion,
                    'decision'            => 'pending',
                    'decided_by'          => null,
                    'decided_at'          => null,
                ])
            );
        }

        return redirect()->route('admission.merit-list.show', $program)
            ->with('success', 'Merit list generated successfully (version ' . $newVersion . ').');
    }

    public function show(Program $program)
    {
        $batches  = Batch::orderBy('name')->get();
        $batchId  = request('batch_id');
        $decision = request('decision');

        $query = MeritListEntry::where('program_id', $program->id)
            ->with(['applicant.user', 'decidedBy']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }
        if ($decision) {
            $query->where('decision', $decision);
        }

        $entries = $query->orderBy('rank')->paginate(50);

        // Load step info for headers
        $stepIds = collect($entries->items())
            ->flatMap(fn($e) => array_keys($e->step_scores ?? []))
            ->unique()->values();

        $steps = \App\Models\SelectionProcessStep::whereIn('id', $stepIds)->get()->keyBy('id');

        return view('admission.merit-list.show', compact(
            'program', 'batches', 'batchId', 'decision', 'entries', 'steps'
        ));
    }

    public function updateDecision(Request $request, MeritListEntry $entry)
    {
        $request->validate([
            'decision' => 'required|in:selected,waitlisted,rejected',
            'notes'    => 'nullable|string',
        ]);

        $entry->update([
            'decision'   => $request->decision,
            'notes'      => $request->notes,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        if ($request->decision === 'selected' && $request->boolean('update_applicant_status')) {
            $entry->applicant->update(['status' => 'selected']);
        }

        return back()->with('success', 'Decision updated successfully.');
    }

    public function bulkDecide(Request $request, Program $program)
    {
        $request->validate([
            'accept_top'   => 'required|integer|min:1',
            'waitlist_next' => 'nullable|integer|min:0',
            'batch_id'     => 'nullable|exists:batches,id',
        ]);

        $query = MeritListEntry::where('program_id', $program->id)
            ->where('decision', 'pending')
            ->orderBy('rank');

        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        $entries = $query->get();

        $acceptTop    = (int) $request->accept_top;
        $waitlistNext = (int) ($request->waitlist_next ?? 0);

        foreach ($entries as $i => $entry) {
            if ($i < $acceptTop) {
                $entry->update([
                    'decision'   => 'selected',
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);
            } elseif ($i < $acceptTop + $waitlistNext) {
                $entry->update([
                    'decision'   => 'waitlisted',
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);
            }
        }

        return back()->with('success', "Bulk decision applied: {$acceptTop} selected, {$waitlistNext} waitlisted.");
    }

    public function exportMeritList(Program $program)
    {
        $batchId = request('batch_id');

        $query = MeritListEntry::where('program_id', $program->id)
            ->with(['applicant.user', 'batch']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $entries = $query->orderBy('rank')->get();

        $stepIds = $entries->flatMap(fn($e) => array_keys($e->step_scores ?? []))->unique()->values();
        $steps   = \App\Models\SelectionProcessStep::whereIn('id', $stepIds)->get()->keyBy('id');

        $batch = $batchId ? Batch::find($batchId) : null;

        $pdf = Pdf::loadView('admission.merit-list.pdf', compact('program', 'entries', 'steps', 'batch'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('merit-list-' . $program->code . '.pdf');
    }
}
