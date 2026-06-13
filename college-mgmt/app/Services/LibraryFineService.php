<?php
namespace App\Services;

use App\Models\BookIssue;
use App\Models\LibraryMembership;

class LibraryFineService
{
    public function calculateFine(BookIssue $issue): float
    {
        if ($issue->returned_at || $issue->due_date >= now()->toDateString()) return 0;
        $daysOverdue = now()->diffInDays($issue->due_date);
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
        $activeIssues = BookIssue::whereHas('student', fn($q) => $q->where('user_id', $userId))
            ->orWhereHas('teacher', fn($q) => $q->where('user_id', $userId))
            ->whereIn('status', ['issued','overdue'])
            ->exists();
        if ($activeIssues) {
            return ['eligible' => false, 'reason' => 'Has unreturned books'];
        }
        $unpaidFines = BookIssue::whereHas('student', fn($q) => $q->where('user_id', $userId))
            ->orWhereHas('teacher', fn($q) => $q->where('user_id', $userId))
            ->where('fine_paid', false)->where('fine_amount', '>', 0)->exists();
        if ($unpaidFines) {
            return ['eligible' => false, 'reason' => 'Has unpaid library fines'];
        }
        return ['eligible' => true, 'reason' => null];
    }
}
