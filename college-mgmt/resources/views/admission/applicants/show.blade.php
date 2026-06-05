@extends('layouts.admin')

@section('title', 'Applicant — ' . ($applicant->user->name ?? 'N/A'))

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
        <a href="{{ route('admission.applicants.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> All Applicants</a>
        <h2 class="fw-bold mb-0 mt-1">{{ $applicant->user->name ?? 'Unknown' }}</h2>
        <div class="text-muted small">
            <span class="font-monospace">{{ $applicant->application_number }}</span> &middot;
            {{ $applicant->program->name ?? 'N/A' }}
            @if($applicant->batch) &middot; {{ $applicant->batch->name }} @endif
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="{{ $applicant->status_badge }} fs-6">{{ $applicant->status_label }}</span>
        @if($canChangeStatus && count($allowedTransitions) > 0)
        <form action="{{ route('admission.applicants.status', $applicant) }}" method="POST" class="d-flex gap-2">
            @csrf
            <select name="status" class="form-select form-select-sm" style="width:auto">
                @foreach($allowedTransitions as $s)
                    <option value="{{ $s }}">→ {{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Update</button>
        </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="crmTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#application"><i class="bi bi-file-text me-1"></i>Application</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bi bi-folder me-1"></i>Documents <span class="badge bg-secondary">{{ $applicant->documents->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#counselling"><i class="bi bi-chat-dots me-1"></i>Counselling Log <span class="badge bg-info text-dark">{{ $applicant->counsellingLogs->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#notes"><i class="bi bi-sticky me-1"></i>Notes <span class="badge bg-secondary">{{ $applicant->teamNotes->count() }}</span></a></li>
</ul>

<div class="tab-content">
    {{-- APPLICATION TAB --}}
    <div class="tab-pane fade show active" id="application">
        @php
            $sections = [
                'Personal Details' => $applicant->personal_data,
                'Academic Details' => $applicant->academic_data,
                'Family Details'   => $applicant->family_data,
                'Additional Info'  => $applicant->additional_data,
            ];
        @endphp
        @foreach($sections as $title => $data)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ $title }}</div>
            <div class="card-body">
                @if(is_array($data) && count($data))
                <div class="row g-3">
                    @foreach($data as $key => $value)
                    <div class="col-sm-6 col-md-4">
                        <div class="small text-muted text-uppercase" style="font-size:0.72rem">{{ str_replace('_',' ',$key) }}</div>
                        <div class="fw-medium">{{ $value ?: '—' }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">No data submitted.</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- DOCUMENTS TAB --}}
    <div class="tab-pane fade" id="documents">
        @if($applicant->documents->isEmpty())
            <div class="text-center text-muted py-5"><i class="bi bi-folder2-open fs-1"></i><p class="mt-2">No documents uploaded yet.</p></div>
        @else
        <div class="row g-3">
            @foreach($applicant->documents as $doc)
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">{{ $doc->requiredDocument->name ?? $doc->original_name }}</div>
                                <div class="small text-muted">{{ $doc->original_name }} ({{ $doc->file_size_kb }} KB)</div>
                            </div>
                            <span class="badge {{ $doc->status === 'verified' ? 'bg-success' : ($doc->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">
                                {{ ucfirst($doc->status) }}
                            </span>
                        </div>
                        @if($doc->status === 'rejected' && $doc->rejection_reason)
                            <div class="alert alert-danger py-1 small mb-2">{{ $doc->rejection_reason }}</div>
                        @endif
                        <div class="d-flex gap-2 mt-2">
                            <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal" data-bs-target="#verifyModal{{ $doc->id }}" data-action="verified">
                                <i class="bi bi-check-circle"></i> Verify
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#verifyModal{{ $doc->id }}" data-action="rejected">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Verify Modal --}}
            <div class="modal fade" id="verifyModal{{ $doc->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form action="{{ route('admission.documents.verify', $doc) }}" method="POST">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Document Action</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="" class="doc-action-input">
                                <div class="mb-3 rejection-reason-field d-none">
                                    <label class="form-label">Rejection Reason</label>
                                    <textarea name="rejection_reason" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- COUNSELLING LOG TAB --}}
    <div class="tab-pane fade" id="counselling">
        <div class="row g-4">
            <div class="col-md-7">
                <h6 class="fw-semibold mb-3">Interaction History</h6>
                @if($applicant->counsellingLogs->isEmpty())
                    <div class="text-muted">No interactions logged yet.</div>
                @else
                <div class="timeline">
                    @php
                        $typeIcons = ['call'=>'telephone','email'=>'envelope','whatsapp'=>'whatsapp','walk_in'=>'person-walking','other'=>'three-dots'];
                        $outcomeColors = ['interested'=>'success','not_interested'=>'secondary','callback'=>'warning','enrolled'=>'primary','lost'=>'danger','follow_up'=>'info'];
                    @endphp
                    @foreach($applicant->counsellingLogs->sortByDesc('created_at') as $log)
                    <div class="d-flex mb-3">
                        <div class="me-3 text-center" style="min-width:40px">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px">
                                <i class="bi bi-{{ $typeIcons[$log->interaction_type] ?? 'chat' }}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="fw-semibold small">{{ ucfirst(str_replace('_',' ',$log->interaction_type)) }}</span>
                                        <span class="badge bg-{{ $outcomeColors[$log->outcome] ?? 'secondary' }} small">{{ ucfirst(str_replace('_',' ',$log->outcome)) }}</span>
                                    </div>
                                    <p class="mb-1 small">{{ $log->notes }}</p>
                                    <div class="d-flex gap-3 text-muted" style="font-size:0.75rem">
                                        <span>By: {{ $log->loggedBy->name ?? 'N/A' }}</span>
                                        <span>{{ $log->created_at->diffForHumans() }}</span>
                                        @if($log->duration_minutes)<span>{{ $log->duration_minutes }} min</span>@endif
                                        @if($log->next_followup_date)<span class="text-warning"><i class="bi bi-calendar2"></i> Follow-up: {{ $log->next_followup_date->format('d M Y') }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <div class="col-md-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">Add Interaction</div>
                    <div class="card-body">
                        <form action="{{ route('admission.applicants.counselling-log', $applicant) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small">Type</label>
                                <select name="interaction_type" class="form-select form-select-sm" required>
                                    <option value="call">📞 Call</option>
                                    <option value="email">📧 Email</option>
                                    <option value="whatsapp">💬 WhatsApp</option>
                                    <option value="walk_in">🚶 Walk-in</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Outcome</label>
                                <select name="outcome" class="form-select form-select-sm" required>
                                    <option value="interested">Interested</option>
                                    <option value="callback">Callback</option>
                                    <option value="follow_up">Follow Up</option>
                                    <option value="not_interested">Not Interested</option>
                                    <option value="enrolled">Enrolled</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Next Follow-up Date</label>
                                <input type="date" name="next_followup_date" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Duration (minutes)</label>
                                <input type="number" name="duration_minutes" class="form-control form-control-sm" min="1" placeholder="e.g. 15">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Log Interaction</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- NOTES TAB --}}
    <div class="tab-pane fade" id="notes">
        <div class="row g-4">
            <div class="col-md-8">
                @if($applicant->teamNotes->isEmpty())
                    <div class="text-muted">No internal notes yet.</div>
                @else
                    @foreach($applicant->teamNotes->sortByDesc('created_at') as $note)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold small">{{ $note->user->name ?? 'N/A' }}</span>
                                <span class="text-muted small">{{ $note->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mb-0">{{ $note->note }}</p>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">Add Note</div>
                    <div class="card-body">
                        <form action="{{ route('admission.applicants.notes', $applicant) }}" method="POST">
                            @csrf
                            <textarea name="note" class="form-control form-control-sm mb-2" rows="4" required placeholder="Internal note..."></textarea>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Add Note</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Wire up verify modals
document.querySelectorAll('[data-bs-target^="#verifyModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = document.querySelector(this.getAttribute('data-bs-target'));
        const action = this.getAttribute('data-action');
        modal.querySelector('.doc-action-input').value = action;
        modal.querySelector('.rejection-reason-field').classList.toggle('d-none', action !== 'rejected');
    });
});
</script>
@endpush
@endsection
