@extends('layouts.admin')
@section('title', 'Hostel Management')
@section('page-title', 'Hostel Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Hostel</li>
@endsection

@section('content')

{{-- Alert --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Hostel operating sequence</div>
            <div class="small text-muted">Use this page to monitor capacity first, then handle allocations, fees, outpasses, complaints, and block setup.</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Check capacity</span>
            <span class="badge text-bg-light">2. Review allocations</span>
            <span class="badge text-bg-light">3. Check fees</span>
            <span class="badge text-bg-light">4. Handle outpasses/complaints</span>
            <span class="badge text-bg-light">5. Update blocks/rooms</span>
        </div>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-buildings"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $totalBlocks }}</div>
                <div class="kpi-label">Total Blocks</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-door-closed"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $totalRooms }}</div>
                <div class="kpi-label">Total Rooms</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon"><i class="bi bi-person-fill"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $totalOccupied }}</div>
                <div class="kpi-label">Currently Occupied</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $totalAvail }}</div>
                <div class="kpi-label">Available Rooms</div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Links --}}
<div class="row g-2 mb-3">
    <div class="col-auto">
        <a href="{{ route('admin.hostel.allocations') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-check me-1"></i>All Allocations</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.hostel.fees') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-cash-coin me-1"></i>Hostel Fees</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.hostel.outpasses') }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-door-open me-1"></i>Outpasses</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.hostel.complaints') }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-exclamation-triangle me-1"></i>Complaints</a>
    </div>
    <div class="col-auto ms-auto">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBlockModal"><i class="bi bi-plus-circle me-1"></i>Add Block</button>
    </div>
</div>

{{-- Blocks Table --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Hostel Blocks</h6></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Block Name</th>
                    <th>Gender</th>
                    <th>Capacity</th>
                    <th>Occupied</th>
                    <th>Available</th>
                    <th>Warden</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($blocks as $block)
                    <tr>
                        <td><strong>{{ $block->name }}</strong></td>
                        <td>
                            @if($block->gender === 'boys')
                                <span class="badge bg-primary">Boys</span>
                            @elseif($block->gender === 'girls')
                                <span class="badge bg-pink text-white" style="background-color:#e91e8c!important">Girls</span>
                            @else
                                <span class="badge bg-secondary">Mixed</span>
                            @endif
                        </td>
                        <td>{{ $block->total_capacity ?? '-' }}</td>
                        <td>{{ $block->occupied_count }}</td>
                        <td>{{ max(0, ($block->total_capacity ?? 0) - $block->occupied_count) }}</td>
                        <td>{{ $block->warden?->name ?? '-' }}</td>
                        <td>
                            @if($block->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.hostel.rooms', $block) }}" class="btn btn-sm btn-outline-primary">Rooms</a>
                            <a href="{{ route('admin.hostel.blocks.edit', $block) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No blocks found. Add the first block.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Block Modal --}}
<div class="modal fade" id="addBlockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.hostel.blocks.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Hostel Block</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Block Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Block A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="boys">Boys</option>
                            <option value="girls">Girls</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Floors <span class="text-danger">*</span></label>
                        <input type="number" name="total_floors" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address / Notes</label>
                        <textarea name="address_notes" class="form-control" rows="2" placeholder="Location, notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
