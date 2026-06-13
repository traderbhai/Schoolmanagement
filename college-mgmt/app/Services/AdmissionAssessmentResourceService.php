<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdmissionAssessmentResourceService
{
    public function create(array $data): int
    {
        return DB::table('admission_assessment_resources')->insertGetId([
            'name' => $data['name'],
            'resource_type' => $data['resource_type'] ?? 'room',
            'capacity' => $data['capacity'] ?? 1,
            'location' => $data['location'] ?? null,
            'online_link' => $data['online_link'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function book(int $resourceId, array $data): int
    {
        return DB::table('admission_assessment_resource_bookings')->insertGetId([
            'resource_id' => $resourceId,
            'panel_id' => $data['panel_id'] ?? null,
            'slot_id' => $data['slot_id'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => 'booked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function conflicts(?int $resourceId = null)
    {
        $bookings = DB::table('admission_assessment_resource_bookings')
            ->when($resourceId, fn ($q) => $q->where('resource_id', $resourceId))
            ->where('status', 'booked')
            ->orderBy('starts_at')
            ->get();

        return $bookings->filter(function ($booking) use ($bookings) {
            return $bookings->where('id', '!=', $booking->id)
                ->where('resource_id', $booking->resource_id)
                ->contains(fn ($other) => $booking->starts_at < $other->ends_at && $booking->ends_at > $other->starts_at);
        })->values();
    }
}
