<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\Program;
use App\Models\ProgramSeatMatrix;
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
            'batch_id'              => 'nullable|exists:batches,id',
            'academic_weight'       => 'nullable|numeric|min:0|max:100',
            'entrance_exam_weight'  => 'nullable|numeric|min:0|max:100',
        ]);

        $batchId             = $request->batch_id;
        $entranceExamWeight  = (float) ($request->entrance_exam_weight ?? 30) / 100;
        $academicWeight      = (float) ($request->academic_weight ?? 20) / 100;
        $selectionWeight     = max(0, 1 - $entranceExamWeight - $academicWeight);

        // Get applicants
        $query = Applicant::where('program_id', $program->id)
            ->whereIn('status', ['shortlisted', 'under_review', 'selected']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $applicants = $query->with(['scores.step'])->get();

        // Get seat matrix
        $seatMatrix = ProgramSeatMatrix::where('program_id', $program->id)
            ->where(function ($q) use ($batchId) {
                $q->whereNull('batch_id');
                if ($batchId) {
                    $q->orWhere('batch_id', $batchId);
                }
            })
            ->orderByDesc('batch_id') // prefer batch-specific
            ->first();

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

            // Entrance exam score — already on the applicant column
            $entranceScore = $applicant->entrance_exam_score;

            // If no entrance exam score, redistribute weight to selection+academic
            $effectiveSelectionWeight = $selectionWeight;
            $effectiveAcademicWeight  = $academicWeight;
            $effectiveEntranceWeight  = $entranceExamWeight;

            if ($entranceScore === null) {
                $total = $selectionWeight + $academicWeight;
                if ($total > 0) {
                    $effectiveSelectionWeight = $selectionWeight / $total;
                    $effectiveAcademicWeight  = $academicWeight / $total;
                }
                $effectiveEntranceWeight = 0;
            }

            $compositeScore = ($selectionWeightedTotal * $effectiveSelectionWeight)
                + (($academicScore ?? 0) * $effectiveAcademicWeight)
                + (($entranceScore ?? 0) * $effectiveEntranceWeight / 100); // normalize percentile

            // Store weights used for auditability
            $stepScores['_weights'] = [
                'selection_weight'    => round($effectiveSelectionWeight * 100, 1),
                'academic_weight'     => round($effectiveAcademicWeight * 100, 1),
                'entrance_exam_weight'=> round($effectiveEntranceWeight * 100, 1),
            ];

            $ranked[] = [
                'applicant'            => $applicant,
                'applicant_id'         => $applicant->id,
                'batch_id'             => $applicant->batch_id ?? $batchId,
                'category'             => $applicant->category ?? 'general',
                'total_weighted_score' => round($selectionWeightedTotal, 2),
                'step_scores'          => $stepScores,
                'academic_score'       => $academicScore,
                'composite_score'      => round($compositeScore, 2),
            ];
        }

        // Sort descending by composite_score (open merit list)
        usort($ranked, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        // Category-wise ranking
        $categoryGroups = [];
        foreach ($ranked as $item) {
            $cat = $item['category'];
            $categoryGroups[$cat][] = $item;
        }
        // Each category already sorted by composite_score (descending) because $ranked is sorted
        $categoryRanks = [];
        foreach ($categoryGroups as $cat => $items) {
            foreach ($items as $i => $item) {
                $categoryRanks[$item['applicant_id']] = $i + 1;
            }
        }

        // Determine quota type per entry based on seat matrix
        foreach ($ranked as $rank => $data) {
            $cat = $data['category'];
            $applicantId = $data['applicant_id'];
            $catRank = $categoryRanks[$applicantId];

            // Determine quota type
            $quotaType = 'open';
            $isSupernumerary = false;

            if ($seatMatrix) {
                if (in_array($cat, ['nri'])) {
                    $quotaType = 'nri';
                    $isSupernumerary = true;
                } elseif ($cat === 'management_quota') {
                    $quotaType = 'management';
                } elseif ($cat === 'pwd') {
                    $quotaType = 'pwd';
                    $isSupernumerary = true;
                } elseif ($cat !== 'general' && $catRank <= $seatMatrix->getSeatsForCategory($cat)) {
                    $quotaType = 'category';
                } else {
                    $quotaType = 'open';
                }
            }

            MeritListEntry::updateOrCreate(
                [
                    'program_id'   => $program->id,
                    'applicant_id' => $data['applicant_id'],
                ],
                [
                    'batch_id'             => $data['batch_id'],
                    'rank'                 => $rank + 1,
                    'total_weighted_score' => $data['total_weighted_score'],
                    'step_scores'          => $data['step_scores'],
                    'academic_score'       => $data['academic_score'],
                    'composite_score'      => $data['composite_score'],
                    'merit_list_version'   => $newVersion,
                    'decision'             => 'pending',
                    'decided_by'           => null,
                    'decided_at'           => null,
                    'category'             => $cat,
                    'category_rank'        => $catRank,
                    'quota_type'           => $quotaType,
                    'is_supernumerary'     => $isSupernumerary,
                ]
            );
        }

        return redirect()->route('admission.merit-list.show', $program)
            ->with('success', 'Merit list generated successfully (version ' . $newVersion . ').');
    }

    public function show(Program $program)
    {
        $batches   = Batch::orderBy('name')->get();
        $batchId   = request('batch_id');
        $decision  = request('decision');
        $quotaFilter = request('quota_type');

        $query = MeritListEntry::where('program_id', $program->id)
            ->with(['applicant.user', 'decidedBy']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }
        if ($decision) {
            $query->where('decision', $decision);
        }
        if ($quotaFilter) {
            $query->where('quota_type', $quotaFilter);
        }

        $entries = $query->orderBy('rank')->paginate(50);

        // Load step info for headers
        $stepIds = collect($entries->items())
            ->flatMap(fn($e) => array_keys(array_filter($e->step_scores ?? [], fn($k) => $k !== '_weights', ARRAY_FILTER_USE_KEY)))
            ->unique()->values();

        $steps = \App\Models\SelectionProcessStep::whereIn('id', $stepIds)->get()->keyBy('id');

        // Seat matrix summary
        $seatMatrix = ProgramSeatMatrix::where('program_id', $program->id)
            ->where(function ($q) use ($batchId) {
                $q->whereNull('batch_id');
                if ($batchId) $q->orWhere('batch_id', $batchId);
            })
            ->orderByDesc('batch_id')
            ->first();

        return view('admission.merit-list.show', compact(
            'program', 'batches', 'batchId', 'decision', 'quotaFilter', 'entries', 'steps', 'seatMatrix'
        ));
    }

    public function categoryWiseReport(Program $program)
    {
        $batchId = request('batch_id');

        $seatMatrix = ProgramSeatMatrix::where('program_id', $program->id)
            ->where(function ($q) use ($batchId) {
                $q->whereNull('batch_id');
                if ($batchId) $q->orWhere('batch_id', $batchId);
            })
            ->orderByDesc('batch_id')
            ->first();

        $categories = ['general','obc','obc_nc','sc','st','ews','pwd','nri','management_quota'];

        $entryQuery = MeritListEntry::where('program_id', $program->id)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId));

        $report = [];
        foreach ($categories as $cat) {
            $seatsTotal   = $seatMatrix ? $seatMatrix->getSeatsForCategory($cat) : 0;
            $catEntries   = (clone $entryQuery)->where('category', $cat);
            $applied      = Applicant::where('program_id', $program->id)
                ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
                ->where('category', $cat)->count();
            $scored       = (clone $catEntries)->whereNotNull('composite_score')->count();
            $selected     = (clone $catEntries)->where('decision', 'selected')->count();
            $vacant       = max(0, $seatsTotal - $selected);

            $report[$cat] = [
                'label'      => $this->categoryLabel($cat),
                'seats'      => $seatsTotal,
                'applied'    => $applied,
                'scored'     => $scored,
                'selected'   => $selected,
                'vacant'     => $vacant,
                'fill_pct'   => $seatsTotal > 0 ? round(($selected / $seatsTotal) * 100) : 0,
            ];
        }

        $batches = Batch::where('program_id', $program->id)->orderBy('name')->get();

        return view('admission.merit-list.category-report', compact(
            'program', 'batches', 'batchId', 'seatMatrix', 'report'
        ));
    }

    private function categoryLabel(string $cat): string
    {
        return match($cat) {
            'general'          => 'General',
            'obc'              => 'OBC',
            'obc_nc'           => 'OBC (Non-Creamy Layer)',
            'sc'               => 'SC',
            'st'               => 'ST',
            'ews'              => 'EWS',
            'pwd'              => 'PWD (Supernumerary)',
            'nri'              => 'NRI (Supernumerary)',
            'management_quota' => 'Management Quota',
            default            => ucfirst($cat),
        };
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

        $stepIds = $entries->flatMap(fn($e) => array_keys(array_filter($e->step_scores ?? [], fn($k) => $k !== '_weights', ARRAY_FILTER_USE_KEY)))->unique()->values();
        $steps   = \App\Models\SelectionProcessStep::whereIn('id', $stepIds)->get()->keyBy('id');

        $batch = $batchId ? Batch::find($batchId) : null;

        $pdf = Pdf::loadView('admission.merit-list.pdf', compact('program', 'entries', 'steps', 'batch'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('merit-list-' . $program->code . '.pdf');
    }
}
