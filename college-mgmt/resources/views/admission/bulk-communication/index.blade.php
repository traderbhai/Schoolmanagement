@extends('layouts.admin')
@section('title', 'Bulk Communication')
@section('page-title', 'Bulk Communication')

@section('content')
@php
    $activeAudienceFilters = collect([
        'Status' => request('filter_status') ? ucwords(str_replace('_', ' ', request('filter_status'))) : null,
        'Program' => optional($programs->firstWhere('id', (int) request('filter_program_id')))->name,
        'Batch' => optional($batches->firstWhere('id', (int) request('filter_batch_id')))->name,
    ])->filter();
@endphp
<div class="container-fluid p-4">
    <x-ui.page-header
        title="Bulk Communication"
        subtitle="Filter a real audience, preview recipients, write the message, and send only after consent and duplicate checks are clear."
    />

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12">
            <div class="alert alert-info border-0 shadow-sm small mb-0">
                <div class="fw-semibold mb-1">Bulk-send workflow</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border">1. Filter audience</span>
                    <span class="badge text-bg-light border">2. Preview recipients</span>
                    <span class="badge text-bg-light border">3. Confirm consent and duplicates</span>
                    <span class="badge text-bg-light border">4. Compose message</span>
                    <span class="badge text-bg-light border">5. Send and monitor delivery</span>
                </div>
                <div class="text-muted mt-2">For high-risk sends, review Communication Safety first so opt-outs, quiet hours, blocked recipients, and duplicate recipients are visible before sending.</div>
                <div class="mt-2">
                    <a href="{{ route('admission.communication-safety.index') }}" class="btn btn-sm btn-outline-primary">Open Communication Safety</a>
                    <a href="{{ route('admission.communication.index') }}" class="btn btn-sm btn-outline-secondary">Open Communication Hub</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="bi bi-funnel me-2"></i>Filter Applicants
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admission.bulk-communication.index') }}">
                        <div class="mb-3">
                            <label for="filter_status" class="form-label small fw-semibold">Status</label>
                            <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                <option value="">Any Status</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ request('filter_status') === $status ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="filter_program_id" class="form-label small fw-semibold">Program</label>
                            <select name="filter_program_id" id="filter_program_id" class="form-select form-select-sm">
                                <option value="">Any Program</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ request('filter_program_id') == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="filter_batch_id" class="form-label small fw-semibold">Batch</label>
                            <select name="filter_batch_id" id="filter_batch_id" class="form-select form-select-sm">
                                <option value="">Any Batch</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}" {{ request('filter_batch_id') == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-search me-1"></i>Preview Matches
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="bi bi-envelope me-2"></i>Compose Message
                </div>
                <div class="card-body">
                    @if(!$preview)
                        <div class="alert alert-info mb-0">
                            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-2"></i>No audience preview yet</div>
                            <div class="small">Use the filters on the left to create a recipient preview. The send form stays hidden until staff can see the matching applicants and confirm the audience source.</div>
                        </div>
                    @elseif($applicants->isEmpty())
                        <div class="alert alert-warning mb-0">
                            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle me-2"></i>No applicants match the selected filters</div>
                            <div class="small text-muted mb-2">
                                Active filters:
                                @forelse($activeAudienceFilters as $label => $value)
                                    <span class="badge text-bg-light border">{{ $label }}: {{ $value }}</span>
                                @empty
                                    <span>All visible applicants.</span>
                                @endforelse
                            </div>
                            <div class="small">
                                The preview uses the current status, program, and batch filters. Clear filters or adjust one filter at a time before composing a bulk message.
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="{{ route('admission.bulk-communication.index') }}" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                                <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <span class="badge bg-info fs-6 px-3 py-2">
                                <i class="bi bi-people me-1"></i>{{ $applicants->count() }} recipient(s) selected
                            </span>
                            <div class="small text-muted mt-2">This preview is the source of truth for the send. If the count looks wrong, change filters before composing.</div>
                            <div class="small mt-2">
                                <span class="fw-semibold">Audience filter summary:</span>
                                @forelse($activeAudienceFilters as $label => $value)
                                    <span class="badge text-bg-light border">{{ $label }}: {{ $value }}</span>
                                @empty
                                    <span class="text-muted">All visible applicants.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="border rounded mb-4" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-group list-group-flush">
                                @foreach($applicants as $applicant)
                                    <li class="list-group-item py-2 small">
                                        <div class="d-flex flex-wrap justify-content-between gap-2">
                                            <span>
                                                <span class="fw-semibold">{{ $applicant->user?->name ?? 'Applicant name missing' }}</span>
                                                <span class="text-muted ms-2">#{{ $applicant->application_number ?? 'Application number missing' }}</span>
                                            </span>
                                            <span class="text-muted">{{ ucwords(str_replace('_', ' ', $applicant->status ?? 'status missing')) }}</span>
                                        </div>
                                        <div class="text-muted">
                                            Program: {{ $applicant->program?->name ?? 'Program not assigned' }}
                                            <span class="mx-1">|</span>
                                            Email: {{ $applicant->user?->email ?? 'Email missing' }}
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <form method="POST" action="{{ route('admission.bulk-communication.send') }}" id="bulk-compose-form">
                            @csrf

                            @foreach($applicants as $applicant)
                                <input type="hidden" name="applicant_ids[]" value="{{ $applicant->id }}">
                            @endforeach

                            <div class="mb-3">
                                <label for="subject" class="form-label fw-semibold small">Subject <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="subject"
                                       id="subject"
                                       class="form-control @error('subject') is-invalid @enderror"
                                       maxlength="255"
                                       required
                                       value="{{ old('subject') }}"
                                       placeholder="Enter message subject">
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label fw-semibold small">
                                    Message <span class="text-danger">*</span>
                                    <span class="text-muted fw-normal ms-2" id="char-count">0 / 2000</span>
                                </label>
                                <textarea name="message"
                                          id="message"
                                          class="form-control @error('message') is-invalid @enderror"
                                          rows="6"
                                          maxlength="2000"
                                          required
                                          placeholder="Type your message here...">{{ old('message') }}</textarea>
                                @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="send_email"
                                       id="send_email"
                                       value="1"
                                       {{ old('send_email') ? 'checked' : '' }}>
                                <label class="form-check-label small" for="send_email">
                                    <i class="bi bi-envelope-at me-1"></i>Also send email
                                </label>
                            </div>

                            <div class="alert alert-secondary small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                This will notify <strong>{{ $applicants->count() }}</strong> candidate(s) via their portal and optionally email. Use the safety preview for consent, quiet-hour, and duplicate-recipient checks before sending a large audience.
                                <div class="mt-2">
                                    <a href="{{ route('admission.communication-safety.index') }}" class="alert-link">Open Communication Safety before high-volume send</a>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Send Message
                                </button>
                                <a href="{{ route('admission.bulk-communication.index') }}" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>

                        <script>
                            (function () {
                                var textarea = document.getElementById('message');
                                var counter = document.getElementById('char-count');
                                function updateCount() {
                                    counter.textContent = textarea.value.length + ' / 2000';
                                }
                                textarea.addEventListener('input', updateCount);
                                updateCount();
                            })();
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
