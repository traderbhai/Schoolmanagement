<?php

namespace App\Services;

use App\Models\AcademicDeanOperatingRecord;

class AcademicDeanOperatingRecordService
{
    public function dashboard(string $type, array $filters = []): array
    {
        $baseQuery = AcademicDeanOperatingRecord::with(['owner', 'program', 'student', 'teacher'])
            ->where('record_type', $type);

        $query = (clone $baseQuery)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($q, $severity) => $q->where('severity', $severity))
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->when($filters['owner_user_id'] ?? null, fn ($q, $ownerId) => $q->where('owner_user_id', $ownerId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($scope) use ($search) {
                    $scope->where('title', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%")
                        ->orWhere('source_key', 'like', "%{$search}%");
                });
            });

        $sortMap = [
            'title' => 'title',
            'status' => 'status',
            'severity' => 'severity',
            'score' => 'score',
            'due_at' => 'due_at',
            'created_at' => 'created_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'due_at'] ?? 'due_at';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return [
            'records' => $query
                ->orderBy($sort, $direction)
                ->orderBy('id')
                ->paginate(min(100, max(10, (int) ($filters['per_page'] ?? 20))))
                ->withQueryString(),
            'open' => (clone $baseQuery)->whereNotIn('status', ['done', 'closed', 'resolved', 'cancelled'])->count(),
            'critical' => (clone $baseQuery)->where('severity', 'critical')->count(),
            'overdue' => (clone $baseQuery)->whereNotIn('status', ['done', 'closed', 'resolved', 'cancelled'])->where('due_at', '<', now())->count(),
            'avg_score' => (int) ((clone $baseQuery)->avg('score') ?? 0),
            'filters' => $filters,
        ];
    }
}
