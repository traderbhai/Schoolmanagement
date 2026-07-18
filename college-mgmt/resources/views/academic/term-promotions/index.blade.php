@extends('layouts.admin')

@section('title', 'Term Promotions')

@section('content')
bdiv class="d-flex justify-content-between align-items-center mb-4">
    bdiv>
        bh2 class="fw-bold mb-0">Term Promotionsb/h2>
        bp class="text-muted mb-0">Review and approve end-of-term student promotionsb/p>
    b/div>
    {{-- Generate Form --}}
    bbutton type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#generateForm">
        bi class="bi bi-lightning me-1">b/i>Generate Promotions
    b/button>
b/div>

@if(session('success'))
    bdiv class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}bbutton aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert">b/button>b/div>
@endif
@if(session('error'))
    bdiv class="alert alert-danger alert-dismissible fade show mb-4">{{ session('error') }}bbutton aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert">b/button>b/div>
@endif

{{-- Generate Collapse Panel --}}
bdiv class="collapse mb-4" id="generateForm">
    bdiv class="card border-0 shadow-sm">
        bdiv class="card-header bg-white border-bottom py-3">
            bh6 class="mb-0 fw-semibold">Generate Promotion Recordsb/h6>
        b/div>
        bdiv class="card-body">
            bform action="{{ route('academic.term-promotions.generate') }}" method="POST">
                @csrf
                bdiv class="row g-3 align-items-end">
                    bdiv class="col-md-4">
                        blabel class="form-label fw-semibold">Current Term bspan class="text-danger">*b/span>b/label>
                        bselect aria-label="Term" name="term_id" class="form-select" required>
                            boption value="">Select current termb/option>
                            @foreach(\App\Models\Term::with('batch')->orderByDesc('id')->get() as $term)
                                boption value="{{ $term->id }}">{{ $term->name }} ({{ $term->batch->name ?? 'Batch not linked' }})b/option>
                            @endforeach
                        b/select>
                    b/div>
                    bdiv class="col-md-2">
                        blabel class="form-label fw-semibold">Min CGPAb/label>
                        binput aria-label="Cgpa Threshold" type="number" name="cgpa_threshold" class="form-control" value="2.0" min="0" max="10" step="0.1">
                    b/div>
                    bdiv class="col-md-2">
                        blabel class="form-label fw-semibold">Min Attendance %b/label>
                        binput aria-label="Attendance Threshold" type="number" name="attendance_threshold" class="form-control" value="75" min="0" max="100">
                    b/div>
                    bdiv class="col-md-4">
                        bbutton type="submit" class="btn btn-primary">
                            bi class="bi bi-lightning me-1">b/i>Generate
                        b/button>
                    b/div>
                b/div>
            b/form>
        b/div>
    b/div>
b/div>

