@extends('emails.layout')

@section('content')
<h2>You Have Been Selected!</h2>
<p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
<p>We are delighted to inform you that you have been <strong>selected</strong> for admission to {{ env('INSTITUTE_NAME', config('app.name')) }}.</p>

<div class="info-box">
    <p><strong>Application Number:</strong> {{ $applicant->application_number }}</p>
    <p><strong>Program:</strong> {{ $applicant->program?->name ?? 'N/A' }}</p>
    @if(isset($meritRank) && $meritRank)
    <p><strong>Merit Rank:</strong> {{ $meritRank }}</p>
    @endif
    <p><strong>Status:</strong> Selected</p>
</div>

<div class="alert-success">
    <p><strong>Next Steps:</strong> Please complete the fee payment process and document submission through the applicant portal to confirm your admission.</p>
</div>

<p>We look forward to welcoming you to our institution. Please complete the remaining formalities at the earliest to secure your seat.</p>

<p>Congratulations once again!<br><strong>Admissions Team</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
