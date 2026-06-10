<?php

namespace App\Services;

use App\Models\{TimetableEntry, TimetableSlot, Batch, Program, Term};
use Barryvdh\DomPDF\Facade\Pdf;

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

        // Get timetable entries for this batch
        $entries = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where('batch_id', $batchId)
            ->where('is_active', true)
            ->with(['subject', 'teacher.user', 'classroom', 'slot'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Build grid
        $grid = [];
        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
            foreach ($slots as $slot) {
                $key = $day . '-' . $slot->id;
                $grid[$day][$slot->id] = $entries[$key] ?? null;
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
    public function generateTeacherPdf(int $termId, int $teacherId): \Barryvdh\DomPDF\PDF
    {
        $term = Term::find($termId);
        $teacher = \App\Models\Teacher::with('user')->find($teacherId);

        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

        // Get entries for this teacher
        $entries = TimetableEntry::where('teacher_id', $teacherId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->with(['subject', 'batch', 'classroom', 'slot', 'program'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Build grid
        $grid = [];
        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
            foreach ($slots as $slot) {
                $key = $day . '-' . $slot->id;
                $grid[$day][$slot->id] = $entries[$key] ?? null;
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
                $entry = $grid[$day][$slot->id] ?? null;

                if ($entry) {
                    $html .= '<td>';
                    $html .= '<div class="subject">' . $entry->subject->name . '</div>';
                    $html .= '<div class="teacher">' . ($entry->teacher?->user->name ?? 'N/A') . '</div>';
                    $html .= '<div class="room">Room: ' . ($entry->classroom->room_number ?? 'N/A') . '</div>';
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
                $entry = $grid[$day][$slot->id] ?? null;

                if ($entry) {
                    $html .= '<td>';
                    $html .= '<div class="subject">' . $entry->subject->name . '</div>';
                    $html .= '<div class="batch">' . $entry->batch->name . '</div>';
                    $html .= '<div class="room">Room: ' . ($entry->classroom->room_number ?? 'N/A') . '</div>';
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
}
