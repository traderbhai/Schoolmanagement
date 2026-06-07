@extends('emails.layout')

@section('content')
<h2>Attendance Alert</h2>
<p>Dear {{ $student->user?->name ?? 'Student' }},</p>
<p>We would like to bring to your attention that your attendance in the following subject has dropped below the required minimum of 75%:</p>

<div class="alert-danger">
    <p><strong>Subject:</strong> {{ $subject->name ?? 'N/A' }}</p>
    <p><strong>Current Attendance:</strong> {{ $attendance_percentage ?? 0 }}%</p>
    <p><strong>Minimum Required:</strong> 75%</p>
</div>

<p>Low attendance may result in debarment from examinations. Please ensure regular attendance going forward and speak with your faculty coordinator if you are facing any difficulties.</p>

<p>Best regards,<br><strong>Academic Office</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
