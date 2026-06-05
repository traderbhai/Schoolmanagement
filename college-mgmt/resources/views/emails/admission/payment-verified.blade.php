@extends('emails.layout')

@section('content')
<h2>Payment Confirmed</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>Your payment has been successfully verified. Here are the details:</p>

<div class="info-box">
    <p><strong>Installment:</strong> {{ $payment->installment?->name ?? 'Admission Fee' }}</p>
    <p><strong>Amount Paid:</strong> ₹{{ number_format($payment->amount_paid, 2) }}</p>
    @if($payment->transaction_reference)
    <p><strong>Transaction Reference:</strong> {{ $payment->transaction_reference }}</p>
    @endif
    <p><strong>Payment Date:</strong> {{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</p>
    <p><strong>Status:</strong> Verified</p>
</div>

<div class="alert-success">
    <p>Your payment has been confirmed. Please retain this email as your payment confirmation.</p>
</div>

<p>Thank you for completing the payment. The next steps in your enrollment process will be communicated to you shortly.</p>

<p>Best regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
