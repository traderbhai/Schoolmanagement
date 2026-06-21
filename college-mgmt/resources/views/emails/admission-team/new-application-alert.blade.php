@extends('emails.layout')

@section('content')
<h2>New Application Received</h2>
<p>A new application has been submitted and requires your attention.</p>

<div class="info-box">
    <p><strong>Applicant Name:</strong> {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant name not provided') }}</p>
    <p><strong>Application Number:</strong> {{ $applicant->application_number ?? 'Application number pending' }}</p>
    <p><strong>Program Applied:</strong> {{ $applicant->program?->name ?? 'Program to be confirmed' }}</p>
    <p><strong>Email:</strong> {{ $applicant->user?->email ?? 'Email not provided' }}</p>
    <p><strong>Submitted On:</strong> {{ $applicant->applied_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
</div>

<p>Please log in to the admission portal to review the application.</p>

<p>{{ env('INSTITUTE_NAME', config('app.name')) }} Notification System</p>
@endsection
