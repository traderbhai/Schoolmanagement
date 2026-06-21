@extends('emails.layout')

@section('content')
<h2>Exam Results Published</h2>
<p>Dear {{ $student->user?->name ?? 'Student' }},</p>
<p>Your results for the following exam have been published:</p>

<div class="info-box">
    <p><strong>Exam:</strong> {{ $exam->name ?? 'Exam name pending' }}</p>
    @if(isset($result))
    <p><strong>Marks Obtained:</strong> {{ $result->marks_obtained ?? 'Marks pending' }} / {{ $result->total_marks ?? 'Total marks pending' }}</p>
    <p><strong>Grade:</strong> {{ $result->grade ?? 'Grade pending' }}</p>
    <p><strong>Status:</strong> {{ ($result->marks_obtained ?? 0) >= ($result->passing_marks ?? 0) ? 'Pass' : 'Fail' }}</p>
    @else
    <p><strong>Marks Obtained:</strong> Marks pending / Total marks pending</p>
    <p><strong>Grade:</strong> Grade pending</p>
    @endif
</div>

<p>Please log in to the student portal to view your detailed result and grade card.</p>

<p>Best regards,<br><strong>Examination Cell</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
