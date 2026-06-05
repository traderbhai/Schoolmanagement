@extends('emails.layout')

@section('content')
@if($recipientName)
<p>Dear {{ $recipientName }},</p>
@else
<p>Dear Student/Applicant,</p>
@endif

<div style="font-size:15px; line-height:1.8; color:#333;">
    {!! nl2br(e($body)) !!}
</div>

<p style="margin-top:24px;">Best regards,<br><strong>Administration</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
