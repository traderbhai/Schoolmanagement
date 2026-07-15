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
        abort_unless($this->portalAccess->canUseTeacherPortal(auth()->user()), 403);

        $teacher = auth()->user()->teacher;
        $currentTerm = Term::latest('start_date')->first();
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
