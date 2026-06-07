@extends('emails.layout')

@section('content')
<h2>New Notice Posted</h2>
<p>Dear Student,</p>
<p>A new notice has been published. Please read it carefully:</p>

<div class="info-box">
    <p><strong>Title:</strong> {{ $notice->title ?? 'N/A' }}</p>
    <p><strong>Published On:</strong> {{ $notice->published_at ? \Carbon\Carbon::parse($notice->published_at)->format('d M Y, h:i A') : now()->format('d M Y') }}</p>
</div>

<div style="background:#f9f9f9; border:1px solid #e5e7eb; padding:16px; border-radius:4px; margin:16px 0;">
    {!! nl2br(e($notice->content ?? '')) !!}
</div>

<p>For more details, please log in to the portal.</p>

<p>Best regards,<br><strong>Administration</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
