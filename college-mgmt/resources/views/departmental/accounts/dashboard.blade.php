@extends('layouts.admin')
@section('title', 'Accounts — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Accounts Dashboard</h4>
</div>

{{-- Primary Finance KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.4rem">₹{{ number_format($totalBilled, 0) }}</div>
                    <div class="kpi-label">Total Billed</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-file-earmark-text me-1"></i>Fee demands raised</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.4rem">₹{{ number_format($totalCollected, 0) }}</div>
                    <div class="kpi-label">Collected</div>
                </div>
            </div>
            <div class="kpi-trend up"><i class="bi bi-arrow-up me-1"></i>Payments received</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.4rem">₹{{ number_format($outstanding, 0) }}</div>
                    <div class="kpi-label">Outstanding</div>
                </div>
            </div>
            <div class="kpi-trend down"><i class="bi bi-arrow-down me-1"></i>Pending collection</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card {{ $overdue > 0 ? 'kpi-amber' : 'kpi-blue' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="kpi-value">{{ $overdue }}</div>
                    <div class="kpi-label">Overdue Accounts</div>
                </div>
            </div>
            <div class="kpi-trend {{ $overdue > 0 ? 'up' : '' }}">
                @if($overdue > 0)<i class="bi bi-exclamation-triangle me-1"></i>Requires follow-up
                @else
                <i class="bi bi-check me-1"></i>None overdue
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Admission-side stats row --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.3rem">₹{{ number_format($totalAdmissionCollected, 0) }}</div>
                    <div class="kpi-label">Admission Fees Collected</div>
                </div>
            </div>
            <div class="kpi-trend up"><i class="bi bi-arrow-up me-1"></i>New intakes</div>
        </div>
    </div>
    <div class="col-md-3">
        <a href="{{ route('accounts.admission-payments') }}" class="text-decoration-none">
            <div class="kpi-card {{ $pendingAdmissionVerification > 0 ? 'kpi-amber' : 'kpi-blue' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-ui-checks"></i></div>
                    <div>
                        <div class="kpi-value">{{ $pendingAdmissionVerification }}</div>
                        <div class="kpi-label">Pending Verification</div>
                    </div>
                </div>
                <div class="kpi-trend {{ $pendingAdmissionVerification > 0 ? 'up' : '' }}">
                    @if($pendingAdmissionVerification > 0)<i class="bi bi-clock me-1"></i>View queue
                    @else
                    <i class="bi bi-check me-1"></i>Queue clear
                    @endif
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admission.scholarship-disbursements.index') }}" class="text-decoration-none">
            <div class="kpi-card kpi-cyan">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-award-fill"></i></div>
                    <div>
                        <div class="kpi-value">{{ $pendingScholarshipDisbursements }}</div>
                        <div class="kpi-label">Scholarships to Disburse</div>
                    </div>
                </div>
                <div class="kpi-trend"><i class="bi bi-currency-rupee me-1"></i>₹{{ number_format($pendingScholarshipAmount, 0) }} pending</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <div class="kpi-card {{ $overdueDemandsCount > 0 ? 'kpi-red' : 'kpi-blue' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-calendar-x-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $overdueDemandsCount }}</div>
                    <div class="kpi-label">Overdue Fee Demands</div>
                </div>
            </div>
            <div class="kpi-trend {{ $overdueDemandsCount > 0 ? 'down' : '' }}">
                <i class="bi bi-currency-rupee me-1"></i>₹{{ number_format($overdueDemandsAmount, 0) }} at risk
            </div>
        </div>
    </div>
</div>

{{-- Fee Demand KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card kpi-purple">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-file-earmark-ruled-fill"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.3rem">₹{{ number_format($totalDemanded, 0) }}</div>
                    <div class="kpi-label">Total Demanded</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-bar-chart me-1"></i>All demands raised</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card {{ $collectionRate >= 75 ? 'kpi-green' : ($collectionRate >= 50 ? 'kpi-amber' : 'kpi-red') }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-pie-chart-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $collectionRate }}%</div>
                    <div class="kpi-label">Collection Rate</div>
                </div>
            </div>
            <div class="kpi-trend {{ $collectionRate >= 75 ? 'up' : 'down' }}">
                <i class="bi bi-arrow-{{ $collectionRate >= 75 ? 'up' : 'down' }} me-1"></i>Overall efficiency
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <a href="{{ route('academic.fee-demands.index') }}" class="text-decoration-none">
            <div class="kpi-card {{ $overdueCount > 0 ? 'kpi-red' : 'kpi-blue' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
                    <div>
                        <div class="kpi-value">{{ $overdueCount }}</div>
                        <div class="kpi-label">Overdue Demands</div>
                    </div>
                </div>
                <div class="kpi-trend {{ $overdueCount > 0 ? 'down' : '' }}">
                    @if($overdueCount > 0)<i class="bi bi-eye me-1"></i>View demands
                    @else
                <i class="bi bi-check me-1"></i>None overdue
                @endif
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <div class="kpi-card {{ $totalPenalty > 0 ? 'kpi-amber' : 'kpi-blue' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="kpi-value" style="font-size:1.3rem">₹{{ number_format($totalPenalty, 0) }}</div>
                    <div class="kpi-label">Pending Penalties</div>
                </div>
            </div>
            <div class="kpi-trend {{ $totalPenalty > 0 ? 'up' : '' }}">
                @if($totalPenalty > 0)<i class="bi bi-hourglass me-1"></i>Late fees accrued
                @else
                <i class="bi bi-check me-1"></i>No penalties
                @endif
            </div>
        </div>
    </div>
</div>

@if($totalBilled > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
            <span class="fw-semibold">Collection Progress</span>
            <span>{{ round(($totalCollected/$totalBilled)*100) }}%</span>
        </div>
        <div class="progress" style="height:20px">
            <div class="progress-bar bg-success" style="width:{{ min(100, ($totalCollected/$totalBilled)*100) }}%"></div>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Recent Payments</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Student</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead>
                        <tbody>
                        @forelse($recentPayments as $p)
                            <tr>
                                <td>{{ $p->student?->user?->name ?? '—' }}</td>
                                <td>₹{{ number_format($p->amount_paid, 0) }}</td>
                                <td>{{ $p->payment_date?->format('d M Y') }}</td>
                                <td>{{ ucfirst($p->payment_method ?? '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Program Collection</div>
            <div class="card-body">
                @foreach($programs as $prog)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span>{{ $prog->name }}</span><span>₹{{ number_format($prog->collected,0) }}</span></div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('accounts.fee-collections') }}" class="btn btn-sm btn-primary text-start">
                    <i class="bi bi-cash-coin me-2"></i>Fee Collections
                </a>
                <a href="{{ route('accounts.outstanding') }}" class="btn btn-sm btn-outline-danger text-start">
                    <i class="bi bi-exclamation-circle me-2"></i>Outstanding Dues
                </a>
                <a href="{{ route('accounts.reconciliation') }}" class="btn btn-sm btn-outline-secondary text-start">
                    <i class="bi bi-arrow-left-right me-2"></i>Reconciliation
                </a>
                <a href="{{ route('accounts.reports') }}" class="btn btn-sm btn-outline-purple text-start">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Financial Reports
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
