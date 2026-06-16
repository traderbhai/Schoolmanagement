<?php
namespace App\Services;

use App\Models\BookIssue;
use App\Models\LibraryMembership;
use Illuminate\Database\Eloquent\Builder;

class LibraryFineService
{
    public function calculateFine(BookIssue $issue): float
    {
        if ($issue->returned_at || $issue->due_date >= now()->toDateString()) return 0;
        $daysOverdue = $issue->due_date->startOfDay()->diffInDays(now()->startOfDay());
        $userId = $issue->student?->user_id ?? $issue->teacher?->user_id;
        $membership = $userId ? LibraryMembership::where('user_id', $userId)->where('is_active', true)->first() : null;
        $ratePerDay = $membership ? (float)$membership->fine_per_day : 1.00;
        return round($daysOverdue * $ratePerDay, 2);
    }

    public function applyOverdueFines(): int
    {
        $updated = 0;
        BookIssue::whereIn('status', ['issued','overdue'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['student.user', 'teacher.user'])
            ->chunkById(200, function($issues) use (&$updated) {
                foreach ($issues as $issue) {
                    $fine = $this->calculateFine($issue);
                    $issue->update(['fine_amount' => $fine, 'status' => 'overdue']);
                    $updated++;
                }
            });
        return $updated;
    }

    public function checkNocEligibility(int $userId): array
    {
        $activeIssues = $this->issuesForUser($userId)
            ->whereIn('status', ['issued','overdue'])
            ->exists();
        if ($activeIssues) {
            return ['eligible' => false, 'reason' => 'Has unreturned books'];
        }
        $unpaidFines = $this->issuesForUser($userId)
            ->where('fine_paid', false)->where('fine_amount', '>', 0)->exists();
        if ($unpaidFines) {
            return ['eligible' => false, 'reason' => 'Has unpaid library fines'];
        }
        return ['eligible' => true, 'reason' => null];
    }

    private function issuesForUser(int $userId): Builder
    {
        return BookIssue::where(function (Builder $query) use ($userId) {
            $query->whereHas('student', fn (Builder $student) => $student->where('user_id', $userId))
                ->orWhereHas('teacher', fn (Builder $teacher) => $teacher->where('user_id', $userId));
        });
    }
}
