<?php

namespace App\Services;

use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

class TimetableSlotMathService
{
    public function maxConsecutiveForItems(Collection $items): int
    {
        $max = 0;

        foreach ($items->groupBy('day_of_week') as $dayItems) {
            $slots = $this->expandedSlotOrdersForItems($dayItems);
            $current = 0;
            $previous = null;

            foreach ($slots as $slot) {
                $current = $previous !== null && ((int) $slot === ((int) $previous + 1)) ? $current + 1 : 1;
                $max = max($max, $current);
                $previous = $slot;
            }
        }

        return $max;
    }

    public function expandedSlotOrdersForItems(Collection $items): Collection
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');

        return $items
            ->flatMap(function ($item) use ($slotOrders) {
                if (! $item->timetable_slot_id) {
                    return [];
                }

                $block = $this->blockSlotIds((int) $item->timetable_slot_id, max(1, (int) ($item->duration_slots ?? 1)));
                if (empty($block)) {
                    $block = [(int) $item->timetable_slot_id];
                }

                return collect($block)
                    ->map(fn ($slotId) => $slotOrders[$slotId] ?? null)
                    ->filter(fn ($order) => $order !== null);
            })
            ->unique()
            ->sort()
            ->values();
    }

    public function blockSlotIds(int $startSlotId, int $durationSlots = 1): array
    {
        $ordered = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get()->values();
        $startIndex = $ordered->search(fn ($slot) => (int) $slot->id === (int) $startSlotId);
        if ($startIndex === false || $durationSlots < 1) {
            return [];
        }

        $block = $ordered->slice($startIndex, $durationSlots)->values();
        if ($block->count() < $durationSlots || $block->contains(fn ($slot) => (bool) $slot->is_break)) {
            return [];
        }

        return $block->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
