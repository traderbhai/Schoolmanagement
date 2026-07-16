<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcSubstitutionRecommendation, AcademicPmcTimetableChangeRequest, AcademicPmcTimetableGenerationItem, Term, TimetableSlot, TimetableEntry, TimetableSubstitution};
use App\Services\PortalAccessPolicyService;
use Illuminate\Database\Eloquent\Builder;

class TimetableController extends Controller
{
    public function __construct(private PortalAccessPolicyService $portalAccess) {}

    public function index()
    {
        $this->portalAccess->authorizeTeacherPortal(auth()->user());

        $teacher = auth()->user()->teacher;
        $currentTerm = $teacher ? $this->currentTermForTeacher((int) $teacher->id) : $this->fallbackCurrentTerm();
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $days  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];

        if (! $teacher) {
            $entries = collect();
            $todaySubstitutions = collect();
            $profileMissing = true;

            return view('teacher.timetable.index', compact(
                'slots', 'days', 'entries', 'currentTerm', 'todaySubstitutions', 'profileMissing'
            ));
        }

        $canonicalEntries = AcademicPmcTimetableGenerationItem::query()
            ->where('teacher_id', $teacher->id)
            ->when($currentTerm?->id, fn (Builder $query) => $query->where(function (Builder $scope) use ($currentTerm) {
                $scope->where('term_id', $currentTerm->id)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $currentTerm->id));
            }))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn($version) => $version->where('status', 'published'))
            ->with(['subject', 'courseGroup.subject', 'classroom', 'slot', 'batch', 'timetableVersion'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        $entries = $canonicalEntries->isNotEmpty()
            ? $canonicalEntries
            : TimetableEntry::where('teacher_id', $teacher->id)
            ->where('term_id', $currentTerm?->id)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            })
            ->with(['subject', 'classroom', 'slot', 'batch'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        $todaySubstitutions = $this->todayTimetableAlerts((int) $teacher->id);
        $profileMissing = false;

        return view('teacher.timetable.index', compact(
            'slots', 'days', 'entries', 'currentTerm', 'todaySubstitutions', 'profileMissing'
        ));
    }

    private function currentTermForTeacher(int $teacherId): ?Term
    {
        $termIds = AcademicPmcTimetableGenerationItem::where('teacher_id', $teacherId)
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->pluck('term_id')
            ->merge(TimetableEntry::where('teacher_id', $teacherId)
                ->where('is_active', true)
                ->where('status', 'published')
                ->pluck('term_id'))
            ->filter()
            ->unique()
            ->values();

        if ($termIds->isEmpty()) {
            return $this->fallbackCurrentTerm();
        }

        $today = today()->toDateString();

        return Term::whereIn('id', $termIds)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first()
            ?: Term::whereIn('id', $termIds)->where('is_current', true)->orderByDesc('start_date')->first()
            ?: Term::whereIn('id', $termIds)->orderByDesc('start_date')->first()
            ?: $this->fallbackCurrentTerm();
    }

    private function fallbackCurrentTerm(): ?Term
    {
        $today = today()->toDateString();

        return Term::where('is_current', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first()
            ?: Term::where('is_current', true)->orderByDesc('start_date')->first()
            ?: Term::latest('start_date')->first();
    }

    private function todayTimetableAlerts(int $teacherId)
    {
        $today = today()->toDateString();

        $legacy = TimetableSubstitution::whereHas('entry', fn ($query) => $query->where('teacher_id', $teacherId))
            ->where('date', $today)
            ->with('entry.subject')
            ->get()
            ->map(fn (TimetableSubstitution $substitution): array => [
                'subject' => $substitution->entry?->subject?->name ?? 'Subject not linked',
                'group' => null,
                'action' => $substitution->action,
                'reason' => $substitution->reason,
                'source' => 'Legacy timetable',
            ]);

        $canonicalSubstitutions = AcademicPmcSubstitutionRecommendation::with([
                'pmcGenerationItem.subject',
                'pmcGenerationItem.courseGroup.subject',
                'originalTeacher.user',
                'substituteTeacher.user',
            ])
            ->whereDate('substitution_date', $today)
            ->where(function ($query) use ($teacherId) {
                $query->where('original_teacher_id', $teacherId)
                    ->orWhere('substitute_teacher_id', $teacherId);
            })
            ->get()
            ->map(function (AcademicPmcSubstitutionRecommendation $recommendation) use ($teacherId): array {
                $item = $recommendation->pmcGenerationItem;
                $isCovering = (int) $recommendation->substitute_teacher_id === $teacherId;
                $otherTeacher = $isCovering
                    ? $recommendation->originalTeacher?->user?->name
                    : $recommendation->substituteTeacher?->user?->name;

                return [
                    'subject' => $item?->subject?->name ?? $item?->courseGroup?->subject?->name ?? 'Subject not linked',
                    'group' => $item?->courseGroup?->name,
                    'action' => $isCovering
                        ? 'Covering for ' . ($otherTeacher ?: 'original faculty')
                        : ($otherTeacher ? 'Substituted by ' . $otherTeacher : 'Substitution uncovered'),
                    'reason' => collect($recommendation->reasons ?? [])->filter()->implode('; '),
                    'source' => 'Official PMC session',
                ];
            });

        $canonicalChanges = AcademicPmcTimetableChangeRequest::with([
                'pmcGenerationItem.subject',
                'pmcGenerationItem.courseGroup.subject',
            ])
            ->whereIn('change_type', ['cancellation', 'reschedule'])
            ->whereHas('pmcGenerationItem', fn ($query) => $query->where('teacher_id', $teacherId))
            ->get()
            ->filter(fn (AcademicPmcTimetableChangeRequest $change): bool => ($change->impact_summary['requested_date'] ?? null) === $today)
            ->map(function (AcademicPmcTimetableChangeRequest $change): array {
                $item = $change->pmcGenerationItem;

                return [
                    'subject' => $item?->subject?->name ?? $item?->courseGroup?->subject?->name ?? 'Subject not linked',
                    'group' => $item?->courseGroup?->name,
                    'action' => ucfirst($change->change_type) . ' requested',
                    'reason' => $change->reason,
                    'source' => 'Official PMC change request',
                ];
            });

        return collect($legacy->all())
            ->merge($canonicalSubstitutions->all())
            ->merge($canonicalChanges->all())
            ->values();
    }
}
