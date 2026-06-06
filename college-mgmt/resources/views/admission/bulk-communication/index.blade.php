@extends('layouts.admin')
@section('title', 'Bulk Communication')
@section('page-title', 'Bulk Communication')

@section('content')
<div class="container-fluid p-4">

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Panel 1: Filter & Preview --}}
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
                                <option value="">— Any Status —</option>
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
                                <option value="">— Any Program —</option>
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
                                <option value="">— Any Batch —</option>
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

        {{-- Panel 2: Compose Message --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="bi bi-envelope me-2"></i>Compose Message
                </div>
                <div class="card-body">

                    @if(!$preview)
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>Use the filters on the left to select recipients first.
                        </div>

                    @elseif($applicants->isEmpty())
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-circle me-2"></i>No applicants match the selected filters.
                        </div>

                    @else
                        {{-- Recipient Summary --}}
                        <div class="mb-3">
                            <span class="badge bg-info fs-6 px-3 py-2">
                                <i class="bi bi-people me-1"></i>{{ $applicants->count() }} recipient(s) selected
                            </span>
                        </div>

                        {{-- Scrollable Recipient List --}}
                        <div class="border rounded mb-4" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-group list-group-flush">
                                @foreach($applicants as $applicant)
                                    <li class="list-group-item py-2 small">
                                        <span class="fw-semibold">{{ $applicant->user?->name ?? '—' }}</span>
                                        <span class="text-muted ms-2">#{{ $applicant->application_number }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Compose Form --}}
                        <form method="POST" action="{{ route('admission.bulk-communication.send') }}" id="bulk-compose-form">
                            @csrf

                            {{-- Hidden applicant IDs --}}
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
                                          placeholder="Type your message here…">{{ old('message') }}</textarea>
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
                                This will notify <strong>{{ $applicants->count() }}</strong> candidate(s) via their portal and (optionally) email.
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
                                var counter  = document.getElementById('char-count');
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
