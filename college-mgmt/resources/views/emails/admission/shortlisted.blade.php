@extends('emails.layout')

@section('content')
<h2>Congratulations! You Have Been Shortlisted</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>We are pleased to inform you that your application has been reviewed and you have been <strong>shortlisted</strong> for the next stage of the admission process.</p>

<div class="info-box">
    <p><strong>Application Number:</strong> {{ $applicant->application_number }}</p>
    <p><strong>Program:</strong> {{ $applicant->program?->name ?? 'N/A' }}</p>
    <p><strong>Status:</strong> Shortlisted</p>
</div>

<div class="alert-success">
    <p><strong>Next Steps:</strong> You will be scheduled for a selection session (GD/PI/WAT) shortly. Please keep an eye on your email for further instructions.</p>
</div>

<p>Ensure that all your documents are uploaded and verified in the applicant portal to avoid any delays.</p>

<p>Best regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
