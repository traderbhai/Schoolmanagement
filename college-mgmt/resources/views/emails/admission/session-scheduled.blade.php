@extends('emails.layout')

@section('content')
<h2>Your Selection Session is Scheduled</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>Your selection session has been scheduled. Please find the details below:</p>

<div class="info-box">
    <p><strong>Session Type:</strong> {{ $session->step?->type ? strtoupper($session->step->type) : 'Selection Session' }}</p>
    <p><strong>Date:</strong> {{ $session->scheduled_date?->format('l, d M Y') ?? 'N/A' }}</p>
    <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($session->start_time)->format('h:i A') ?? 'N/A' }}</p>
    @if($session->venue)
    <p><strong>Venue:</strong> {{ $session->venue }}</p>
    @endif
    <p><strong>Session Name:</strong> {{ $session->session_name }}</p>
</div>

@if($session->instructions)
<div class="alert-warning">
    <p><strong>Instructions:</strong><br>{{ $session->instructions }}</p>
</div>
@endif

<p>Please arrive 15 minutes before the scheduled time. Bring your original documents and a printed copy of your application.</p>

<p>Best of luck!<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
