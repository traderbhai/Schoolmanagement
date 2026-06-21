@extends('layouts.admin')
@section('title', 'Selection Committee')

@section('content')
<div class="container-fluid py-3">
    <x-ui.page-header
        title="Final Selection Committee"
        subtitle="Decision board using assessment scores, variance, attendance, documents, payments, and applicant blockers."
        action-label="Normalization"
        :action-route="route('admission.assessment-normalization.index')"
        action-icon="bi-sliders"
    />

    <div class="alert alert-success border-0 shadow-sm d-flex gap-3 py-3">
        <div class="ui-kpi-tile-icon bg-white text-success"><i class="bi bi-clipboard2-check"></i></div>
        <div>
            <div class="fw-bold">Committee decision sequence</div>
            <div class="small">1. Review score evidence and variance &nbsp; 2. Check attendance/documents/payment readiness &nbsp; 3. Save selected/waitlist/rejected/hold/reschedule decision with reason.</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Committee Candidates</span>
            <span class="small text-muted">Reason is required for every decision</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Selection committee candidates">
                <thead><tr><th>Applicant</th><th>Program</th><th>Status</th><th>Documents</th><th>Paid</th><th>Decision</th></tr></thead>
                <tbody>
                @foreach($candidates as $applicant)
                    <tr>
                        <td><a href="{{ route('admission.applicants.show', $applicant) }}">{{ $applicant->user?->name ?? $applicant->application_number }}</a></td>
                        <td>{{ $applicant->program?->name }}</td>
                        <td>{{ $applicant->status_label }}</td>
                        <td>{{ $applicant->documents->where('status', 'verified')->count() }}/{{ $applicant->documents->count() }}</td>
                        <td>{{ number_format($applicant->payments->where('status', 'verified')->sum('amount_paid'), 0) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admission.selection-committee.decide') }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                                <select name="decision" class="form-select form-select-sm" aria-label="Committee decision">
                                    <option>selected</option><option>waitlist</option><option>rejected</option><option>hold</option><option>reschedule</option>
                                </select>
                                <input name="reason" class="form-control form-control-sm" value="Committee reviewed readiness and score evidence." aria-label="Decision reason">
                                <button class="btn btn-sm btn-primary">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $candidates->links() }}</div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Recent Decisions</span><span class="small text-muted">Audit trail for committee outcomes</span></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Recent selection decisions">
                        <thead><tr><th>Applicant</th><th>Decision</th><th>Reason</th><th>When</th></tr></thead>
                        <tbody>@foreach($decisions as $decision)<tr><td>{{ $applicantNames[$decision->applicant_id] ?? 'Applicant pending' }}</td><td>{{ $decision->decision }}</td><td>{{ Str::limit($decision->reason, 70) }}</td><td>{{ $decision->decided_at }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Score Evidence</span><span class="small text-muted">Use normalized/outlier signals before deciding</span></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Score evidence">
                        <thead><tr><th>Applicant</th><th>Raw</th><th>Normalized</th><th>Outlier</th></tr></thead>
                        <tbody>@foreach($scores as $score)<tr><td>{{ $applicantNames[$score->applicant_id] ?? 'Applicant pending' }}</td><td>{{ $score->raw_score }}</td><td>{{ $score->normalized_score }}</td><td>{{ $score->outlier_flag ? 'Yes' : 'No' }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
