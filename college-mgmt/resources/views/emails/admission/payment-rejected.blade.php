@extends('emails.layout')

@section('content')
<h2>Payment Verification Failed — Action Required</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>We were unable to verify your recent payment submission. Please review the details below:</p>

<div class="alert-danger">
    <p><strong>Installment:</strong> {{ $payment->installment?->name ?? 'Admission Fee' }}</p>
    <p><strong>Amount Submitted:</strong> ₹{{ number_format($payment->amount_paid, 2) }}</p>
    @if($payment->verification_notes)
    <p><strong>Reason:</strong> {{ $payment->verification_notes }}</p>
    @endif
</div>

<p>Please log in to the applicant portal and re-submit your payment proof or contact the accounts office for assistance.</p>
<p>Failure to resolve this may delay your admission process.</p>

<p>Best regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
