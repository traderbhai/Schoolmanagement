<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{HostelBlock, HostelRoom, HostelAllocation, HostelFeeDemand, OutpassRequest, HostelComplaint, Student, User};
use Illuminate\Http\Request;

class HostelController extends Controller
{
    // ── Blocks ───────────────────────────────────────────────────────────────

    public function index()
    {
        $blocks = HostelBlock::withCount('rooms')
            ->with('warden')
            ->get()
            ->map(function ($block) {
                $roomIds = $block->rooms()->pluck('id');
                $block->occupied_count = HostelAllocation::whereIn('hostel_room_id', $roomIds)
                    ->where('status', 'active')->count();
                $block->total_capacity = HostelRoom::whereIn('id', $roomIds)->sum('capacity');
                return $block;
            });

        $totalBlocks   = $blocks->count();
        $totalRooms    = HostelRoom::count();
        $totalOccupied = HostelAllocation::where('status', 'active')->count();
        $totalAvail    = HostelRoom::where('status', 'available')->count();

        return view('admin.hostel.index', compact('blocks', 'totalBlocks', 'totalRooms', 'totalOccupied', 'totalAvail'));
    }

    public function blockStore(Request $r)
    {
        $r->validate([
            'name'          => 'required|string|max:100',
            'gender'        => 'required|in:boys,girls,mixed',
            'total_floors'  => 'required|integer|min:1',
            'address_notes' => 'nullable|string|max:500',
        ]);

        HostelBlock::create($r->only('name', 'gender', 'total_floors', 'address_notes') + ['is_active' => true]);

        return back()->with('success', 'Block created successfully.');
    }

    public function blockEdit(HostelBlock $block)
    {
        $wardens = User::orderBy('name')->get();
        return view('admin.hostel.block-edit', compact('block', 'wardens'));
    }

    public function blockUpdate(Request $r, HostelBlock $block)
    {
        $r->validate([
            'name'          => 'required|string|max:100',
            'gender'        => 'required|in:boys,girls,mixed',
            'total_floors'  => 'required|integer|min:1',
            'warden_id'     => 'nullable|exists:users,id',
            'address_notes' => 'nullable|string|max:500',
            'is_active'     => 'boolean',
        ]);

        $block->update($r->only('name', 'gender', 'total_floors', 'warden_id', 'address_notes') + [
            'is_active' => $r->boolean('is_active'),
        ]);

        return redirect()->route('admin.hostel.index')->with('success', 'Block updated.');
    }

    // ── Rooms ─────────────────────────────────────────────────────────────────

    public function rooms(HostelBlock $block)
    {
        $rooms = $block->rooms()->withCount([
            'allocations as occupied_count' => fn($q) => $q->where('status', 'active'),
        ])->get();

        return view('admin.hostel.rooms', compact('block', 'rooms'));
    }

    public function roomStore(Request $r, HostelBlock $block)
    {
        $r->validate([
            'room_number' => 'required|string|max:20',
            'floor'       => 'required|integer|min:0',
            'room_type'   => 'required|in:single,double,triple,dormitory',
            'capacity'    => 'required|integer|min:1|max:20',
            'monthly_fee' => 'nullable|numeric|min:0',
        ]);

        $block->rooms()->create($r->only('room_number', 'floor', 'room_type', 'capacity', 'monthly_fee') + [
            'status' => 'available',
        ]);

        return back()->with('success', 'Room added.');
    }

    public function roomUpdate(Request $r, HostelBlock $block, HostelRoom $room)
    {
        $r->validate([
            'room_number' => 'required|string|max:20',
            'floor'       => 'required|integer|min:0',
            'room_type'   => 'required|in:single,double,triple,dormitory',
            'capacity'    => 'required|integer|min:1|max:20',
            'monthly_fee' => 'nullable|numeric|min:0',
            'status'      => 'required|in:available,occupied,maintenance,reserved',
        ]);

        $room->update($r->only('room_number', 'floor', 'room_type', 'capacity', 'monthly_fee', 'status'));

        return back()->with('success', 'Room updated.');
    }

    // ── Allocations ───────────────────────────────────────────────────────────

