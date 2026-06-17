<?php

namespace App\Services;

use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;

class PlacementLifecycleService
{
    public const OPEN_DRIVE_STATUSES = ['upcoming', 'ongoing'];
    public const OPEN_APPLICATION_STATUSES = ['applied', 'shortlisted', 'interview'];
    public const TERMINAL_APPLICATION_STATUSES = ['selected', 'rejected', 'withdrawn'];

    public function validateDriveUpdate(PlacementDrive $drive, array $data): ?string
    {
        if (isset($data['drive_date'], $data['last_apply_date']) && $data['last_apply_date'] && $data['last_apply_date'] > $data['drive_date']) {
            return 'Application deadline cannot be after the placement drive date.';
        }

        $selectedCount = $drive->placements()->where('application_status', 'selected')->count();
        if (($data['vacancies'] ?? null) !== null && (int) $data['vacancies'] < $selectedCount) {
            return "Vacancies cannot be reduced below the selected offer count ({$selectedCount}).";
        }

        if (($data['status'] ?? null) === 'completed' && $this->openApplicationCount($drive) > 0) {
            return 'A placement drive cannot be completed while applied, shortlisted, or interview applications are still open.';
        }

        if (($data['status'] ?? null) === 'cancelled' && $selectedCount > 0) {
            return 'A placement drive with selected students cannot be cancelled. Close joining/offer records instead.';
        }

        if (in_array($drive->status, ['completed', 'cancelled'], true) && $drive->placements()->exists()) {
            $onlyStatusSame = ($data['status'] ?? $drive->status) === $drive->status;
            if (! $onlyStatusSame) {
                return 'Completed or cancelled drives with application history cannot be reopened or moved through ordinary edit routes.';
            }
        }

        return null;
    }

    public function validateDriveDelete(PlacementDrive $drive): ?string
    {
        if ($drive->placements()->exists()) {
            return 'Cannot delete a placement drive after students have applied. Cancel or complete it instead to preserve application history.';
        }

        return null;
    }

    public function validateApplicationCreate(PlacementDrive $drive, Student $student): ?string
    {
        if (! in_array($drive->status, self::OPEN_DRIVE_STATUSES, true)) {
            return 'This placement drive is not open for applications.';
        }

        if ($drive->last_apply_date && $drive->last_apply_date->lt(now()->startOfDay())) {
            return 'The application deadline for this drive has passed.';
        }

        if ($student->status !== 'active') {
            return 'Only active students can be added to a placement drive.';
        }

        if ($drive->placements()->where('student_id', $student->id)->exists()) {
            return 'This student has already applied to this drive.';
        }

        return null;
    }

    public function validateApplicationUpdate(Placement $placement, array $data): ?string
    {
        $newStatus = $data['application_status'] ?? $placement->application_status;
        $currentStatus = $placement->application_status;

        if (in_array($currentStatus, self::TERMINAL_APPLICATION_STATUSES, true) && $newStatus !== $currentStatus) {
            return 'Final placement application decisions are locked. Create an audited correction workflow instead of changing selected, rejected, or withdrawn history.';
        }

        if ($placement->drive && $placement->drive->status === 'cancelled' && ! in_array($newStatus, ['withdrawn', 'rejected'], true)) {
            return 'Applications on a cancelled drive can only be withdrawn or rejected.';
        }

        if ($newStatus === 'selected') {
            $vacancies = $placement->drive?->vacancies;
            if ($vacancies !== null) {
                $selectedCount = Placement::where('drive_id', $placement->drive_id)
                    ->where('application_status', 'selected')
                    ->where('id', '!=', $placement->id)
                    ->count();

                if ($selectedCount >= (int) $vacancies) {
                    return "Cannot select more students than available vacancies ({$vacancies}).";
                }
            }
        }

        return null;
    }

    public function openApplicationCount(PlacementDrive $drive): int
    {
        return $drive->placements()->whereIn('application_status', self::OPEN_APPLICATION_STATUSES)->count();
    }
}
