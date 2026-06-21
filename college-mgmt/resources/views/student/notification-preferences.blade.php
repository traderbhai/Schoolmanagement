@extends('layouts.student')

@section('title', 'Notification Settings')
@section('page-title', 'Notification Settings')

@section('content')
<div class="container-fluid py-3" style="max-width:980px">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Communication Preferences</div>
                <h5 class="fw-bold mb-1">Choose the email updates you want to receive</h5>
                <p class="text-muted mb-0">
                    These settings control email alerts only. Your in-app notification inbox still shows official messages,
                    notice updates, result alerts, and payment confirmations sent by the institute.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-bell me-1"></i>Open Inbox
                </a>
                <a href="{{ route('student.notices') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-megaphone me-1"></i>Notice Board
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('student.notifications.update') }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="fw-semibold">Email Categories</div>
                <div class="text-muted small">Turn off optional email categories you do not want in your mailbox.</div>
            </div>
            <div class="list-group list-group-flush">
                @foreach([
                    'email_application_updates' => ['Admission and application updates', 'Application status, selection steps, offer letters, and enrollment messages.'],
                    'email_payment_updates' => ['Payment confirmations and reminders', 'Fee payment confirmations, pending dues, receipt updates, and payment reminders.'],
                    'email_result_published' => ['Exam result publication', 'Alerts when official results or academic outcome updates are published.'],
                    'email_notices' => ['Notices and announcements', 'Institute notices, academic announcements, and student-facing circulars.'],
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
                    Urgent official updates may still appear in your portal inbox even if an email category is disabled.
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i>Save Preferences
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
