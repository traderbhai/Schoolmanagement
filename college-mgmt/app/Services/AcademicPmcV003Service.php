<?php

namespace App\Services;

use App\Models\AcademicPmcCurriculumPlan;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcFacultyLoadPlan;
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcTimetableControl;
use App\Models\AcademicPmcWorkItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicPmcV003Service
{
    public function command(User $user): array
    {
        return [
            'kpis' => [
                'open_work' => AcademicPmcWorkItem::whereNotIn('status', ['done', 'closed', 'cancelled'])->count(),
                'curriculum_plans' => AcademicPmcCurriculumPlan::whereNotIn('status', ['closed', 'cancelled'])->count(),
                'faculty_overload' => AcademicPmcFacultyLoadPlan::whereIn('load_band', ['overload', 'critical'])->count(),
                'timetable_conflicts' => AcademicPmcTimetableControl::sum('teacher_conflicts') + AcademicPmcTimetableControl::sum('room_conflicts'),
                'student_success_risk' => AcademicPmcStudentSuccessPlan::whereIn('risk_band', ['high', 'critical'])->count(),
            ],
            'priorityItems' => AcademicPmcWorkItem::with(['owner', 'program'])->whereIn('severity', ['critical', 'high'])->orderBy('due_at')->limit(10)->get(),
            'reviews' => AcademicPmcReviewMeeting::with('chair')->latest('scheduled_for')->limit(6)->get(),
            'savedViews' => AcademicPmcSavedView::where('user_id', $user->id)->orWhereNull('user_id')->latest()->limit(8)->get(),
            'reports' => $this->reports(),
        ];
    }

    public function workbench(string $type = 'all'): array
    {
        $query = AcademicPmcWorkItem::with(['owner', 'program', 'subject', 'student', 'teacher'])
            ->when($type !== 'all', fn ($q) => $q->where('work_type', $type));

        return [
            'type' => $type,
            'items' => (clone $query)->latest()->paginate(20),
            'open' => (clone $query)->whereNotIn('status', ['done', 'closed', 'cancelled'])->count(),
            'overdue' => (clone $query)->whereNotIn('status', ['done', 'closed', 'cancelled'])->where('due_at', '<', now())->count(),
            'critical' => (clone $query)->where('severity', 'critical')->count(),
        ];
    }

    public function curriculum(): array
    {
        return [
            'plans' => AcademicPmcCurriculumPlan::with(['program', 'owner'])->latest()->paginate(15),
            'work' => $this->workbench('curriculum'),
            'pending_approval' => AcademicPmcCurriculumPlan::whereIn('approval_status', ['pmc_review', 'dean_review'])->count(),
            'rollout_due' => AcademicPmcCurriculumPlan::where('rollout_due_at', '<=', now()->addDays(14))->count(),
        ];
    }

    public function faculty(): array
    {
        return [
            'loads' => AcademicPmcFacultyLoadPlan::with(['teacher.user', 'program', 'owner'])->latest()->paginate(15),
            'work' => $this->workbench('faculty'),
            'overload' => AcademicPmcFacultyLoadPlan::whereIn('load_band', ['overload', 'critical'])->count(),
            'adjunct_required' => AcademicPmcFacultyLoadPlan::where('adjunct_required', true)->count(),
        ];
    }

    public function timetable(): array
    {
        return [
            'controls' => AcademicPmcTimetableControl::with('program')->latest()->paginate(15),
            'work' => $this->workbench('timetable'),
            'freeze_due' => AcademicPmcTimetableControl::whereNull('published_at')->where('freeze_due_at', '<=', now()->addDays(7))->count(),
            'conflicts' => AcademicPmcTimetableControl::sum('teacher_conflicts') + AcademicPmcTimetableControl::sum('room_conflicts'),
        ];
    }

    public function studentSuccess(): array
    {
        return [
            'plans' => AcademicPmcStudentSuccessPlan::with(['student.user', 'program', 'mentor'])->latest()->paginate(15),
            'work' => $this->workbench('student_success'),
            'high_risk' => AcademicPmcStudentSuccessPlan::whereIn('risk_band', ['high', 'critical'])->count(),
            'parent_escalations' => AcademicPmcStudentSuccessPlan::where('parent_escalation_required', true)->count(),
        ];
    }

    public function reviews(): array
    {
        return [
            'meetings' => AcademicPmcReviewMeeting::with('chair')->latest('scheduled_for')->paginate(15),
            'actions' => AcademicPmcWorkItem::with('owner')->where('work_type', 'review_action')->latest()->paginate(15),
        ];
    }

    public function savedViews(User $user, string $surface): Collection
    {
        return AcademicPmcSavedView::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where('surface', $surface)
            ->latest()
            ->get();
    }

    public function saveView(User $user, array $data): AcademicPmcSavedView
    {
        if (! empty($data['is_default'])) {
            AcademicPmcSavedView::where('user_id', $user->id)->where('surface', $data['surface'])->update(['is_default' => false]);
        }

        return AcademicPmcSavedView::updateOrCreate(
            ['user_id' => $user->id, 'surface' => $data['surface'], 'name' => $data['name']],
            ['filters' => $data['filters'] ?? [], 'is_default' => (bool) ($data['is_default'] ?? false)]
        );
    }

    public function createWorkItem(User $actor, array $data): AcademicPmcWorkItem
    {
        return AcademicPmcWorkItem::create($data + ['assigned_by' => $actor->id, 'owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
    }

    public function updateWorkItem(AcademicPmcWorkItem $item, array $data): AcademicPmcWorkItem
    {
        $item->update($data);
        return $item->fresh();
    }

    public function createReview(User $actor, array $data): AcademicPmcReviewMeeting
    {
        return AcademicPmcReviewMeeting::create($data + ['chair_user_id' => $data['chair_user_id'] ?? $actor->id]);
    }

    public function reports(): Collection
    {
        return collect([
            ['key' => 'workbench', 'label' => 'PMC workbench', 'count' => AcademicPmcWorkItem::count(), 'route' => route('academics.pmc.command')],
            ['key' => 'curriculum', 'label' => 'Curriculum governance', 'count' => AcademicPmcCurriculumPlan::count(), 'route' => route('academics.pmc.curriculum-governance')],
            ['key' => 'faculty', 'label' => 'Faculty workload', 'count' => AcademicPmcFacultyLoadPlan::count(), 'route' => route('academics.pmc.faculty-workload')],
            ['key' => 'timetable', 'label' => 'Timetable control', 'count' => AcademicPmcTimetableControl::count(), 'route' => route('academics.pmc.timetable-control')],
            ['key' => 'student_success', 'label' => 'Student success', 'count' => AcademicPmcStudentSuccessPlan::count(), 'route' => route('academics.pmc.student-success')],
            ['key' => 'reviews', 'label' => 'PMC reviews/actions', 'count' => AcademicPmcReviewMeeting::count(), 'route' => route('academics.pmc.reviews')],
        ]);
    }

    public function export(string $report, User $actor, array $filters = []): StreamedResponse
    {
        $rows = $this->exportRows($report);
        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => $report,
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'Academics PMC OS v0.03'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['title', 'subtitle', 'status', 'priority', 'due']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['title'], $row['subtitle'], $row['status'], $row['priority'] ?? '', $row['due'] ?? '']);
            }
            fclose($out);
        }, 'pmc-' . $report . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportRows(string $report): Collection
    {
        return match ($report) {
            'curriculum' => AcademicPmcCurriculumPlan::with('program')->latest()->limit(500)->get()->map(fn ($row) => ['title' => $row->title, 'subtitle' => $row->program?->code ?? 'All programs', 'status' => $row->approval_status, 'priority' => '', 'due' => $row->rollout_due_at?->toDateString()]),
            'faculty' => AcademicPmcFacultyLoadPlan::with('teacher.user')->latest()->limit(500)->get()->map(fn ($row) => ['title' => $row->teacher?->user?->name ?? 'Faculty load', 'subtitle' => $row->allocated_hours . '/' . $row->planned_hours . ' hours', 'status' => $row->load_band, 'priority' => $row->adjunct_required ? 'adjunct required' : '', 'due' => '']),
            'timetable' => AcademicPmcTimetableControl::with('program')->latest()->limit(500)->get()->map(fn ($row) => ['title' => $row->title, 'subtitle' => $row->program?->code ?? 'Program', 'status' => $row->status, 'priority' => $row->teacher_conflicts + $row->room_conflicts . ' conflicts', 'due' => $row->freeze_due_at?->toDateString()]),
            'student_success' => AcademicPmcStudentSuccessPlan::with('student.user')->latest()->limit(500)->get()->map(fn ($row) => ['title' => $row->student?->user?->name ?? 'Student success plan', 'subtitle' => $row->risk_type, 'status' => $row->risk_band, 'priority' => $row->parent_escalation_required ? 'parent escalation' : '', 'due' => $row->next_review_at?->toDateString()]),
            default => AcademicPmcWorkItem::latest()->limit(500)->get()->map(fn ($row) => ['title' => $row->title, 'subtitle' => $row->description ?? $row->work_type, 'status' => $row->status, 'priority' => $row->priority, 'due' => $row->due_at?->toDateString()]),
        };
    }
}
