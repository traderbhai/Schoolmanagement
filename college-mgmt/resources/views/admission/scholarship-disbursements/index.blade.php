@extends('layouts.admin')

@section('title', 'Scholarship Disbursement Queue')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-0">Scholarship Disbursements</h2>
    <p class="text-muted mb-0">Awarded scholarships pending disbursement to applicants</p>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-warning">{{ $pending->total() }}</div>
            <div class="text-muted small">Pending Disbursement</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-danger">₹{{ number_format($totalPendingAmount, 0) }}</div>
            <div class="text-muted small">Total Pending Amount</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-success">₹{{ number_format($totalDisbursed, 0) }}</div>
            <div class="text-muted small">Total Disbursed</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Filter by Program</label>
                <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All Programs —</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>{{ $prog->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif

        @if($pending->isEmpty())
            <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-1"></i> All scholarships have been disbursed.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Applicant</th>
                            <th>Application No.</th>
                            <th>Program</th>
                            <th>Scheme</th>
                            <th class="text-end">Amount (₹)</th>
                            <th>Awarded On</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $award)
                        <tr>
                            <td class="fw-semibold">{{ $award->applicant->user->name ?? '—' }}</td>
                            <td class="font-monospace small">{{ $award->applicant->application_number }}</td>
                            <td>{{ $award->applicant->program->name ?? '—' }}</td>
                            <td>
                                {{ $award->scheme->name }}
                                <div class="small text-muted font-monospace">{{ $award->scheme->scheme_code }}</div>
                            </td>
                            <td class="text-end fw-bold text-success">₹{{ number_format($award->awarded_amount, 2) }}</td>
                            <td class="small text-muted">{{ $award->awarded_at?->format('d M Y') }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#disburseModal{{ $award->id }}">
                                    <i class="bi bi-send me-1"></i>Disburse
                                </button>
                            </td>

                            <!-- Disburse Modal -->
                            <div class="modal fade" id="disburseModal{{ $award->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Disburse Scholarship</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('admission.scholarships.disburse', $award) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <p class="mb-3">
                                                    Disbursing <strong>₹{{ number_format($award->awarded_amount, 2) }}</strong>
                                                    to <strong>{{ $award->applicant->user->name ?? 'applicant' }}</strong>
                                                    for <strong>{{ $award->scheme->name }}</strong>.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="disbursement_ref{{ $award->id }}" class="form-label">Transaction / UTR Reference <span class="text-danger">*</span></label>
                                                    <input type="text" name="disbursement_ref" id="disbursement_ref{{ $award->id }}"
                                                           class="form-control" placeholder="e.g. UTR12345678" required>
                                                </div>
                                                <div>
                                                    <label for="notes{{ $award->id }}" class="form-label">Notes (optional)</label>
                                                    <textarea name="notes" id="notes{{ $award->id }}" class="form-control" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Mark as Disbursed</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $pending->render() }}</div>
        @endif
    </div>
</div>
@endsection
