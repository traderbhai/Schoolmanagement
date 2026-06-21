@extends('layouts.admin')

@section('title', 'Term Promotions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Term Promotions</h2>
        <p class="text-muted mb-0">Review and approve end-of-term student promotions</p>
    </div>
    {{-- Generate Form --}}
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#generateForm">
        <i class="bi bi-lightning me-1"></i>Generate Promotions
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Generate Collapse Panel --}}
<div class="collapse mb-4" id="generateForm">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-semibold">Generate Promotion Records</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('academic.term-promotions.generate') }}" method="POST">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Current Term <span class="text-danger">*</span></label>
                        <select name="term_id" class="form-select" required>
                            <option value="">Select current term</option>
                            @foreach(\App\Models\Term::with('batch')->orderByDesc('id')->get() as $term)
                                <option value="{{ $term->id }}">{{ $term->name }} ({{ $term->batch->name ?? 'Batch not linked' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Min CGPA</label>
                        <input type="number" name="cgpa_threshold" class="form-control" value="2.0" min="0" max="10" step="0.1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Min Attendance %</label>
                        <input type="number" name="attendance_threshold" class="form-control" value="75" min="0" max="100">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-lightning me-1"></i>Generate
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Approve Form --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">Pending Promotions ({{ $promotions->total() }})</h5>
        <button class="btn btn-sm btn-success" id="bulkApproveBtn" onclick="submitBulkApprove()" disabled>
            <i class="bi bi-check-all me-1"></i>Approve Selected
        </button>
    </div>
    <form id="bulkForm" action="{{ route('academic.term-promotions.bulk-approve') }}" method="POST">
        @csrf
        <div class="card-body p-0">
            @if($promotions->isEmpty())
                <div class="text-center py-5 text-muted">No promotion records are ready for review. Generate promotions after term results, attendance, student current-term assignment, and target-term setup are complete.</div>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                            <th>Student</th>
                            <th>From Term</th>
                            <th>To Term</th>
                            <th class="text-center">CGPA</th>
                            <th class="text-center">Attend %</th>
                            <th class="text-center">Academic</th>
                            <th class="text-center">Attendance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($promotions as $tp)
                        <tr>
                            <td>
                                @if($tp->status === 'pending' && $tp->canPromote())
                                    <input type="checkbox" name="promotion_ids[]" value="{{ $tp->id }}"
                                           class="row-check" onchange="updateBulkBtn()">
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $tp->student->user->name ?? $tp->student->name ?? 'Student name missing' }}</td>
                            <td>{{ $tp->currentTerm->name ?? 'Current term not linked' }}</td>
                            <td>{{ $tp->promotedToTerm->name ?? 'Target term not linked' }}</td>
                            <td class="text-center">{{ number_format($tp->cgpa, 2) }}</td>
                            <td class="text-center">{{ number_format($tp->attendance_percentage, 1) }}%</td>
                            <td class="text-center">
                                @if($tp->meets_academic_criteria)
                                    <span class="badge bg-success">Pass</span>
                                @else
                                    <span class="badge bg-danger">Fail</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($tp->meets_attendance_criteria)
                                    <span class="badge bg-success">Pass</span>
                                @else
                                    <span class="badge bg-danger">Fail</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $badgeClass = match($tp->status) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default    => 'bg-warning text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($tp->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('academic.term-promotions.show', $tp) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($tp->status === 'pending' && $tp->canPromote())
                                    <form action="{{ route('academic.term-promotions.approve', $tp) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                                    </form>
                                @endif
                                @if($tp->status === 'pending')
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $tp->id }}">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <div class="modal fade" id="rejectModal{{ $tp->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Promotion</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="{{ route('academic.term-promotions.reject', $tp) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                                        <textarea name="remarks" class="form-control" rows="3" required></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $promotions->render() }}</div>
            @endif
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
    updateBulkBtn();
}
function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkApproveBtn').disabled = checked === 0;
    document.getElementById('selectAll').indeterminate =
        checked > 0 && checked < document.querySelectorAll('.row-check').length;
}
function submitBulkApprove() { document.getElementById('bulkForm').submit(); }
</script>
@endpush
@endsection
