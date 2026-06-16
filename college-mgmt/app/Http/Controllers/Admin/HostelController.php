<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{HostelBlock, HostelRoom, HostelAllocation, HostelFeeDemand, OutpassRequest, HostelComplaint, Student, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        abort_unless((int) $room->hostel_block_id === (int) $block->id, 404);

        $r->validate([
            'room_number' => 'required|string|max:20',
            'floor'       => 'required|integer|min:0',
            'room_type'   => 'required|in:single,double,triple,dormitory',
            'capacity'    => 'required|integer|min:1|max:20',
            'monthly_fee' => 'nullable|numeric|min:0',
            'status'      => 'required|in:available,occupied,maintenance,reserved',
        ]);

        $activeAllocations = HostelAllocation::where('hostel_room_id', $room->id)
            ->where('status', 'active')
            ->count();

        if ((int) $r->capacity < $activeAllocations) {
            return back()->withErrors(['capacity' => 'Room capacity cannot be reduced below active occupants.']);
        }

        if ($activeAllocations > 0 && in_array($r->status, ['maintenance', 'reserved'], true)) {
            return back()->withErrors(['status' => 'Occupied rooms cannot be moved to maintenance or reserved. Vacate or transfer students first.']);
        }

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

        if ($r->bed_number > $room->capacity) {
            return back()->withErrors(['bed_number' => 'Bed number cannot exceed room capacity.']);
        }

        if (! $room->block?->is_active) {
            return back()->withErrors(['hostel_room_id' => 'Inactive hostel blocks cannot receive new allocations.']);
        }

        if (! in_array($room->status, ['available', 'occupied'], true)) {
            return back()->withErrors(['hostel_room_id' => 'Only available or partially occupied rooms can be allocated.']);
        }

        // Room has capacity
        $activeCount = HostelAllocation::where('hostel_room_id', $room->id)->where('status', 'active')->count();
        if ($activeCount >= $room->capacity) {
            return back()->withErrors(['hostel_room_id' => 'Room is at full capacity.']);
        }

        $bedAllocation = HostelAllocation::where('hostel_room_id', $room->id)
            ->where('bed_number', $r->bed_number)
            ->first();

        if ($bedAllocation && $bedAllocation->status === 'active') {
            return back()->withErrors(['bed_number' => 'This bed is already allocated.']);
        }

        if ($bedAllocation) {
            $bedAllocation->update([
                'student_id'     => $r->student_id,
                'allocated_from' => $r->allocated_from,
                'allocated_to'   => null,
                'status'         => 'active',
                'allocated_by'   => auth()->id(),
                'vacated_at'     => null,
                'vacate_reason'  => null,
            ]);
        } else {
            HostelAllocation::create([
                'hostel_room_id' => $r->hostel_room_id,
                'student_id'     => $r->student_id,
                'bed_number'     => $r->bed_number,
                'allocated_from' => $r->allocated_from,
                'status'         => 'active',
                'allocated_by'   => auth()->id(),
            ]);
        }

        // Update room status if now full
        $newCount = HostelAllocation::where('hostel_room_id', $room->id)->where('status', 'active')->count();
        if ($newCount >= $room->capacity) {
            $room->update(['status' => 'occupied']);
        }

        return back()->with('success', 'Student allocated successfully.');
    }

    public function allocationVacate(HostelAllocation $allocation)
    {
        if ($allocation->status !== 'active') {
            return back()->with('error', 'Only active allocations can be vacated.');
        }

        $allocation->update([
            'status'       => 'vacated',
            'vacated_at'   => now(),
            'allocated_to' => now()->toDateString(),
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

    public function allocationTransfer(Request $r, HostelAllocation $allocation)
    {
        if ($allocation->status !== 'active') {
            return back()->with('error', 'Only active allocations can be transferred.');
        }

        $data = $r->validate([
            'hostel_room_id' => 'required|exists:hostel_rooms,id',
            'bed_number' => 'required|integer|min:1',
            'allocated_from' => 'required|date',
            'transfer_reason' => 'nullable|string|max:255',
        ]);

        $targetRoom = HostelRoom::findOrFail($data['hostel_room_id']);

        if ((int) $data['hostel_room_id'] === (int) $allocation->hostel_room_id
            && (int) $data['bed_number'] === (int) $allocation->bed_number) {
            return back()->withErrors(['bed_number' => 'Choose a different room or bed for the transfer.']);
        }

        if ($data['bed_number'] > $targetRoom->capacity) {
            return back()->withErrors(['bed_number' => 'Bed number cannot exceed room capacity.']);
        }

        if (! $targetRoom->block?->is_active) {
            return back()->withErrors(['hostel_room_id' => 'Inactive hostel blocks cannot receive transfers.']);
        }

        if (! in_array($targetRoom->status, ['available', 'occupied'], true)) {
            return back()->withErrors(['hostel_room_id' => 'Only available or partially occupied rooms can receive transfers.']);
        }

        $activeCount = HostelAllocation::where('hostel_room_id', $targetRoom->id)
            ->where('status', 'active')
            ->when($targetRoom->id === $allocation->hostel_room_id, fn($q) => $q->whereKeyNot($allocation->id))
            ->count();

        if ($activeCount >= $targetRoom->capacity) {
            return back()->withErrors(['hostel_room_id' => 'Target room is at full capacity.']);
        }

        $targetBedAllocation = HostelAllocation::where('hostel_room_id', $targetRoom->id)
            ->where('bed_number', $data['bed_number'])
            ->first();

        if ($targetBedAllocation && $targetBedAllocation->status === 'active') {
            return back()->withErrors(['bed_number' => 'Target bed is already allocated.']);
        }

        $sourceRoom = $allocation->room;

        $allocation->update([
            'status' => 'transferred',
            'allocated_to' => now()->toDateString(),
            'vacated_at' => now(),
            'vacate_reason' => $data['transfer_reason'] ?: 'Room transfer',
        ]);

        if ($targetBedAllocation) {
            $targetBedAllocation->update([
                'student_id' => $allocation->student_id,
                'allocated_from' => $data['allocated_from'],
                'allocated_to' => null,
                'status' => 'active',
                'allocated_by' => auth()->id(),
                'vacated_at' => null,
                'vacate_reason' => null,
            ]);
        } else {
            HostelAllocation::create([
                'hostel_room_id' => $targetRoom->id,
                'student_id' => $allocation->student_id,
                'bed_number' => $data['bed_number'],
                'allocated_from' => $data['allocated_from'],
                'status' => 'active',
                'allocated_by' => auth()->id(),
            ]);
        }

        if (!HostelAllocation::where('hostel_room_id', $sourceRoom->id)->where('status', 'active')->exists()) {
            $sourceRoom->update(['status' => 'available']);
        }

        $newCount = HostelAllocation::where('hostel_room_id', $targetRoom->id)->where('status', 'active')->count();
        $targetRoom->update(['status' => $newCount >= $targetRoom->capacity ? 'occupied' : 'available']);

        return back()->with('success', 'Student transferred to the new room.');
    }

    public function fees(Request $r)
    {
        $query = HostelFeeDemand::with(['student.user', 'allocation.room.block'])->latest('due_date');

        if ($r->filled('status')) {
            $query->where('status', $r->status);
        }

        if ($r->filled('month')) {
            $query->where('month', $r->month);
        }

        $demands = $query->paginate(20)->withQueryString();
        $stats = [
            'pending' => HostelFeeDemand::where('status', 'pending')->count(),
            'paid' => HostelFeeDemand::where('status', 'paid')->count(),
            'waived' => HostelFeeDemand::where('status', 'waived')->count(),
            'pending_amount' => HostelFeeDemand::where('status', 'pending')->sum('amount'),
        ];

        return view('admin.hostel.fees', compact('demands', 'stats'));
    }

    public function feeGenerate(Request $r)
    {
        $data = $r->validate([
            'month' => 'required|date_format:Y-m',
            'due_date' => 'required|date',
        ]);

        $created = 0;
        $skipped = 0;

        HostelAllocation::with('room')
            ->where('status', 'active')
            ->chunkById(100, function ($allocations) use ($data, &$created, &$skipped) {
                foreach ($allocations as $allocation) {
                    $amount = (float) ($allocation->room?->monthly_fee ?? 0);
                    if ($amount <= 0) {
                        $skipped++;
                        continue;
                    }

                    $exists = HostelFeeDemand::where('hostel_allocation_id', $allocation->id)
                        ->where('month', $data['month'])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    HostelFeeDemand::create([
                        'hostel_allocation_id' => $allocation->id,
                        'student_id' => $allocation->student_id,
                        'month' => $data['month'],
                        'amount' => $amount,
                        'status' => 'pending',
                        'due_date' => $data['due_date'],
                    ]);
                    $created++;
                }
            });

        return back()->with('success', "Hostel fee demands generated: {$created} created, {$skipped} skipped.");
    }

    public function feeMarkPaid(HostelFeeDemand $demand)
    {
        if ($demand->status !== 'pending') {
            return back()->with('error', 'Only pending hostel fee demands can be marked paid.');
        }

        $demand->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Hostel fee demand marked as paid.');
    }

    public function feeWaive(HostelFeeDemand $demand)
    {
        if ($demand->status !== 'pending') {
            return back()->with('error', 'Only pending hostel fee demands can be waived.');
        }

        $demand->update([
            'status' => 'waived',
            'paid_at' => null,
        ]);

        return back()->with('success', 'Hostel fee demand waived.');
    }

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
        if ($op->status !== 'pending') {
            return back()->with('error', 'Only pending outpass requests can be approved.');
        }

        if ($op->out_datetime && $op->out_datetime->isPast()) {
            return back()->with('error', 'Expired outpass requests cannot be approved. Reject it and ask the student to submit a fresh request.');
        }

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

        if ($op->status !== 'pending') {
            return back()->with('error', 'Only pending outpass requests can be rejected.');
        }

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
        if ($op->status !== 'approved') {
            return back()->with('error', 'Only approved outpasses can be marked returned.');
        }

        if ($op->out_datetime && now()->lt($op->out_datetime)) {
            return back()->with('error', 'Student cannot be marked returned before the approved out time.');
        }

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
        $data = $r->validate([
            'status'           => 'required|in:open,in_progress,resolved,closed',
            'assigned_to'      => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string|max:3000',
        ]);

        $resolutionNotes = trim((string) ($data['resolution_notes'] ?? $complaint->resolution_notes ?? ''));

        if (in_array($data['status'], ['resolved', 'closed'], true) && $resolutionNotes === '') {
            return back()->withErrors(['resolution_notes' => 'Resolution notes are required before resolving or closing a hostel complaint.']);
        }

        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $complaint->resolved_at) {
            $data['resolved_at'] = now();
        }

        if (! in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = null;
        }

        $complaint->update($data);

        return back()->with('success', 'Complaint updated.');
    }
}
