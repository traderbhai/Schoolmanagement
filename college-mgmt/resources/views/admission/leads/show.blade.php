@extends('layouts.admin')

@section('title', 'Lead — ' . $lead->name)
@section('page-title', 'Lead Details')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admission.leads.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> All Leads</a>
            <h2 class="fw-bold mb-0 mt-1">{{ $lead->name }}</h2>
            <span class="{{ $lead->status_badge }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
            <span class="text-muted ms-2">{{ $lead->source_label }}</span>
        </div>
        <div class="d-flex gap-2">
            @if(!$lead->isConverted())
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#convertModal">
                    <i class="bi bi-person-plus me-1"></i> Convert to Applicant
                </button>
            @else
                <a href="{{ route('admission.applicants.show', $lead->convertedApplicant) }}" class="btn btn-outline-success">
                    <i class="bi bi-person-check me-1"></i> View Applicant
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        {{-- Left Column --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Contact Info</h6>
                    <p class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></p>
                    <p class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i>{{ $lead->phone ?? '—' }}</p>
                    <p class="mb-0"><i class="bi bi-mortarboard me-2 text-muted"></i>{{ $lead->program?->name ?? 'Not specified' }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Source</h6>
                    <p class="mb-0">{{ $lead->source_label }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Last Contacted</h6>
                    <p class="mb-0">{{ $lead->last_contacted_at?->format('d M Y H:i') ?? 'Never' }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Enquiry Date</h6>
                    <p class="mb-0">{{ $lead->created_at->format('d M Y') }}</p>
                </div>
            </div>
            @if($lead->isConverted())
            <div class="card border-success border-0 shadow-sm mb-3" style="border-left:4px solid #198754!important">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Converted To Applicant</h6>
                    <p class="mb-0"><a href="{{ route('admission.applicants.show', $lead->convertedApplicant) }}">{{ $lead->convertedApplicant?->application_number }}</a></p>
                    <small class="text-muted">{{ $lead->converted_at?->format('d M Y H:i') }}</small>
                </div>
            </div>
            @endif
        </div>

        {{-- Right Column --}}
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">Notes</div>
                <div class="card-body">
                    @if($lead->notes)
                        <p class="mb-0">{{ $lead->notes }}</p>
                    @else
                        <p class="text-muted mb-0">No notes added yet.</p>
                    @endif
                </div>
            </div>

            @if(!$lead->isConverted())
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Quick Actions</div>
                <div class="card-body">
                    <div class="row g-3">
                        @if($lead->status === 'new')
                        <div class="col-md-6">
                            <form action="{{ route('admission.leads.contact', $lead) }}" method="POST">
                                @csrf
                                <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Contact notes (optional)"></textarea>
                                <button class="btn btn-secondary w-100"><i class="bi bi-telephone me-1"></i>Mark Contacted</button>
                            </form>
                        </div>
                        @endif
                        @if(in_array($lead->status, ['new','contacted','not_interested']))
                        <div class="col-md-6">
                            <form action="{{ route('admission.leads.interested', $lead) }}" method="POST">
                                @csrf
                                <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Notes (optional)"></textarea>
                                <button class="btn btn-warning w-100"><i class="bi bi-star me-1"></i>Mark Interested</button>
                            </form>
                        </div>
                        @endif
                        @if(!in_array($lead->status, ['not_interested','converted']))
                        <div class="col-md-6">
                            <form action="{{ route('admission.leads.not-interested', $lead) }}" method="POST">
                                @csrf
                                <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Reason (optional)"></textarea>
                                <button class="btn btn-danger w-100"><i class="bi bi-x-circle me-1"></i>Not Interested</button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Convert Modal --}}
@if(!$lead->isConverted())
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Convert to Applicant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admission.leads.convert', $lead) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">This will create a new applicant account for <strong>{{ $lead->name }}</strong> ({{ $lead->email }}) and mark this lead as converted.</p>
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <option value="">Select Program</option>
                            @foreach(\App\Models\Program::where('is_active', true)->orderBy('name')->get() as $program)
                                <option value="{{ $program->id }}" {{ $lead->program_id == $program->id ? 'selected' : '' }}>{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch (Optional)</label>
                        <select name="batch_id" class="form-select">
                            <option value="">Select Batch</option>
                            @foreach(\App\Models\Batch::orderByDesc('start_date')->get() as $batch)
                                <option value="{{ $batch->id }}">{{ $batch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Convert Now</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