    public function allocations(Request $r)
    {
        $query = HostelAllocation::with(['room.block', 'student.user'])
            ->where('status', 'active');

        if ($r->filled('search')) {
            $search = $r->search;
            $query->whereHas('student.user', fn($q) => $q->where('name', 'like', "%$search%"))
                  ->orWhereHas('student', fn($q) => $q->where('enrollment_number', 'like', "%$search%"));
        }

        $allocations = $query->latest()->paginate(20)->withQueryString();

        $blocks   = HostelBlock::where('is_active', true)->with('rooms')->get();
        $students = Student::with('user')->get();

        return view('admin.hostel.allocations', compact('allocations', 'blocks', 'students'));
    }

    public function allocationStore(Request $r)
    {
        $r->validate([
            'student_id'     => 'required|exists:students,id',
            'hostel_room_id' => 'required|exists:hostel_rooms,id',
            'bed_number'     => 'required|integer|min:1',
            'allocated_from' => 'required|date',
        ]);

        // Student not already in hostel
        if (HostelAllocation::where('student_id', $r->student_id)->where('status', 'active')->exists()) {
            return back()->withErrors(['student_id' => 'Student already has an active hostel allocation.']);
        }

        $room = HostelRoom::findOrFail($r->hostel_room_id);

        // Room has capacity
        $activeCount = HostelAllocation::where('hostel_room_id', $room->id)->where('status', 'active')->count();
        if ($activeCount >= $room->capacity) {
            return back()->withErrors(['hostel_room_id' => 'Room is at full capacity.']);
        }

        HostelAllocation::create([
            'hostel_room_id' => $r->hostel_room_id,
            'student_id'     => $r->student_id,
            'bed_number'     => $r->bed_number,
            'allocated_from' => $r->allocated_from,
            'status'         => 'active',
            'allocated_by'   => auth()->id(),
        ]);

        // Update room status if now full
        $newCount = HostelAllocation::where('hostel_room_id', $room->id)->where('status', 'active')->count();
        if ($newCount >= $room->capacity) {
            $room->update(['status' => 'occupied']);
        }

        return back()->with('success', 'Student allocated successfully.');
    }

    public function allocationVacate(HostelAllocation $allocation)
    {
        $allocation->update([
            'status'     => 'vacated',
            'vacated_at' => now(),
        ]);

        // Update room status back to available if no more active allocations
        $activeCount = HostelAllocation::where('hostel_room_id', $allocation->hostel_room_id)
            ->where('status', 'active')->count();
        if ($activeCount === 0) {
            $allocation->room->update(['status' => 'available']);
        }

        return back()->with('success', 'Allocation vacated.');
    }

    // ── Outpasses ─────────────────────────────────────────────────────────────

    public function outpasses(Request $r)
    {
        $query = OutpassRequest::with(['student.user', 'allocation.room.block'])->latest();

        if ($r->filled('status')) {
            $query->where('status', $r->status);
        }

        $outpasses = $query->paginate(20)->withQueryString();

        return view('admin.hostel.outpasses', compact('outpasses'));
    }

    public function outpassApprove(OutpassRequest $op)
    {
        $op->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Outpass approved.');
    }

    public function outpassReject(Request $r, OutpassRequest $op)
    {
        $r->validate(['remarks' => 'nullable|string|max:500']);

        $op->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks'     => $r->remarks,
        ]);

        return back()->with('success', 'Outpass rejected.');
    }

    public function outpassReturn(OutpassRequest $op)
    {
        $op->update([
            'actual_return' => now(),
            'status'        => 'returned',
        ]);

        return back()->with('success', 'Student marked as returned.');
    }

    // ── Complaints ────────────────────────────────────────────────────────────

    public function complaints(Request $r)
    {
        $query = HostelComplaint::with(['student.user', 'block', 'room', 'assignedTo'])->latest();

        if ($r->filled('status')) {
            $query->where('status', $r->status);
        }
        if ($r->filled('priority')) {
            $query->where('priority', $r->priority);
        }

        $complaints = $query->paginate(20)->withQueryString();
        $users      = User::orderBy('name')->get();

        return view('admin.hostel.complaints', compact('complaints', 'users'));
    }

    public function complaintUpdate(Request $r, HostelComplaint $complaint)
    {
        $r->validate([
            'status'           => 'required|in:open,in_progress,resolved,closed',
            'assigned_to'      => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string',
        ]);

        $data = $r->only('status', 'assigned_to', 'resolution_notes');

        if (in_array($r->status, ['resolved', 'closed']) && ! $complaint->resolved_at) {
            $data['resolved_at'] = now();
        }

        $complaint->update($data);

        return back()->with('success', 'Complaint updated.');
    }
}
