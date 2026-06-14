<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\CurriculumChange;
use App\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicDeanCalendarService
{
    public function events(): Collection
    {
        return collect()
            ->concat(AcademicDeanReviewMeeting::latest('scheduled_for')->limit(30)->get()->map(fn ($m) => $this->event('Review', $m->title, $m->scheduled_for, $m->status, route('academics.dean-os.reviews'))))
            ->concat(AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])->orderBy('due_at')->limit(30)->get()->map(fn ($a) => $this->event('Action', $a->title, $a->due_at, $a->status, route('academics.dean-os.reviews'))))
            ->concat(Exam::where('exam_date', '>=', now()->subDays(7))->orderBy('exam_date')->limit(30)->get()->map(fn ($e) => $this->event('Exam', $e->name, $e->exam_date, 'scheduled', route('academics.coe.exam-readiness'))))
            ->concat(CurriculumChange::whereIn('status', ['submitted', 'under_review'])->limit(20)->get()->map(fn ($c) => $this->event('Curriculum', $c->title, $c->submitted_at?->addDays(3), $c->status, route('academic.curriculum-changes.index'))))
            ->concat($this->handoffEvents())
            ->sortBy('date')
            ->values();
    }

    private function handoffEvents(): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('admission_handoff_records')) {
            return collect();
        }

        return DB::table('admission_handoff_records')
            ->whereIn('status', ['blocked', 'ready_for_academics', 'returned_for_correction'])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn ($h) => $this->event('Handoff', 'Applicant #' . $h->applicant_id . ' ' . str_replace('_', ' ', $h->status), $h->updated_at, $h->status, route('academics.dean-os.handoff')));
    }

    private function event(string $type, string $title, mixed $date, string $status, string $route): array
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();
        return [
            'type' => $type,
            'title' => $title,
            'date' => $date,
            'status' => $status,
            'route' => $route,
            'bucket' => $date->isPast() ? 'Overdue/Past' : ($date->isToday() ? 'Today' : ($date->lte(now()->endOfWeek()) ? 'This week' : 'Upcoming')),
        ];
    }
}
