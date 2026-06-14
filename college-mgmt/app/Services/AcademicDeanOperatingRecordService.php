<?php

namespace App\Services;

use App\Models\AcademicDeanOperatingRecord;

class AcademicDeanOperatingRecordService
{
    public function dashboard(string $type): array
    {
        $query = AcademicDeanOperatingRecord::with(['owner', 'program', 'student', 'teacher'])->where('record_type', $type);

        return [
            'records' => (clone $query)->latest()->paginate(20),
            'open' => (clone $query)->whereNotIn('status', ['done', 'closed', 'resolved', 'cancelled'])->count(),
            'critical' => (clone $query)->where('severity', 'critical')->count(),
            'overdue' => (clone $query)->whereNotIn('status', ['done', 'closed', 'resolved', 'cancelled'])->where('due_at', '<', now())->count(),
            'avg_score' => (int) ((clone $query)->avg('score') ?? 0),
        ];
    }
}
