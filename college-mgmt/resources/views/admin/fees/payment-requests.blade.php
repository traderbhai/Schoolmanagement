@extends('layouts.admin')
@section('title', 'Fee Payment Proofs')
@section('page-title', 'Fee Payment Proofs')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fees</a></li>
    <li class="breadcrumb-item active">Payment Proofs</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Pending Proofs</div><div class="h4 mb-0">{{ $stats['pending'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Verified Today</div><div class="h4 mb-0 text-success">{{ $stats['verified_today'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Rejected Today</div><div class="h4 mb-0 text-danger">{{ $stats['rejected_today'] }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Student</label>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or email">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary">Apply</button>
                <a href="{{ route('admin.fees.payment-requests.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                <a href="{{ route('admin.fees.collect') }}" class="btn btn-sm btn-outline-primary ms-auto">Collect Fee</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Demand</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th style="min-width:360px">Decision</th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $request)
                @php
                    $demand = $request->feeDemand;
                    $openAmount = $demand ? (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0) : null;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $request->student?->user?->name ?? 'Student' }}</div>
                        <div class="small text-muted">{{ $request->student?->enrollment_number ?? 'No enrollment' }} @if($request->student?->course) &bull; {{ $request->student->course->name }} @endif</div>
                    </td>
                    <td>
                        @if($demand)
                            <div>{{ $demand->term?->name ?? 'Fee Demand' }}</div>
                            <div class="small text-muted">Open: INR {{ number_format($openAmount, 2) }} | {{ str($demand->status)->headline() }}</div>
                        @else
                            <span class="text-muted">General payment proof</span>
                        @endif
                    </td>
                    <td class="fw-semibold">INR {{ number_format((float) $request->amount, 2) }}</td>
                    <td>
                        <div>{{ strtoupper($request->payment_method) }}</div>
                        <div class="small text-muted">{{ $request->transaction_ref ?: 'No ref' }}</div>
                        @if($request->proof_path)
                            <a class="small" href="{{ route('admin.fees.payment-requests.proof', $request) }}">Download proof</a>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $request->submitted_at?->format('d M Y H:i') }}</td>
                    <td>
                        <span class="badge text-bg-{{ $request->status === 'pending' ? 'warning' : ($request->status === 'verified' ? 'success' : 'danger') }}">{{ ucfirst($request->status) }}</span>
                        @if($request->verifier)
                            <div class="small text-muted">By {{ $request->verifier->name }}</div>
                        @endif
                    </td>
                    <td>
                        @if($request->status === 'pending')
                            <form method="POST" action="{{ route('admin.fees.payment-requests.verify', $request) }}" class="d-flex gap-1 mb-1">
                                @csrf
                                @method('PATCH')
                                <input class="form-control form-control-sm" name="notes" placeholder="Verification note">
                                <button class="btn btn-sm btn-success">Verify</button>
                            </form>
                            <form method="POST" action="{{ route('admin.fees.payment-requests.reject', $request) }}" class="d-flex gap-1">
                                @csrf
                                @method('PATCH')
                                <input class="form-control form-control-sm" name="notes" placeholder="Rejection reason" required>
                                <button class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        @else
                            <div class="small text-muted">{{ $request->notes ?: 'No notes recorded.' }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No payment proofs match the current filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="card-footer bg-white">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
