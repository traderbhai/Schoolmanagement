@extends('emails.layout')

@section('content')
<h2>Fee Payment Receipt</h2>
<p>Dear {{ $student->user?->name ?? 'Student' }},</p>
<p>Your fee payment has been recorded successfully. Here is your receipt:</p>

<div class="info-box">
    <p><strong>Receipt Number:</strong> {{ $feePayment->receipt_number ?? $feePayment->id ?? 'Receipt number pending' }}</p>
    <p><strong>Amount Paid:</strong> Rs. {{ number_format($feePayment->amount ?? 0, 2) }}</p>
    <p><strong>Payment Date:</strong> {{ isset($feePayment->paid_at) ? \Carbon\Carbon::parse($feePayment->paid_at)->format('d M Y') : now()->format('d M Y') }}</p>
    <p><strong>Payment Mode:</strong> {{ ucfirst($feePayment->payment_mode ?? 'Payment mode pending') }}</p>
</div>

<p>Please retain this email as your fee payment confirmation. For a detailed receipt, log in to the student portal.</p>

<p>Best regards,<br><strong>Accounts Office</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
