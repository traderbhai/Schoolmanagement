@extends('emails.layout')

@section('content')
<h2>Follow-up Due Today</h2>
<p>Dear {{ $counsellor?->name ?? 'Counsellor' }},</p>
<p>You have a follow-up scheduled for today with the following applicant:</p>

<div class="info-box">
    <p><strong>Applicant:</strong> {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'N/A') }}</p>
    <p><strong>Application Number:</strong> {{ $applicant->application_number }}</p>
    <p><strong>Program:</strong> {{ $applicant->program?->name ?? 'N/A' }}</p>
    <p><strong>Follow-up Date:</strong> {{ isset($log) && $log->next_followup_date ? \Carbon\Carbon::parse($log->next_followup_date)->format('d M Y') : today()->format('d M Y') }}</p>
    @if(isset($log) && $log->notes)
    <p><strong>Previous Notes:</strong> {{ $log->notes }}</p>
    @endif
    @if(isset($log) && $log->outcome)
    <p><strong>Previous Outcome:</strong> {{ ucfirst($log->outcome) }}</p>
    @endif
</div>

<p>Please log in to the admission portal to record this interaction and update the follow-up status.</p>

<p>— {{ env('INSTITUTE_NAME', config('app.name')) }} Notification System</p>
@endsection
