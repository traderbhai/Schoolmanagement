@extends('emails.layout')

@section('content')
<h2>Application Received!</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>Thank you for submitting your application. We have successfully received your application and it is now under review.</p>

<div class="info-box">
    <p><strong>Application Number:</strong> {{ $applicant->application_number ?? 'Application number pending' }}</p>
    <p><strong>Program Applied:</strong> {{ $applicant->program?->name ?? 'Program to be confirmed' }}</p>
    <p><strong>Submitted On:</strong> {{ $applicant->applied_at?->format('d M Y, h:i A') ?? now()->format('d M Y') }}</p>
    <p><strong>Status:</strong> Submitted</p>
</div>

<p>Our admissions team will review your application and update you on the next steps. You can track the status of your application through the applicant portal.</p>

<p>If you have any questions, please feel free to reach out to the admissions office.</p>

<p>Best regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
