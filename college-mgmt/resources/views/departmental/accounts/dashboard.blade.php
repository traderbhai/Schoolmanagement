@extends('layouts.admin')
@section('title', 'Accounts - Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Accounts Dashboard</h4>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Accounts operating sequence</div>
                <div class="small text-muted">Use this dashboard to move from verification queues to outstanding follow-up, scholarships, reconciliation, and reports.</div>
                <div class="small text-muted mt-1">
                    <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                    <span class="badge text-bg-light">Source: fee demands, verified payments, Admission receipts, and scholarship awards</span>
                </div>
            </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Verify payments</span>
            <span class="badge text-bg-light">2. Review outstanding</span>
            <span class="badge text-bg-light">3. Process scholarships</span>
            <span class="badge text-bg-light">4. Reconcile receipts</span>
            <span class="badge text-bg-light">5. Export reports</span>
        </div>
    </div>
</div>

{{-- Finance Priority --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between">
            <div>
                <div class="text-muted small text-uppercase fw-semibold mb-1">Finance Priority</div>
                @if($pendingAdmissionVerification > 0)
                    <h5 class="fw-bold mb-1">Verify {{ $pendingAdmissionVerification }} admission payment{{ $pendingAdmissionVerification === 1 ? '' : 's' }}</h5>
                    <p class="text-muted mb-0">Admission payments are waiting for accounts review. Verify or reject them before enrollment decisions depend on stale payment status.</p>
                @elseif($overdueDemandsCount > 0)
                    <h5 class="fw-bold mb-1">Follow up on {{ $overdueDemandsCount }} overdue fee demand{{ $overdueDemandsCount === 1 ? '' : 's' }}</h5>
                    <p class="text-muted mb-0">Rs. {{ number_format($overdueDemandsAmount, 0) }} is at risk. Review outstanding dues and generate demand letters where needed.</p>
                @elseif($pendingScholarshipDisbursements > 0)
                    <h5 class="fw-bold mb-1">Process scholarship disbursements</h5>
                    <p class="text-muted mb-0">{{ $pendingScholarshipDisbursements }} award{{ $pendingScholarshipDisbursements === 1 ? '' : 's' }} totaling Rs. {{ number_format($pendingScholarshipAmount, 0) }} still need disbursement action.</p>
                @elseif($outstanding > 0)
                    <h5 class="fw-bold mb-1">Monitor active outstanding balances</h5>
                    <p class="text-muted mb-0">Rs. {{ number_format($outstanding, 0) }} remains open across active demands. Keep collection follow-up current.</p>
                @else
                    <h5 class="fw-bold mb-1">No urgent finance action due today</h5>
                    <p class="text-muted mb-0">Use this time to reconcile admission receipts, review reports, or export the latest fee collections.</p>
                @endif
            </div>
            <div class="d-grid gap-2" style="min-width: 230px;">
                @if($pendingAdmissionVerification > 0)
                    <a href="{{ route('accounts.admission-payments') }}" class="btn btn-warning btn-sm"><i class="bi bi-ui-checks me-1"></i> Verify Payments</a>
                @elseif($overdueDemandsCount > 0)
                    <a href="{{ route('accounts.outstanding', ['mode' => 'overdue_demands']) }}" class="btn btn-danger btn-sm"><i class="bi bi-exclamation-circle me-1"></i> Review Overdue Demands</a>
                @elseif($outstanding > 0)
                    <a href="{{ route('accounts.outstanding') }}" class="btn btn-danger btn-sm"><i class="bi bi-exclamation-circle me-1"></i> Review Outstanding</a>
                @elseif($pendingScholarshipDisbursements > 0)
                    <a href="{{ route('admission.scholarship-disbursements.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-award me-1"></i> Open Scholarships</a>
                @else
                    <a href="{{ route('accounts.reconciliation') }}" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i> Reconcile Receipts</a>
                @endif
                <a href="{{ route('accounts.reports') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i> Financial Reports</a>
            </div>
        </div>
    </div>
</div>

{{-- Primary Finance KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <x-ui.kpi-card href="{{ route('accounts.reports') }}" tone="blue" icon="bi-receipt-cutoff" value="Rs. {{ number_format($totalBilled, 0) }}" value-size="1.4rem" label="Total Billed" trend="Open demand report" trend-icon="bi-file-earmark-text" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-ui.kpi-card href="{{ route('accounts.fee-collections', ['status' => 'paid']) }}" tone="green" icon="bi-cash-coin" value="Rs. {{ number_format($totalCollected, 0) }}" value-size="1.4rem" label="Collected" trend="Open paid collections" trend-icon="bi-arrow-up" trend-tone="up" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-ui.kpi-card href="{{ route('accounts.outstanding') }}" tone="red" icon="bi-exclamation-circle-fill" value="Rs. {{ number_format($outstanding, 0) }}" value-size="1.4rem" label="Outstanding" trend="Open outstanding list" trend-icon="bi-arrow-down" trend-tone="down" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-ui.kpi-card href="{{ route('accounts.outstanding', ['mode' => 'overdue_demands']) }}" :tone="$overdue > 0 ? 'amber' : 'blue'" icon="bi-hourglass-split" :value="$overdue" label="Overdue Accounts" :trend="$overdue > 0 ? 'Open overdue queue' : 'None overdue'" :trend-icon="$overdue > 0 ? 'bi-exclamation-triangle' : 'bi-check'" :trend-tone="$overdue > 0 ? 'up' : null" />
    </div>
</div>

{{-- Admission-side stats row --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <x-ui.kpi-card href="{{ route('accounts.reconciliation') }}" tone="green" icon="bi-mortarboard-fill" value="Rs. {{ number_format($totalAdmissionCollected, 0) }}" value-size="1.3rem" label="Admission Fees Collected" trend="Open reconciliation" trend-icon="bi-arrow-up" trend-tone="up" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card href="{{ route('accounts.admission-payments') }}" :tone="$pendingAdmissionVerification > 0 ? 'amber' : 'blue'" icon="bi-ui-checks" :value="$pendingAdmissionVerification" label="Pending Verification" :trend="$pendingAdmissionVerification > 0 ? 'View queue' : 'Queue clear'" :trend-icon="$pendingAdmissionVerification > 0 ? 'bi-clock' : 'bi-check'" :trend-tone="$pendingAdmissionVerification > 0 ? 'up' : null" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card href="{{ route('admission.scholarship-disbursements.index') }}" tone="cyan" icon="bi-award-fill" :value="$pendingScholarshipDisbursements" label="Scholarships to Disburse" trend="Rs. {{ number_format($pendingScholarshipAmount, 0) }} pending" trend-icon="bi-cash-stack" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card href="{{ route('accounts.outstanding', ['mode' => 'overdue_demands']) }}" :tone="$overdueDemandsCount > 0 ? 'red' : 'blue'" icon="bi-calendar-x-fill" :value="$overdueDemandsCount" label="Overdue Fee Demands" trend="Rs. {{ number_format($overdueDemandsAmount, 0) }} at risk" trend-icon="bi-eye" :trend-tone="$overdueDemandsCount > 0 ? 'down' : null" />
    </div>
</div>

{{-- Fee Demand KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <x-ui.kpi-card tone="purple" icon="bi-file-earmark-ruled-fill" value="Rs. {{ number_format($totalDemanded, 0) }}" value-size="1.3rem" label="Total Demanded" trend="All demands raised" trend-icon="bi-bar-chart" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card :tone="$collectionRate >= 75 ? 'green' : ($collectionRate >= 50 ? 'amber' : 'red')" icon="bi-pie-chart-fill" value="{{ $collectionRate }}%" label="Collection Rate" trend="Overall efficiency" :trend-icon="$collectionRate >= 75 ? 'bi-arrow-up' : 'bi-arrow-down'" :trend-tone="$collectionRate >= 75 ? 'up' : 'down'" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card href="{{ route('accounts.outstanding', ['mode' => 'overdue_demands']) }}" :tone="$overdueCount > 0 ? 'red' : 'blue'" icon="bi-exclamation-octagon-fill" :value="$overdueCount" label="Overdue Demands" :trend="$overdueCount > 0 ? 'View demands' : 'None overdue'" :trend-icon="$overdueCount > 0 ? 'bi-eye' : 'bi-check'" :trend-tone="$overdueCount > 0 ? 'down' : null" />
    </div>
    <div class="col-md-3">
        <x-ui.kpi-card :tone="$totalPenalty > 0 ? 'amber' : 'blue'" icon="bi-exclamation-triangle-fill" value="Rs. {{ number_format($totalPenalty, 0) }}" value-size="1.3rem" label="Pending Penalties" :trend="$totalPenalty > 0 ? 'Late fees accrued' : 'No penalties'" :trend-icon="$totalPenalty > 0 ? 'bi-hourglass' : 'bi-check'" :trend-tone="$totalPenalty > 0 ? 'up' : null" />
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
                                <td>{{ $p->student?->user?->name ?? 'Student not linked' }}</td>
                                <td>Rs. {{ number_format($p->amount_paid, 0) }}</td>
                                <td>{{ $p->payment_date?->format('d M Y') ?? 'Payment date not recorded' }}</td>
                                <td>{{ ucfirst($p->payment_method ?? 'Method not recorded') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    No recent verified payments yet. Post or verify fee payments before using this recent collection list.
                                </td>
                            </tr>
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
                    <div class="d-flex justify-content-between small"><span>{{ $prog->name }}</span><span>Rs. {{ number_format($prog->collected,0) }}</span></div>
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
