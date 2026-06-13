@extends('layouts.admin')
@section('title', 'Hostel Allocations')
@section('page-title', 'Hostel Allocations')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">Allocations</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search student..." value="{{ request('search') }}">
        <button class="btn btn-sm btn-primary">Search</button>
        @if(request('search'))<a href="{{ route('admin.hostel.allocations') }}" class="btn btn-sm btn-outline-secondary">Clear</a>@endif
    </form>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#allocateModal"><i class="bi bi-plus-circle me-1"></i>Allocate Student</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Enrollment No.</th>
                    <th>Block</th>
                    <th>Room</th>
                    <th>Bed</th>
                    <th>Allocated From</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allocations as $alloc)
                    <tr>
                        <td>{{ $alloc->student?->user?->name ?? '—' }}</td>
                        <td><code>{{ $alloc->student?->enrollment_number ?? '—' }}</code></td>
                        <td>{{ $alloc->room?->block?->name ?? '—' }}</td>
                        <td>{{ $alloc->room?->room_number ?? '—' }}</td>
                        <td>{{ $alloc->bed_number }}</td>
                        <td>{{ $alloc->allocated_from?->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.hostel.allocations.vacate', $alloc) }}" onsubmit="return confirm('Mark as vacated?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Vacate</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No active allocations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($allocations->hasPages())
        <div class="card-footer">{{ $allocations->withQueryString()->links() }}</div>
    @endif
</div>

{{-- Allocate Student Modal --}}
<div class="modal fade" id="allocateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.hostel.allocations.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Allocate Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">— Select Student —</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}">{{ $s->user?->name }} ({{ $s->enrollment_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Block <span class="text-danger">*</span></label>
                        <select name="block_id" id="blockSelect" class="form-select" required>
                            <option value="">— Select Block —</option>
                            @foreach($blocks as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room <span class="text-danger">*</span></label>
                        <select name="hostel_room_id" id="roomSelect" class="form-select" required>
                            <option value="">— Select Block First —</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Bed Number <span class="text-danger">*</span></label>
                            <input type="number" name="bed_number" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">From Date <span class="text-danger">*</span></label>
                            <input type="date" name="allocated_from" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Allocate</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const blocksData = @json($blocks->map(fn($b) => ['id' => $b->id, 'rooms' => $b->rooms->map(fn($r) => ['id' => $r->id, 'room_number' => $r->room_number, 'status' => $r->status])]));

document.getElementById('blockSelect').addEventListener('change', function() {
    const blockId = parseInt(this.value);
    const roomSelect = document.getElementById('roomSelect');
    roomSelect.innerHTML = '<option value="">— Select Room —</option>';
    const block = blocksData.find(b => b.id === blockId);
    if (block) {
        block.rooms.forEach(r => {
            if (r.status !== 'maintenance') {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = 'Room ' + r.room_number + ' (' + r.status + ')';
                roomSelect.appendChild(opt);
            }
        });
    }
});
</script>
@endpush

@endsection
