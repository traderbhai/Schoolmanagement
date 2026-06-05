@extends('emails.layout')

@section('content')
<h2>Application Status Update</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>Thank you for your interest in {{ env('INSTITUTE_NAME', config('app.name')) }} and for taking the time to apply.</p>
<p>We regret to inform you that after careful consideration, we are unable to offer you admission at this time for the program you applied to.</p>

<div class="info-box">
    <p><strong>Application Number:</strong> {{ $applicant->application_number }}</p>
    <p><strong>Program:</strong> {{ $applicant->program?->name ?? 'N/A' }}</p>
    <p><strong>Status:</strong> Not Selected</p>
</div>

<p>This decision was made after a thorough review of all applications received and we had a large number of highly qualified candidates. We encourage you to explore other opportunities and not be discouraged.</p>

<p>We wish you the very best in your academic pursuits and future endeavors.</p>

<p>Warm regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
