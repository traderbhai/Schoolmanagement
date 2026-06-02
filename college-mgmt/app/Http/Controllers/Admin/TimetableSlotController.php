<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;

class TimetableSlotController extends Controller
{
    public function index()
    {
        $slots = TimetableSlot::orderBy('sort_order')->get();
        return view('admin.timetable-slots.index', compact('slots'));
    }

    public function create()
    {
        return view('admin.timetable-slots.create');
    }

    public function store(Request $request)
    {
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
        return view('admin.timetable-slots.show', compact('timetableSlot'));
    }

    public function edit(TimetableSlot $timetableSlot)
    {
        return view('admin.timetable-slots.edit', compact('timetableSlot'));
    }

    public function update(Request $request, TimetableSlot $timetableSlot)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_break'   => 'boolean',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $timetableSlot->update($data);
        return redirect()->route('admin.timetable-slots.index')->with('success', 'Slot updated.');
    }

    public function destroy(TimetableSlot $timetableSlot)
    {
        $timetableSlot->delete();
        return redirect()->route('admin.timetable-slots.index')->with('success', 'Slot deleted.');
    }
}
