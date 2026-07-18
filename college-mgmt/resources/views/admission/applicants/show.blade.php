@extends('layouts.admin')

@section('title', 'Applicant - ' . ($applicant->user->name ?? 'Applicant name missing'))

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
        <a href="{{ route('admission.applicants.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> All Applicants</a>
        <h2 class="fw-bold mb-0 mt-1">{{ $applicant->user->name ?? 'Unknown' }}</h2>
        <div class="text-muted small">
            <span class="font-monospace">{{ $applicant->application_number }}</span> -
            {{ $applicant->program->name ?? 'Program not assigned' }}
            @if($applicant->batch) - {{ $applicant->batch->name }} @endif
        </div>
        <div class="mt-1 d-flex gap-2 flex-wrap align-items-center">
            <span class="badge bg-light text-dark border">{{ $applicant->category_label }}</span>
            @if($applicant->category_certificate_verified)
                <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i>Certificate Verified</span>
            @elseif(in_array($applicant->category, ['obc','obc_nc','sc','st','ews','pwd']))
                @if(app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
                <form method="POST" action="{{ route('admission.applicants.category.update', $applicant) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning py-0 px-2">
                        <i class="bi bi-patch-check me-1"></i>Verify Certificate
                    </button>
                </form>
                @else
                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Certificate Pending</span>
                @endif
            @endif
            @if($applicant->entrance_exam_type && $applicant->entrance_exam_type !== 'none')
                <span class="badge bg-secondary">{{ $applicant->entrance_exam_label }}: {{ $applicant->entrance_exam_score }}</span>
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-start justify-content-md-end gap-2">
        <span class="{{ $applicant->status_badge }} fs-6">{{ $applicant->status_label }}</span>
        @if($canChangeStatus && count($allowedTransitions) > 0)
        <form action="{{ route('admission.applicants.status', $applicant) }}" method="POST" class="d-flex gap-2">
            @csrf
            <select aria-label="Applicant status transition" name="status" class="form-select form-select-sm" style="width:auto">
                @foreach($allowedTransitions as $s)
                    <option value="{{ $s }}">To {{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Update applicant status</button>
        </form>
        @endif
        {{-- Enrollment Actions --}}
        @if($applicant->status === 'selected')
            @if($applicant->isEnrolled())
                @php $enrollment = $applicant->enrollmentConfirmation; @endphp
                <a href="{{ route('admission.enrollment.show', $enrollment) }}" class="btn btn-sm btn-success">
                    <i class="bi bi-person-check me-1"></i> View Enrollment
                </a>
                @if($enrollment->student)
                <a href="{{ route('admin.students.show', $enrollment->student) }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-person me-1"></i> Student Profile
                </a>
                @endif
            @else
                <a href="{{ route('admission.enrollment.create', $applicant) }}" class="btn btn-sm btn-warning">
                    <i class="bi bi-person-plus me-1"></i> Proceed to Enrollment
                </a>
            @endif
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@include('admission.partials.action-center', ['actionCenter' => $actionCenter])
<div class="alert alert-info border-0 shadow-sm small mb-4">
    <div class="fw-semibold mb-1">Applicant review sequence</div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light border">1. Check action center blockers</span>
        <span class="badge text-bg-light border">2. Verify application profile</span>
        <span class="badge text-bg-light border">3. Clear documents and payments</span>
        <span class="badge text-bg-light border">4. Log counselling and notes</span>
        <span class="badge text-bg-light border">5. Move status only when ready</span>
    </div>
    <div class="text-muted mt-2">Use tabs left to right when reviewing a case. Staff-only notes and counselling history explain why the applicant is blocked, ready, selected, or enrolled.</div>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="crmTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#application"><i class="bi bi-file-text me-1"></i>Application</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bi bi-folder me-1"></i>Documents <span class="badge bg-secondary">{{ $applicant->documents->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#counselling"><i class="bi bi-chat-dots me-1"></i>Counselling Log <span class="badge bg-info text-dark">{{ $applicant->counsellingLogs->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#assignment"><i class="bi bi-diagram-3 me-1"></i>Assignment <span class="badge bg-secondary">{{ $applicant->assignmentEvents->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#notes"><i class="bi bi-sticky me-1"></i>Notes <span class="badge bg-secondary">{{ $applicant->teamNotes->count() }}</span></a></li>
</ul>

<div class="tab-content">
    {{-- APPLICATION TAB --}}
    <div class="tab-pane fade show active" id="application">
        <div class="alert alert-light border small">
            <strong>Application tab:</strong> confirm personal, academic, family, and additional details before changing status or creating an approval action.
        </div>
        @php
            $sections = [
                'Personal Details' => $applicant->personal_data,
                'Academic Details' => $applicant->academic_data,
                'Family Details'   => $applicant->family_data,
                'Additional Info'  => $applicant->additional_data,
            ];
            $emptySectionGuidance = [
                'Personal Details' => 'Ask the applicant to complete profile basics such as phone, address, identity, and contact preferences before document or payment follow-up.',
                'Academic Details' => 'Collect qualification, institution, score, and entrance details before assessment, shortlist, or offer decisions.',
                'Family Details' => 'Capture parent or guardian decision-maker details so counsellors can plan parent calls and escalation reminders.',
                'Additional Info' => 'Use counselling notes or applicant follow-up to capture hostel, transport, scholarship, objection, or special-support needs.',
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
                        <div class="fw-medium">{{ $value ?: 'Not provided' }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="border rounded bg-light-subtle p-3 small text-muted">
                    <div class="fw-semibold text-dark mb-1">{{ $title }} not submitted yet</div>
                    <div>{{ $emptySectionGuidance[$title] ?? 'Capture this section before advancing the applicant to the next admission stage.' }}</div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- DOCUMENTS TAB --}}
    <div class="tab-pane fade" id="documents">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="fw-semibold">Documents ({{ $applicant->documents->count() }})</span>
                <div class="small text-muted">Rejected documents need a clear reason; verified documents support selection and enrollment readiness.</div>
            </div>
            <a href="{{ route('admission.documents.queue') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-folder-check me-1"></i>View All Pending Docs
            </a>
        </div>
        @if($applicant->documents->isEmpty())
            <div class="border rounded bg-light-subtle p-3 text-muted small">
                <div class="fw-semibold text-dark mb-1">No applicant documents are uploaded yet</div>
                <div>Ask the applicant to upload mandatory documents from the checklist, then return here to preview, verify, or reject with a clear reason.</div>
            </div>
        @else
        <div class="row g-3">
            @foreach($applicant->documents as $doc)
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">{{ $doc->requiredDocument->name ?? $doc->original_name }}</div>
                                <div class="small text-muted">
                                    <i class="bi {{ $doc->file_icon }} me-1"></i>
                                    {{ $doc->original_name }} - {{ $doc->formatted_file_size }}
                                    @if($doc->uploaded_at) - {{ $doc->uploaded_at->diffForHumans() }} @endif
                                </div>
                            </div>
                            {!! $doc->status_badge !!}
                        </div>
                        @if($doc->status === 'rejected' && $doc->rejection_reason)
                            <div class="alert alert-danger py-1 small mb-2">{{ $doc->rejection_reason }}</div>
                        @endif
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            @if($doc->file_available)
                            <a href="{{ route('admission.documents.preview', $doc) }}" target="_blank" rel="noopener"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Preview
                            </a>
                            <a href="{{ route('admission.documents.download', $doc) }}"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Download
                            </a>
                            @else
                            <span class="badge text-bg-light border align-self-center">
                                <i class="bi bi-file-earmark-x me-1"></i>File missing - ask applicant to re-upload
                            </span>
                            @endif
                            @if($doc->status !== 'verified')
                            <form method="POST" action="{{ route('admission.documents.verify', $doc) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success"
                                        onclick="return confirm('Verify this document for {{ addslashes($applicant->user->name ?? 'this applicant') }}? Confirm file preview, required-document match, applicant identity, and verification evidence before approval.')">
                                    <i class="bi bi-check-circle"></i> Verify
                                </button>
                            </form>
                            @endif
                            @if($doc->status !== 'rejected')
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-doc-id="{{ $doc->id }}"
                                data-doc-name="{{ $doc->requiredDocument->name ?? $doc->original_name }}"
                                data-applicant="{{ $applicant->user->name ?? 'Applicant name missing' }}">
                                <i class="bi bi-x-circle"></i> Reject document
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Reject Modal --}}
        @include('admission.documents._reject-modal')
    </div>

    {{-- COUNSELLING LOG TAB --}}
    <div class="tab-pane fade" id="counselling">
        <div class="alert alert-light border small">
            <strong>Counselling tab:</strong> log the outcome, next follow-up date, and useful notes so the next staff member understands the conversation history.
        </div>
        <div class="row g-4">
            <div class="col-md-7">
                <h6 class="fw-semibold mb-3">Interaction History</h6>
                @if($applicant->counsellingLogs->isEmpty())
                    <div class="border rounded bg-light-subtle p-3 text-muted small">
                        <div class="fw-semibold text-dark mb-1">No counselling interactions are logged yet</div>
                        <div>Log the first call, email, WhatsApp, or walk-in outcome so the next staff member knows the applicant's interest, objection, and follow-up date.</div>
                    </div>
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
                                        <span>By: {{ $log->loggedBy->name ?? 'Staff user not recorded' }}</span>
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
                                <select aria-label="Interaction type" name="interaction_type" class="form-select form-select-sm" required>
                                    <option value="call">Call</option>
                                    <option value="email">Email</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="walk_in">Walk-in</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Outcome</label>
                                <select aria-label="Interaction outcome" name="outcome" class="form-select form-select-sm" required>
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
                                <textarea aria-label="Interaction notes" name="notes" class="form-control form-control-sm" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Next Follow-up Date</label>
                                <input aria-label="Next follow-up date" type="date" name="next_followup_date" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Duration (minutes)</label>
                                <input aria-label="Duration in minutes" type="number" name="duration_minutes" class="form-control form-control-sm" min="1" placeholder="e.g. 15">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Log Interaction</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="assignment">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <div class="fw-semibold">Assignment Timeline</div>
                <div class="small text-muted">Shows who owned the applicant, who delegated it, and why the handoff happened.</div>
            </div>
            <div class="list-group list-group-flush">
                @forelse($applicant->assignmentEvents as $event)
                    <div class="list-group-item">
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $event->mode)) }} to {{ $event->toUser?->name ?? 'Unassigned' }}</div>
                        <div class="small text-muted">By {{ $event->assignedBy?->name ?? 'System' }} {{ $event->created_at?->diffForHumans() }} {{ $event->reason ? '- ' . $event->reason : '' }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No assignment events yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- NOTES TAB --}}
    <div class="tab-pane fade" id="notes">
        <div class="row g-4">
            <div class="col-md-8">
                @if($applicant->teamNotes->isEmpty())
                    <div class="border rounded bg-light-subtle p-3 text-muted small">
                        <div class="fw-semibold text-dark mb-1">No internal team notes are recorded yet</div>
                        <div>Add a concise staff-only note for exceptions, manager guidance, payment/document context, or a decision reason before changing sensitive statuses.</div>
                    </div>
                @else
                    @foreach($applicant->teamNotes->sortByDesc('created_at') as $note)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold small">{{ $note->user->name ?? 'Staff user not recorded' }}</span>
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
                            <textarea aria-label="Internal team note" name="note" class="form-control form-control-sm mb-2" rows="4" required placeholder="Internal note..."></textarea>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Add Note</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Scholarship Awards --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-award me-2"></i>Scholarship Awards</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#awardScholarshipForm">
                <i class="bi bi-plus-lg me-1"></i>Award Scholarship
            </button>
        </div>
    </div>

    {{-- Award form (collapsible) --}}
    <div class="collapse" id="awardScholarshipForm">
        <div class="card-body border-bottom bg-light">
            <form action="{{ route('admission.applicants.scholarships.store', $applicant) }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="scheme_id" class="form-label fw-semibold">Scholarship Scheme <span class="text-danger">*</span></label>
                        <select name="scheme_id" id="scheme_id" class="form-select @error('scheme_id') is-invalid @enderror"
                                onchange="prefillAmount(this)" required>
                            <option value="">Select Scheme</option>
                            @foreach(\App\Models\ScholarshipScheme::where('is_active', true)
                                ->where(function($q) use ($applicant) {
                                    $q->whereNull('program_id')->orWhere('program_id', $applicant->program_id);
                                })->orderBy('name')->get() as $scheme)
                                <option value="{{ $scheme->id }}"
                                        data-max="{{ $scheme->max_amount }}"
                                        data-seats="{{ $scheme->seatsRemaining() }}">
                                    {{ $scheme->name }}
                                    ({{ $scheme->type_label }})
                                    - Max Rs. {{ number_format($scheme->max_amount, 0) }}
                                    @if($scheme->available_seats) - {{ $scheme->seatsRemaining() }} seats left @endif
                                </option>
                            @endforeach
                        </select>
                        @error('scheme_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="awarded_amount" class="form-label fw-semibold">Award Amount (Rs.) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" name="awarded_amount" id="awarded_amount"
                                   class="form-control @error('awarded_amount') is-invalid @enderror"
                                   min="0" step="100" required>
                        </div>
                        @error('awarded_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="scholarship_notes" class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" id="scholarship_notes"
                               class="form-control @error('notes') is-invalid @enderror"
                               placeholder="Optional notes">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-award me-1"></i>Award Scholarship
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing awards list --}}
    <div class="card-body">
        @php $awardsList = $applicant->scholarships()->with('scheme', 'awardedBy')->latest()->get(); @endphp

        @if($awardsList->isEmpty())
            <p class="text-muted mb-0 small">No scholarships awarded yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Scheme</th>
                            <th scope="col" class="text-end">Amount (Rs.)</th>
                            <th scope="col">Status</th>
                            <th scope="col">Awarded By</th>
                            <th scope="col">Awarded On</th>
                            <th scope="col" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($awardsList as $award)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $award->scheme->name }}</div>
                                <div class="small text-muted font-monospace">{{ $award->scheme->scheme_code }}</div>
                            </td>
                            <td class="text-end fw-bold text-success">Rs. {{ number_format($award->awarded_amount, 2) }}</td>
                            <td><span class="{{ $award->status_badge }}">{{ ucfirst($award->status) }}</span></td>
                            <td class="small">{{ $award->awardedBy->name ?? 'Staff user not recorded' }}</td>
                            <td class="small text-muted">{{ $award->awarded_at?->format('d M Y') }}</td>
                            <td>
                                @if($award->status === 'awarded')
                                <form action="{{ route('admission.scholarships.destroy', $award) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Cancel this scholarship award for {{ addslashes($applicant->user->name ?? 'this applicant') }}? Confirm fee-demand impact, disbursement status, award audit trail, and applicant communication before cancellation.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Cancel scholarship award">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- P5-5: Approval Timeline Card -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Approval Workflow</h5>
    </div>
    <div class="card-body">
        @php
            $approvals = $applicant->approvalWorkflows()->orderBy('created_at')->get();
            $roles = ['hod' => 'HOD Approval', 'dean_academics' => 'Dean Clearance', 'program_chair' => 'Program Chair Sign-off'];
        @endphp

        @if($approvals->isEmpty())
            <p class="text-muted mb-0">No approval workflow initiated yet.</p>
        @else
            <div class="timeline">
                @foreach($approvals as $index => $approval)
                    <div class="timeline-item mb-4 pb-4 @if(!$loop->last) border-bottom @endif">
                        <div class="d-flex gap-3">
                            <div class="timeline-marker">
                                @if($approval->status === 'approved')
                                    <span class="badge bg-success rounded-circle p-2">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                @elseif($approval->status === 'rejected')
                                    <span class="badge bg-danger rounded-circle p-2">
                                        <i class="bi bi-x-lg"></i>
                                    </span>
                                @else
                                    <span class="badge bg-warning rounded-circle p-2">
                                        <i class="bi bi-hourglass-split"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="timeline-content flex-grow-1">
                                <h6 class="mb-1 fw-semibold">{{ $roles[$approval->approver_role] ?? ucfirst($approval->approver_role) }}</h6>
                                <p class="text-muted small mb-2">
                                    Status: <span class="{{ $approval->status_badge }}">{{ $approval->status_label }}</span>
                                </p>
                                @if($approval->approver)
                                    <p class="text-muted small mb-2">
                                        Approver: <strong>{{ $approval->approver->name }}</strong>
                                    </p>
                                @endif
                                @if($approval->approved_at)
                                    <p class="text-muted small mb-2">
                                        {{ ucfirst($approval->status) }} on: <strong>{{ $approval->approved_at->format('d M Y, h:i A') }}</strong>
                                    </p>
                                @endif
                                @if($approval->remarks)
                                    <div class="alert alert-light small mb-0 mt-2">
                                        <strong>Remarks:</strong> {{ $approval->remarks }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <style>
                .timeline { list-style: none; padding: 0; }
                .timeline-item { position: relative; }
                .timeline-marker { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; flex-shrink: 0; }
                .timeline-content { padding-top: 4px; }
            </style>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-command me-2"></i>v0.03 Operating Timeline</h5>
        <div class="small text-muted mt-1">Read communications, calls, journey version, and quality flags together before changing status or enrollment readiness.</div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <h6>Communications</h6>
                @forelse($applicant->communicationLogs()->limit(5)->get() as $log)
                    <div class="border rounded p-2 mb-2 small">{{ strtoupper($log->channel) }} - {{ $log->status }}<div class="text-muted">{{ Str::limit($log->body, 80) }}</div></div>
                @empty
                    <p class="text-muted small">No communication history.</p>
                @endforelse
            </div>
            <div class="col-md-4">
                <h6>Calls</h6>
                @forelse($applicant->callLogs()->limit(5)->get() as $call)
                    <div class="border rounded p-2 mb-2 small">{{ ucfirst(str_replace('_', ' ', $call->disposition)) }}<div class="text-muted">{{ $call->notes }}</div></div>
                @empty
                    <p class="text-muted small">No calls logged.</p>
                @endforelse
            </div>
            <div class="col-md-4">
                <h6>Journey And Quality</h6>
                <div class="small mb-2">Journey version: {{ $applicant->journeyVersion?->version ?? 'Not assigned' }}</div>
                @forelse($applicant->dataQualityFlags()->where('status', 'open')->limit(5)->get() as $flag)
                    <div class="badge bg-warning text-dark me-1 mb-1">{{ $flag->flag_type }}</div>
                @empty
                    <p class="text-muted small">No open quality flags.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Wire up reject modal from show page
const rejectModalEl = document.getElementById('rejectModal');
if (rejectModalEl) {
    rejectModalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('rejectDocId').value  = btn.dataset.docId;
        document.getElementById('rejectDocName').textContent   = btn.dataset.docName;
        document.getElementById('rejectApplicant').textContent = btn.dataset.applicant;
        document.getElementById('rejectForm').action = '/admission/documents/' + btn.dataset.docId + '/reject';
    });
}
</script>
@endpush
@endsection
