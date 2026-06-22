<?php

namespace App\Services;

use App\Models\{AcademicPmcTimetableGenerationItem, TimetableEntry, TimetableSlot, Batch, Program, Term};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetablePdfService
{
    /**
     * Generate PDF for a batch timetable.
     */
    public function generateBatchPdf(int $programId, int $termId, int $batchId): \Barryvdh\DomPDF\PDF
    {
        $program = Program::find($programId);
        $term = Term::find($termId);
        $batch = Batch::find($batchId);

        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

        $canonicalItems = AcademicPmcTimetableGenerationItem::with(['subject', 'courseGroup.subject', 'courseGroup.batch', 'teacher.user', 'classroom', 'slot', 'batch', 'program'])
            ->where(function (Builder $query) use ($programId) {
                $query->where('program_id', $programId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('program_id', $programId));
            })
            ->where(function (Builder $query) use ($termId) {
                $query->where('term_id', $termId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
            })
            ->where(function (Builder $query) use ($batchId) {
                $query->where('batch_id', $batchId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('batch_id', $batchId));
            })
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->get();

        $entries = $canonicalItems->isNotEmpty()
            ? $canonicalItems->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->displayEntryFromPmcItem($item))
            : TimetableEntry::where('program_id', $programId)
                ->where('term_id', $termId)
                ->where('batch_id', $batchId)
                ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
                ->with(['subject', 'teacher.user', 'classroom', 'slot', 'batch', 'program'])
                ->get()
                ->map(fn (TimetableEntry $entry) => $this->displayEntryFromLegacyEntry($entry));

        $entriesBySlot = $entries->groupBy(fn ($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Build grid
        $grid = [];
        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
            foreach ($slots as $slot) {
                $key = $day . '-' . $slot->id;
                $grid[$day][$slot->id] = $entriesBySlot->get($key, collect());
            }
        }

        $html = $this->buildHtml($program, $term, $batch, $grid, $slots, $days);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return $pdf;
    }

    /**
     * Generate PDF for a teacher's timetable.
     */
    public function generateTeacherPdf(int $termId, int $teacherId, ?array $programIds = null): \Barryvdh\DomPDF\PDF
    {
        $term = Term::find($termId);
        $teacher = \App\Models\Teacher::with('user')->find($teacherId);

        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

        $canonicalItems = AcademicPmcTimetableGenerationItem::with(['subject', 'courseGroup.subject', 'courseGroup.batch', 'teacher.user', 'classroom', 'slot', 'batch', 'program'])
            ->where('teacher_id', $teacherId)
            ->where(function (Builder $query) use ($termId) {
                $query->where('term_id', $termId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
            })
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->when($programIds !== null, fn($query) => $query->where(function (Builder $scope) use ($programIds) {
                $scope->whereIn('program_id', $programIds)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->whereIn('program_id', $programIds));
            }))
            ->get();

        $entries = $canonicalItems->isNotEmpty()
            ? $canonicalItems->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->displayEntryFromPmcItem($item))
            : TimetableEntry::where('teacher_id', $teacherId)
                ->where('term_id', $termId)
                ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
                ->when($programIds !== null, fn($query) => $query->whereIn('program_id', $programIds))
                ->with(['subject', 'batch', 'classroom', 'slot', 'program', 'teacher.user'])
                ->get()
                ->map(fn (TimetableEntry $entry) => $this->displayEntryFromLegacyEntry($entry));

        $entriesBySlot = $entries->groupBy(fn ($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Build grid
        $grid = [];
        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
            foreach ($slots as $slot) {
                $key = $day . '-' . $slot->id;
                $grid[$day][$slot->id] = $entriesBySlot->get($key, collect());
            }
        }

        $html = $this->buildTeacherHtml($teacher, $term, $grid, $slots, $days);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return $pdf;
    }

    /**
     * Build HTML for batch timetable.
     */
    private function buildHtml($program, $term, $batch, $grid, $slots, $days): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Timetable - ' . $batch->name . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10mm; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 3px 0; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .time-slot { background-color: #f9f9f9; font-weight: bold; width: 60px; }
        .empty { background-color: #fafafa; color: #ccc; }
        .subject { font-weight: bold; }
        .room { font-size: 10px; color: #666; }
        .teacher { font-size: 10px; color: #333; }
        .footer { text-align: right; font-size: 10px; margin-top: 15px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $program->name . ' - ' . $batch->name . '</h1>
        <p>Term: ' . $term->name . ' | Generated: ' . now()->format('d-m-Y H:i') . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Time Slot</th>';

        foreach ($days as $dayNum => $dayName) {
            $html .= '<th>' . $dayName . '</th>';
        }

        $html .= '
            </tr>
        </thead>
        <tbody>';

        foreach ($slots as $slot) {
            $html .= '<tr><td class="time-slot">' . $slot->name . '</td>';

            foreach (range(1, 6) as $day) {
                $entries = collect($grid[$day][$slot->id] ?? [])->filter();

                if ($entries->isNotEmpty()) {
                    $html .= '<td>';
                    foreach ($entries as $entry) {
                        $html .= '<div class="subject">' . e($entry->subject_name) . '</div>';
                        if ($entry->group_name) {
                            $html .= '<div class="teacher">' . e($entry->group_name) . '</div>';
                        }
                        $html .= '<div class="teacher">' . e($entry->teacher_name ?? 'N/A') . '</div>';
                        $html .= '<div class="room">Room: ' . e($entry->room_label ?? 'N/A') . '</div>';
                    }
                    $html .= '</td>';
                } else {
                    $html .= '<td class="empty">—</td>';
                }
            }

            $html .= '</tr>';
        }

        $html .= '
        </tbody>
    </table>
    <div class="footer">
        <p>This is a computer-generated timetable. For official purposes, verify with the academic department.</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Build HTML for teacher timetable.
     */
    private function buildTeacherHtml($teacher, $term, $grid, $slots, $days): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Timetable - ' . $teacher->user->name . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10mm; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 3px 0; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .time-slot { background-color: #f9f9f9; font-weight: bold; width: 60px; }
        .empty { background-color: #fafafa; color: #ccc; }
        .subject { font-weight: bold; }
        .room { font-size: 10px; color: #666; }
        .batch { font-size: 10px; color: #333; }
        .footer { text-align: right; font-size: 10px; margin-top: 15px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $teacher->user->name . ' - ' . ($teacher->designation ?? 'Teacher') . '</h1>
        <p>Term: ' . $term->name . ' | Generated: ' . now()->format('d-m-Y H:i') . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Time Slot</th>';

        foreach ($days as $dayNum => $dayName) {
            $html .= '<th>' . $dayName . '</th>';
        }

        $html .= '
            </tr>
        </thead>
        <tbody>';

        foreach ($slots as $slot) {
            $html .= '<tr><td class="time-slot">' . $slot->name . '</td>';

            foreach (range(1, 6) as $day) {
                $entries = collect($grid[$day][$slot->id] ?? [])->filter();

                if ($entries->isNotEmpty()) {
                    $html .= '<td>';
                    foreach ($entries as $entry) {
                        $html .= '<div class="subject">' . e($entry->subject_name) . '</div>';
                        $html .= '<div class="batch">' . e($entry->group_name ?: $entry->batch_name ?: 'Batch pending') . '</div>';
                        $html .= '<div class="room">Room: ' . e($entry->room_label ?? 'N/A') . '</div>';
                    }
                    $html .= '</td>';
                } else {
                    $html .= '<td class="empty">—</td>';
                }
            }

            $html .= '</tr>';
        }

        $html .= '
        </tbody>
    </table>
    <div class="footer">
        <p>This is a computer-generated timetable. For official purposes, verify with the academic department.</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function displayEntryFromPmcItem(AcademicPmcTimetableGenerationItem $item): object
    {
        return (object) [
            'subject_name' => $item->subject?->name ?? $item->courseGroup?->subject?->name ?? 'Subject not assigned',
            'teacher_name' => $item->teacher?->user?->name,
            'room_label' => $item->classroom?->room_number ?? $item->classroom?->name,
            'batch_name' => $item->batch?->name ?? $item->courseGroup?->batch?->name,
            'group_name' => $item->courseGroup?->name,
            'program_name' => $item->program?->name,
            'day_of_week' => $item->day_of_week,
            'timetable_slot_id' => $item->timetable_slot_id,
        ];
    }

    private function displayEntryFromLegacyEntry(TimetableEntry $entry): object
    {
        return (object) [
            'subject_name' => $entry->subject?->name ?? 'Subject not assigned',
            'teacher_name' => $entry->teacher?->user?->name,
            'room_label' => $entry->classroom?->room_number ?? $entry->classroom?->name,
            'batch_name' => $entry->batch?->name,
            'group_name' => null,
            'program_name' => $entry->program?->name,
            'day_of_week' => $entry->day_of_week,
            'timetable_slot_id' => $entry->timetable_slot_id,
        ];
    }

    private function publishedTimetableScope(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $versionQuery): void {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('timetable_versions')
                            ->whereColumn('timetable_versions.id', 'timetable_entries.timetable_version_id')
                            ->where('timetable_versions.status', 'published');
                    });
            });
    }
}
