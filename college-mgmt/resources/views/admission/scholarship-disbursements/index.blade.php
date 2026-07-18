@extends('layouts.admin')

@section('title', 'Scholarship Disbursement Queue')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-0">Scholarship Disbursements</h2>
    <p class="text-muted mb-0">Awarded scholarships pending disbursement to applicants.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-warning">{{ $pending->total() }}</div>
            <div class="text-muted small">Pending Disbursement</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-danger">Rs. {{ number_format($totalPendingAmount, 0) }}</div>
            <div class="text-muted small">Total Pending Amount</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-success">Rs. {{ number_format($totalDisbursed, 0) }}</div>
            <div class="text-muted small">Total Disbursed</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Filter by Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>{{ $prog->name }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['program_id']))
                <a href="{{ route('admission.scholarship-disbursements.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
            @endif
        </form>
        <div class="small text-muted mt-2">
            Showing awarded scholarships visible to your Admission role{{ request('program_id') ? ' for the selected program.' : '.' }}
        </div>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3">
                {{ session('success') }}
                <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <div class="fw-semibold">This disbursement needs attention.</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($pending->isEmpty())
            <div class="text-center py-5 px-3">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <h5 class="mt-3 mb-1">No scholarship disbursements are pending</h5>
                <p class="text-muted mb-3">
                    This queue only shows awarded scholarships that match your Admission visibility scope
                    @if(request('program_id'))
                        and selected program filter
                    @endif
                    . Award a scholarship from an applicant profile before it appears here for payment disbursement.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    @if(request()->hasAny(['program_id']))
                        <a href="{{ route('admission.scholarship-disbursements.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    @endif
                    <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                    <a href="{{ route('admission.scholarship-schemes.index') }}" class="btn btn-sm btn-outline-primary">Review Schemes</a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <caption class="visually-hidden">Awarded applicant scholarships pending disbursement</caption>
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Applicant</th>
                            <th scope="col">Application No.</th>
                            <th scope="col">Program</th>
                            <th scope="col">Scheme</th>
                            <th scope="col" class="text-end">Amount (Rs.)</th>
                            <th scope="col">Awarded On</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $award)
                            <tr>
                                <td class="fw-semibold">{{ $award->applicant->user->name ?? 'Applicant name missing' }}</td>
                                <td class="font-monospace small">{{ $award->applicant->application_number ?? 'Application number missing' }}</td>
                                <td>{{ $award->applicant->program->name ?? 'Program not assigned' }}</td>
                                <td>
                                    {{ $award->scheme->name ?? 'Scheme not linked' }}
                                    <div class="small text-muted font-monospace">{{ $award->scheme->scheme_code ?? 'Scheme code missing' }}</div>
                                </td>
                                <td class="text-end fw-bold text-success">Rs. {{ number_format($award->awarded_amount, 2) }}</td>
                                <td class="small text-muted">{{ $award->awarded_at?->format('d M Y') ?? 'Award date not recorded' }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#disburseModal{{ $award->id }}">
                                        <i class="bi bi-send me-1"></i>Disburse
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="disburseModal{{ $award->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Disburse Scholarship</h5>
                                            <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('admission.scholarships.disburse', $award) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <p class="mb-3">
                                                    Disbursing <strong>Rs. {{ number_format($award->awarded_amount, 2) }}</strong>
                                                    to <strong>{{ $award->applicant->user->name ?? 'applicant name missing' }}</strong>
                                                    for <strong>{{ $award->scheme->name ?? 'scheme not linked' }}</strong>.
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
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $pending->render() }}</div>
        @endif
    </div>
</div>
@endsection
