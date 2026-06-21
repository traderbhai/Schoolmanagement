@extends('emails.layout')

@section('content')
<h2>Welcome! Your Enrollment is Confirmed</h2>
<p>Dear {{ $enrollment->student?->user?->name ?? 'Student' }},</p>
<p>Congratulations! Your enrollment at <strong>{{ env('INSTITUTE_NAME', config('app.name')) }}</strong> has been successfully confirmed. Welcome to our institution!</p>

<div class="info-box">
    <p><strong>Enrollment Number:</strong> {{ $enrollment->enrollment_number ?? 'Enrollment number pending' }}</p>
    <p><strong>Roll Number:</strong> {{ $enrollment->roll_number ?? 'Roll number pending' }}</p>
    <p><strong>Program:</strong> {{ $enrollment->student?->program?->name ?? 'Program to be confirmed' }}</p>
    <p><strong>Batch:</strong> {{ $enrollment->student?->batch?->name ?? 'Batch to be confirmed' }}</p>
    <p><strong>Enrollment Date:</strong> {{ $enrollment->confirmed_at?->format('d M Y') ?? now()->format('d M Y') }}</p>
</div>

<div class="alert-success">
    <p><strong>Your student account is ready.</strong> You can log in using your registered email address. Your temporary password is your application number (please change it after your first login).</p>
</div>

@if(isset($loginUrl))
<p style="text-align:center;">
    <a href="{{ $loginUrl }}" class="btn">Login to Student Portal</a>
</p>
@endif

<p>Please report to the administration office with your original documents for verification if not already done. Your class schedule and further instructions will be shared through the student portal.</p>

<p>We wish you a wonderful academic journey ahead!</p>

<p>Warm regards,<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