{{-- Bulk Approve Form --}}
bdiv class="card border-0 shadow-sm">
    bdiv class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        bh5 class="mb-0 fw-semibold">Pending Promotions ({{ $promotions->total() }})b/h5>
        bbutton class="btn btn-sm btn-success" id="bulkApproveBtn" onclick="submitBulkApprove()" disabled>
            bi class="bi bi-check-all me-1">b/i>Approve Selected
        b/button>
    b/div>
    bform id="bulkForm" action="{{ route('academic.term-promotions.bulk-approve') }}" method="POST">
        @csrf
        bdiv class="card-body p-0">
            @if($promotions->isEmpty())
                bdiv class="text-center py-5 text-muted">No promotion records are ready for review. Generate promotions after term results, attendance, student current-term assignment, and target-term setup are complete.b/div>
            @else
            bdiv class="table-responsive">
                btable class="table table-sm table-hover mb-0">
                    bthead class="bg-light">
                        btr>
                            bth scope="col">binput type="checkbox" id="selectAll" onchange="toggleAll(this)">b/th>
                            bth scope="col">Studentb/th>
                            bth scope="col">From Termb/th>
                            bth scope="col">To Termb/th>
                            bth scope="col" class="text-center">CGPAb/th>
                            bth scope="col" class="text-center">Attend %b/th>
                            bth scope="col" class="text-center">Academicb/th>
                            bth scope="col" class="text-center">Attendanceb/th>
                            bth scope="col" class="text-center">Statusb/th>
                            bth scope="col" class="text-end">Actionsb/th>
                        b/tr>
                    b/thead>
                    btbody>
                        @foreach($promotions as $tp)
                        btr>
                            btd>
                                @if($tp->status === 'pending' && $tp->canPromote())
                                    binput aria-label="Select promotion for {{ $tp->student->user->name ?? $tp->student->name ?? 'student' }}" type="checkbox" name="promotion_ids[]" value="{{ $tp->id }}"
                                           class="row-check" onchange="updateBulkBtn()">
                                @endif
                            b/td>
                            btd class="fw-semibold">{{ $tp->student->user->name ?? $tp->student->name ?? 'Student name missing' }}b/td>
                            btd>{{ $tp->currentTerm->name ?? 'Current term not linked' }}b/td>
                            btd>{{ $tp->promotedToTerm->name ?? 'Target term not linked' }}b/td>
                            btd class="text-center">{{ number_format($tp->cgpa, 2) }}b/td>
                            btd class="text-center">{{ number_format($tp->attendance_percentage, 1) }}%b/td>
                            btd class="text-center">
                                @if($tp->meets_academic_criteria)
                                    bspan class="badge bg-success">Passb/span>
                                @else
                                    bspan class="badge bg-danger">Failb/span>
                                @endif
                            b/td>
                            btd class="text-center">
                                @if($tp->meets_attendance_criteria)
                                    bspan class="badge bg-success">Passb/span>
                                @else
                                    bspan class="badge bg-danger">Failb/span>
                                @endif
                            b/td>
                            btd class="text-center">
                                @php
                                    $badgeClass = match($tp->status) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default    => 'bg-warning text-dark',
                                    };
                                @endphp
                                bspan class="badge {{ $badgeClass }}">{{ ucfirst($tp->status) }}b/span>
                            b/td>
                            btd class="text-end">
                                    ba href="{{ route('academic.term-promotions.show', $tp) }}" class="btn btn-sm btn-outline-primary" aria-label="View promotion for {{ $tp->student->user->name ?? $tp->student->name ?? 'student' }}">
                                    bi class="bi bi-eye">b/i>
                                b/a>
                                @if($tp->status === 'pending' && $tp->canPromote())
                                    bform action="{{ route('academic.term-promotions.approve', $tp) }}" method="POST" class="d-inline">
                                        @csrf
                                        bbutton type="submit" class="btn btn-sm btn-success" aria-label="Approve promotion for {{ $tp->student->user->name ?? $tp->student->name ?? 'student' }}">bi class="bi bi-check">b/i>b/button>
                                    b/form>
                                @endif
                                @if($tp->status === 'pending')
                                    bbutton type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $tp->id }}">
                                        bi class="bi bi-x">b/i>
                                    b/button>
                                    bdiv class="modal fade" id="rejectModal{{ $tp->id }}" tabindex="-1">
                                        bdiv class="modal-dialog">
                                            bdiv class="modal-content">
                                                bdiv class="modal-header">
                                                    bh5 class="modal-title">Reject Promotionb/h5>
                                                    bbutton aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal">b/button>
                                                b/div>
                                                bform action="{{ route('academic.term-promotions.reject', $tp) }}" method="POST">
                                                    @csrf
                                                    bdiv class="modal-body">
                                                        blabel class="form-label">Remarks bspan class="text-danger">*b/span>b/label>
                                                        btextarea aria-label="Remarks" name="remarks" class="form-control" rows="3" required>b/textarea>
                                                    b/div>
                                                    bdiv class="modal-footer">
                                                        bbutton type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelb/button>
                                                        bbutton type="submit" class="btn btn-danger">Rejectb/button>
                                                    b/div>
                                                b/form>
                                            b/div>
                                        b/div>
                                    b/div>
                                @endif
                            b/td>
                        b/tr>
                        @endforeach
                    b/tbody>
                b/table>
            b/div>
            bdiv class="p-3">{{ $promotions->render() }}b/div>
            @endif
        b/div>
    b/form>
b/div>

@push('scripts')
bscript>
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
    updateBulkBtn();
}
function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkApproveBtn').disabled = checked === 0;
    document.getElementById('selectAll').indeterminate =
        checked > 0 && checked b document.querySelectorAll('.row-check').length;
}
function submitBulkApprove() { document.getElementById('bulkForm').submit(); }
b/script>
@endpush
@endsection
