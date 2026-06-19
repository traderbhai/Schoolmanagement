<?php

namespace App\Services;

use App\Models\{TimetableEntry, Teacher, Term};
use Illuminate\Database\Eloquent\Builder;

class LoadBalancingService
{
    /**
     * Analyze and suggest load rebalancing.
     */
    public function analyzeLoadBalance(int $termId, int $programId): array
    {
        $entries = TimetableEntry::where('term_id', $termId)
            ->where('program_id', $programId)
            ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
            ->with(['teacher.user', 'slot'])
            ->get();

        $teacherLoads = [];
        foreach ($entries as $entry) {
            $tid = $entry->teacher_id;
            if (!isset($teacherLoads[$tid])) {
                $teacherLoads[$tid] = [
                    'teacher_id' => $tid,
                    'teacher_name' => $entry->teacher?->user->name ?? 'Unknown',
                    'hours' => 0,
                    'entries' => [],
                ];
            }
            $hours = $this->getSlotHours($entry->timetable_slot_id);
            $teacherLoads[$tid]['hours'] += $hours;
            $teacherLoads[$tid]['entries'][] = $entry->id;
        }

        usort($teacherLoads, fn($a, $b) => $b['hours'] <=> $a['hours']);

        $stats = [
            'total_teachers' => count($teacherLoads),
            'avg_load' => count($teacherLoads) > 0 ? array_sum(array_column($teacherLoads, 'hours')) / count($teacherLoads) : 0,
            'max_load' => max(array_column($teacherLoads, 'hours')) ?? 0,
            'min_load' => min(array_column($teacherLoads, 'hours')) ?? 0,
            'imbalance' => $this->calculateImbalance($teacherLoads),
        ];

        return [
            'teachers' => $teacherLoads,
            'stats' => $stats,
            'recommendations' => $this->getRecommendations($teacherLoads, $stats),
        ];
    }

    /**
     * Calculate load imbalance factor (0-1, 0=perfect balance).
     */
    private function calculateImbalance(array $loads): float
    {
        if (count($loads) < 2) return 0;

        $loads_only = array_column($loads, 'hours');
        $avg = array_sum($loads_only) / count($loads_only);
        $variance = array_sum(array_map(fn($h) => pow($h - $avg, 2), $loads_only)) / count($loads_only);
        $stddev = sqrt($variance);

        return min(1, $stddev / ($avg + 0.001)); // Normalize to 0-1
    }

    /**
     * Get load balancing recommendations.
     */
    private function getRecommendations(array $loads, array $stats): array
    {
        $recommendations = [];
        $avg = $stats['avg_load'];

        foreach ($loads as $load) {
            if ($load['hours'] > $avg * 1.3) {
                $recommendations[] = [
                    'type' => 'reduce_load',
                    'teacher' => $load['teacher_name'],
                    'current' => round($load['hours'], 1),
                    'target' => round($avg, 1),
                    'excess' => round($load['hours'] - $avg, 1),
                    'action' => "Consider reassigning {$load['teacher_name']} to reduce workload",
                ];
            } elseif ($load['hours'] < $avg * 0.7) {
                $recommendations[] = [
                    'type' => 'increase_load',
                    'teacher' => $load['teacher_name'],
                    'current' => round($load['hours'], 1),
                    'target' => round($avg, 1),
                    'gap' => round($avg - $load['hours'], 1),
                    'action' => "Consider assigning additional subjects to {$load['teacher_name']}",
                ];
            }
        }

        if ($stats['imbalance'] > 0.3) {
            $recommendations[] = [
                'type' => 'overall_imbalance',
                'severity' => 'high',
                'imbalance' => round($stats['imbalance'], 2),
                'action' => 'Overall load imbalance is significant. Consider major reassignments.',
            ];
        }

        return $recommendations;
    }

    private function getSlotHours(int $slotId): float
    {
        $slot = \App\Models\TimetableSlot::find($slotId);
        if (!$slot) return 1;

        $start = \Carbon\Carbon::parse($slot->start_time);
        $end = \Carbon\Carbon::parse($slot->end_time);

        return $start->diffInMinutes($end) / 60;
    }

    private function publishedTimetableScope(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn (Builder $version) => $version->where('status', 'published'));
            });
    }
}
