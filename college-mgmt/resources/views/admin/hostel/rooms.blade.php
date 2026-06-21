@extends('layouts.admin')
@section('title', 'Rooms - ' . $block->name)
@section('page-title', $block->name . ' - Rooms')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">{{ $block->name }}</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-secondary me-1">{{ ucfirst($block->gender) }}</span>
        <span class="badge bg-light text-dark">{{ $block->total_floors }} floor(s)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.hostel.allocations') }}" class="btn btn-sm btn-outline-primary">All Allocations</a>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal"><i class="bi bi-plus-circle me-1"></i>Add Room</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Room No.</th>
                    <th>Floor</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Occupied</th>
                    <th>Status</th>
                    <th>Monthly Fee</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rooms as $room)
                    <tr>
                        <td><strong>{{ $room->room_number }}</strong></td>
                        <td>{{ $room->floor }}</td>
                        <td>
                            @php $typeColors = ['single'=>'success','double'=>'primary','triple'=>'info','dormitory'=>'warning']; @endphp
                            <span class="badge bg-{{ $typeColors[$room->room_type] ?? 'secondary' }}">{{ ucfirst($room->room_type) }}</span>
                        </td>
                        <td>{{ $room->capacity }}</td>
                        <td>{{ $room->occupied_count }}</td>
                        <td>
                            @php $statusColors = ['available'=>'success','occupied'=>'danger','maintenance'=>'warning','reserved'=>'info']; @endphp
                            <span class="badge bg-{{ $statusColors[$room->status] ?? 'secondary' }}">{{ ucfirst($room->status) }}</span>
                        </td>
                        <td>Rs. {{ number_format($room->monthly_fee, 0) }}</td>
                        <td>
                            @if($room->status === 'available')
                                <a href="{{ route('admin.hostel.allocations') }}?room={{ $room->id }}" class="btn btn-sm btn-outline-success">Allocate</a>
                            @endif
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editRoom{{ $room->id }}">Edit</button>
                        </td>
                    </tr>

                    {{-- Edit Room Modal --}}
                    <div class="modal fade" id="editRoom{{ $room->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.hostel.rooms.update', [$block, $room]) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h6 class="modal-title">Edit Room {{ $room->room_number }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label">Room Number</label>
                                                <input type="text" name="room_number" class="form-control" value="{{ $room->room_number }}" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Floor</label>
                                                <input type="number" name="floor" class="form-control" value="{{ $room->floor }}" min="0" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Type</label>
                                                <select name="room_type" class="form-select" required>
                                                    @foreach(['single','double','triple','dormitory'] as $t)
                                                        <option value="{{ $t }}" @selected($room->room_type === $t)>{{ ucfirst($t) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Capacity</label>
                                                <input type="number" name="capacity" class="form-control" value="{{ $room->capacity }}" min="1" max="20" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Monthly Fee (Rs.)</label>
                                                <input type="number" name="monthly_fee" class="form-control" value="{{ $room->monthly_fee }}" min="0" step="0.01">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                    @foreach(['available','occupied','maintenance','reserved'] as $s)
                                                        <option value="{{ $s }}" @selected($room->status === $s)>{{ ucfirst($s) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No rooms are configured in this block yet. Add rooms before allocating beds, creating hostel fee demands, or reviewing occupancy.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Room Modal --}}
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.hostel.rooms.store', $block) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Room to {{ $block->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Room Number <span class="text-danger">*</span></label>
                            <input type="text" name="room_number" class="form-control" required placeholder="101">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Floor <span class="text-danger">*</span></label>
                            <input type="number" name="floor" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="room_type" class="form-select" required>
                                <option value="single">Single</option>
                                <option value="double" selected>Double</option>
                                <option value="triple">Triple</option>
                                <option value="dormitory">Dormitory</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" class="form-control" min="1" max="20" value="2" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Monthly Fee (Rs.)</label>
                            <input type="number" name="monthly_fee" class="form-control" min="0" step="0.01" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
