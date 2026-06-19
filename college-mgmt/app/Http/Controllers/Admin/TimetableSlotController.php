<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableSlotController extends Controller
{
    public function index()
    {
        $this->authorizeAcademicScheduling();

        $slots = TimetableSlot::orderBy('sort_order')->get();
        return view('admin.timetable-slots.index', compact('slots'));
    }

    public function create()
    {
        $this->authorizeAcademicScheduling();

        return view('admin.timetable-slots.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAcademicScheduling();

        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_break'   => 'boolean',
            'sort_order' => 'integer',
        ]);
        TimetableSlot::create($data);
        return redirect()->route('admin.timetable-slots.index')->with('success', 'Slot added.');
    }

    public function show(TimetableSlot $timetableSlot)
    {
        $this->authorizeAcademicScheduling();

        return view('admin.timetable-slots.show', compact('timetableSlot'));
    }

    public function edit(TimetableSlot $timetableSlot)
    {
        $this->authorizeAcademicScheduling();

        return view('admin.timetable-slots.edit', compact('timetableSlot'));
    }

    public function update(Request $request, TimetableSlot $timetableSlot)
    {
        $this->authorizeAcademicScheduling();

        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_break'   => 'boolean',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $hasOperationalDependencies = $this->hasOperationalDependencies($timetableSlot);

        if ($hasOperationalDependencies && $this->changesScheduleShape($timetableSlot, $data)) {
            throw ValidationException::withMessages([
                'timetable_slot' => 'This time slot is already used in timetable, availability, or PMC planning records. Its time window and break classification cannot be changed.',
            ]);
        }

        if ($hasOperationalDependencies && $timetableSlot->is_active && array_key_exists('is_active', $data) && ! (bool) $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'This time slot is already used in operational schedules and cannot be deactivated.',
            ]);
        }

        $timetableSlot->update($data);
        return redirect()->route('admin.timetable-slots.index')->with('success', 'Slot updated.');
    }

    public function destroy(TimetableSlot $timetableSlot)
    {
        $this->authorizeAcademicScheduling();

        if ($this->hasOperationalDependencies($timetableSlot)) {
            return redirect()->route('admin.timetable-slots.index')
                ->with('error', 'This time slot is already used in timetable, availability, or PMC planning records and cannot be deleted.');
        }

        $timetableSlot->delete();
        return redirect()->route('admin.timetable-slots.index')->with('success', 'Slot deleted.');
    }

    private function authorizeAcademicScheduling(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicScheduling(auth()->user()), 403);
    }

    private function hasOperationalDependencies(TimetableSlot $slot): bool
    {
        return $slot->entries()->exists()
            || $slot->lockedSlots()->exists()
            || $slot->generationItems()->exists()
            || $slot->sessionDeliveryLogs()->exists()
            || $slot->teacherAvailabilities()->exists();
    }

    private function changesScheduleShape(TimetableSlot $slot, array $data): bool
    {
        $currentStart = substr((string) $slot->start_time, 0, 5);
        $currentEnd = substr((string) $slot->end_time, 0, 5);

        return ($data['start_time'] ?? $currentStart) !== $currentStart
            || ($data['end_time'] ?? $currentEnd) !== $currentEnd
            || (array_key_exists('is_break', $data) && (bool) $data['is_break'] !== (bool) $slot->is_break);
    }
}
