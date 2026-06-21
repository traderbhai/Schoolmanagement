@extends('layouts.applicant')

@section('title', 'Notification Settings')
@section('page-title', 'Notification Settings')

@section('content')
<div class="container-fluid py-3" style="max-width:980px">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Applicant Communication</div>
                <h5 class="fw-bold mb-1">Choose the email updates you want during admission</h5>
                <p class="text-muted mb-0">
                    These settings control email alerts only. Your applicant portal still shows official status,
                    document, fee, selection, offer, and enrollment messages even when a category is disabled.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('applicant.status') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-list-check me-1"></i>Status Tracker
                </a>
                <a href="{{ route('applicant.checklist') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-ui-checks-grid me-1"></i>Checklist
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('applicant.notifications.update') }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="fw-semibold">Email Categories</div>
                <div class="text-muted small">Use these options to reduce email volume without losing portal visibility.</div>
            </div>
            <div class="list-group list-group-flush">
                @foreach([
                    'email_application_updates' => ['Application status updates', 'Submission, review, shortlist, selection, offer, and enrollment updates.'],
                    'email_payment_updates' => ['Payment confirmations and reminders', 'Registration fee, admission installment, verification, receipt, and payment reminder messages.'],
                    'email_result_published' => ['Exam and selection results', 'Selection-session, assessment, and result-publication updates.'],
                    'email_notices' => ['Notices and announcements', 'Admission notices, instructions, deadline reminders, and institute announcements.'],
                ] as $key => [$label, $description])
                <label class="list-group-item d-flex align-items-start gap-3 py-3 cursor-pointer" for="{{ $key }}">
                    <input
                        class="form-check-input mt-1"
                        type="checkbox"
                        name="{{ $key }}"
                        value="1"
                        id="{{ $key }}"
                        {{ $pref->$key ? 'checked' : '' }}
                    >
                    <span>
                        <span class="fw-semibold d-block">{{ $label }}</span>
                        <span class="text-muted small">{{ $description }}</span>
                    </span>
                </label>
                @endforeach
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small">
                    Critical admission deadlines and official status changes remain visible in your applicant portal.
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i>Save Preferences
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
