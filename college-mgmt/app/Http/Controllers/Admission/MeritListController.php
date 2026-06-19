<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\ProgramSeatMatrix;
use App\Services\DepartmentHierarchyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MeritListController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Program $program)
    {
        $batches = Batch::orderBy('name')->get();
        $batchId = request('batch_id');

        $query = $this->scopedMeritQuery($program);
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
        abort_unless($this->hierarchy->canApproveAdmission($request->user()), 403);

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
        $this->applyMeritApplicantVisibility($query, $request->user());

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

        if ($lockedCount = $this->lockedOfferEntryCount($program->id, $batchId)) {
            return back()->with('error', "Cannot regenerate this merit list because {$lockedCount} applicant(s) already have active offer letters. Use an audited correction workflow instead.");
        }

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

        $query = $this->scopedMeritQuery($program)->with(['applicant.user', 'decidedBy']);

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

        $entryQuery = $this->scopedMeritQuery($program)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId));

        $report = [];
        foreach ($categories as $cat) {
            $seatsTotal   = $seatMatrix ? $seatMatrix->getSeatsForCategory($cat) : 0;
            $catEntries   = (clone $entryQuery)->where('category', $cat);
            $appliedQuery = Applicant::where('program_id', $program->id)
                ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
                ->where('category', $cat);
            $this->applyMeritApplicantVisibility($appliedQuery, request()->user());
            $applied = $appliedQuery->count();
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
        abort_unless($this->hierarchy->canApproveAdmission($request->user()), 403);
        $this->guardEntryScope($entry);

        $request->validate([
            'decision' => 'required|in:selected,waitlisted,rejected',
            'notes'    => 'nullable|string',
        ]);

        if ($this->entryHasActiveOffer($entry)) {
            return back()->with('error', 'Merit decisions linked to active offer letters are locked.');
        }

        $entry->loadMissing('applicant');
        if ($this->entryApplicantIsFinal($entry)) {
            return back()->with('error', 'Merit decisions are locked for applicants in a final admission state.');
        }

        if ($entry->decision !== 'pending' && $entry->decision !== $request->decision) {
            return back()->with('error', 'Final merit decisions are locked. Create an audited correction workflow instead of changing selection history.');
        }

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
        abort_unless($this->hierarchy->canApproveAdmission($request->user()), 403);

        $request->validate([
            'accept_top'   => 'required|integer|min:1',
            'waitlist_next' => 'nullable|integer|min:0',
            'batch_id'     => 'nullable|exists:batches,id',
        ]);

        $query = $this->scopedMeritQuery($program)
            ->where('decision', 'pending')
            ->orderBy('rank');

        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        $entries = $query->get();

        $acceptTop    = (int) $request->accept_top;
        $waitlistNext = (int) ($request->waitlist_next ?? 0);

        $decidableEntries = $entries
            ->load(['applicant'])
            ->reject(fn (MeritListEntry $entry) => $this->entryApplicantIsFinal($entry) || $this->entryHasActiveOffer($entry))
            ->values();

        $selectedCount = 0;
        $waitlistedCount = 0;

        foreach ($decidableEntries as $i => $entry) {
            if ($i < $acceptTop) {
                $entry->update([
                    'decision'   => 'selected',
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);
                $selectedCount++;
            } elseif ($i < $acceptTop + $waitlistNext) {
                $entry->update([
                    'decision'   => 'waitlisted',
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);
                $waitlistedCount++;
            }
        }

        $skipped = $entries->count() - $decidableEntries->count();

        return back()->with('success', "Bulk decision applied: {$selectedCount} selected, {$waitlistedCount} waitlisted. Skipped {$skipped} locked applicant(s).");
    }

    private function entryHasActiveOffer(MeritListEntry $entry): bool
    {
        return OfferLetter::where('applicant_id', $entry->applicant_id)
            ->whereIn('status', ['issued', 'accepted'])
            ->exists();
    }

    private function entryApplicantIsFinal(MeritListEntry $entry): bool
    {
        $status = $entry->applicant?->status;

        return in_array($status, ['rejected', 'withdrawn', 'enrolled'], true);
    }

    private function lockedOfferEntryCount(int $programId, ?int $batchId = null): int
    {
        return MeritListEntry::where('program_id', $programId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->whereHas('applicant.offerLetters', fn ($query) => $query->whereIn('status', ['issued', 'accepted']))
            ->count();
    }

    public function exportMeritList(Program $program)
    {
        $batchId = request('batch_id');

        $query = $this->scopedMeritQuery($program)->with(['applicant.user', 'batch']);

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

    public function exportCsv(Program $program)
    {
        $batchId = request('batch_id');

        $entries = $this->scopedMeritQuery($program)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->with(['applicant.user', 'batch', 'decidedBy'])
            ->orderBy('rank')
            ->get();

        $filename = 'merit-list-' . $program->code . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($entries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Rank', 'Application No', 'Name', 'Email', 'Batch',
                'Total Score', 'Academic Score', 'Composite Score', 'Decision',
                'Decided By', 'Decided At', 'Notes']);
            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->rank,
                    $entry->applicant->application_number ?? '',
                    $entry->applicant->user->name ?? '',
                    $entry->applicant->user->email ?? '',
                    $entry->batch->name ?? '',
                    $entry->total_weighted_score,
                    $entry->academic_score ?? '',
                    $entry->composite_score ?? '',
                    $entry->decision,
                    $entry->decidedBy->name ?? '',
                    $entry->decided_at?->format('Y-m-d H:i') ?? '',
                    $entry->notes ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function scopedMeritQuery(Program $program)
    {
        return MeritListEntry::query()
            ->where('program_id', $program->id)
            ->whereHas('applicant', function ($query) {
                $this->applyMeritApplicantVisibility($query, request()->user());
            });
    }

    private function guardEntryScope(MeritListEntry $entry): void
    {
        $entry->loadMissing('applicant');
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $entry->applicant?->assigned_to, true),
            403
        );
    }

    private function applyMeritApplicantVisibility($query, $user): void
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
