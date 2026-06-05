@extends('emails.layout')

@section('content')
<h2>Action Required: Document Re-upload Needed</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>We have reviewed your submitted documents and found that one of them requires attention.</p>

<div class="alert-danger">
    <p><strong>Document:</strong> {{ $document->requiredDocument?->name ?? $documentName ?? 'Document' }}</p>
    <p><strong>Reason for Rejection:</strong> {{ $document->rejection_reason ?? $rejectionReason ?? 'Does not meet requirements.' }}</p>
</div>

<p>Please log in to the applicant portal and re-upload the document at your earliest convenience. Delay in uploading may affect your application timeline.</p>

<p>If you have questions about why your document was rejected or what is required, please contact the admissions office.</p>

<p>Best regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
