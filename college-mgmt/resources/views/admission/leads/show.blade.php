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

    @include('admission.partials.action-center', ['actionCenter' => $actionCenter])
    <div class="alert alert-info border-0 shadow-sm small mb-4">
        <div class="fw-semibold mb-1">Lead operating sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Confirm ownership</span>
            <span class="badge text-bg-light border">2. Check source, program, and priority</span>
            <span class="badge text-bg-light border">3. Log call or follow-up</span>
            <span class="badge text-bg-light border">4. Resolve quality flags</span>
            <span class="badge text-bg-light border">5. Convert only when ready</span>
        </div>
        <div class="text-muted mt-2">Use the action center first, then record every contact in follow-ups, calls, or communications so the next counsellor can continue without guessing.</div>
    </div>

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
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">Quick Actions</div>
                    <div class="small text-muted">Use these only after the contact outcome is clear; status changes affect queues, SLAs, and conversion reporting.</div>
                </div>
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

            {{-- Assignment Card --}}
            <div class="card border-0 shadow-sm mt-3" id="leadAssignmentCard">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Assigned Counsellor</strong>
                        <div class="small text-muted">Ownership decides who must follow up and which manager sees the record in workload views.</div>
                    </div>
                </div>
                <div class="card-body">
                    @if($lead->assignedTo)
                        <p class="mb-2"><i class="bi bi-person-circle me-2"></i><strong>{{ $lead->assignedTo->name }}</strong></p>
                        <p class="text-muted small mb-3">Assigned {{ $lead->assigned_at?->diffForHumans() ?? '' }}</p>
                    @else
                        <p class="text-muted mb-3">Not assigned to any counsellor yet.</p>
                    @endif
                    @if(!$lead->isConverted())
                    <form action="{{ route('admission.leads.assign', $lead) }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <select name="assigned_to" class="form-select form-select-sm" style="width:auto" required>
                            <option value="">Select Counsellor…</option>
                            @foreach(\App\Models\User::whereHas('roles', fn($query) => $query->whereIn('name', \App\Services\DepartmentHierarchyService::ADMISSION_ROLE_NAMES))->orderBy('name')->get() as $u)
                                <option value="{{ $u->id }}" {{ $lead->assigned_to == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Assign</button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Follow-ups Card --}}
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Follow-ups</strong>
                        <div class="small text-muted">Schedule the next callback before leaving the page when the lead is not ready to convert.</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleFollowUpModal">
                        <i class="bi bi-plus"></i> Schedule
                    </button>
                </div>
                <div class="card-body p-0">
                    @if($lead->followUps->isEmpty())
                        <p class="text-muted p-3 mb-0">No follow-ups scheduled yet.</p>
                    @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>Date/Time</th><th>Type</th><th>Notes</th><th>Status</th><th></th>
                        </tr></thead>
                        <tbody>
                        @foreach($lead->followUps->sortBy('scheduled_at') as $fu)
                            <tr class="{{ $fu->isCompleted() ? 'text-muted' : '' }}">
                                <td class="{{ $fu->isCompleted() ? 'text-decoration-line-through' : '' }}">{{ $fu->scheduled_at->format('d M Y H:i') }}</td>
                                <td><span class="{{ $fu->type_badge }}">{{ $fu->type_label }}</span></td>
                                <td>{{ Str::limit($fu->notes, 50) }}</td>
                                <td>
                                    @if($fu->isCompleted())
                                        <span class="badge bg-success">Done</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$fu->isCompleted())
                                    <form action="{{ route('admission.leads.follow-ups.complete', $fu) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-link btn-sm p-0 text-success">&#10003; Done</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">Assignment Timeline</div>
                    <div class="small text-muted">Shows who owned the lead, who delegated it, and why ownership changed.</div>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($lead->assignmentEvents as $event)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $event->mode)) }} to {{ $event->toUser?->name ?? 'Unassigned' }}</div>
                            <div class="small text-muted">By {{ $event->assignedBy?->name ?? 'System' }} {{ $event->created_at?->diffForHumans() }} {{ $event->reason ? '- ' . $event->reason : '' }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No assignment events yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">Operating Timeline</div>
                    <div class="small text-muted">Read communications, calls, and data-quality flags together before deciding the next action.</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6>Communications</h6>
                            @forelse($lead->communicationLogs()->limit(5)->get() as $log)
                                <div class="border rounded p-2 mb-2 small">{{ strtoupper($log->channel) }} via {{ $log->provider }} - {{ $log->status }}<div class="text-muted">{{ Str::limit($log->body, 80) }}</div></div>
                            @empty
                                <p class="text-muted small">No communication history.</p>
                            @endforelse
                        </div>
                        <div class="col-md-6">
                            <h6>Calls And Quality</h6>
                            @forelse($lead->callLogs()->limit(3)->get() as $call)
                                <div class="border rounded p-2 mb-2 small">{{ ucfirst(str_replace('_', ' ', $call->disposition)) }} by {{ $call->caller?->name ?? 'Staff' }}<div class="text-muted">{{ $call->notes }}</div></div>
                            @empty
                                <p class="text-muted small">No calls logged.</p>
                            @endforelse
                            @foreach($lead->dataQualityFlags()->where('status', 'open')->limit(3)->get() as $flag)
                                <span class="badge bg-warning text-dark me-1">{{ $flag->flag_type }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
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
