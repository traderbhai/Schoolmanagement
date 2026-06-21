@extends('emails.layout')

@section('content')
<h2>Fee Payment Reminder</h2>
<p>Dear {{ $student->user?->name ?? 'Student' }},</p>
<p>This is a reminder that your fee payment is due soon. Please find the details below:</p>

<div class="alert-warning">
    <p><strong>Amount Due:</strong> Rs. {{ number_format($feeStructure->amount ?? 0, 2) }}</p>
    <p><strong>Due Date:</strong> {{ isset($feeStructure->due_date) ? \Carbon\Carbon::parse($feeStructure->due_date)->format('d M Y') : 'Due date not published' }}</p>
    @if(isset($daysUntilDue))
    <p><strong>Days Remaining:</strong> {{ $daysUntilDue > 0 ? $daysUntilDue . ' day(s)' : 'Overdue' }}</p>
    @endif
</div>

<p>Please log in to the student portal and complete your payment to avoid any late fees or academic restrictions.</p>

<p>Best regards,<br><strong>Accounts Office</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
