@extends('emails.layout')

@section('content')
@php
    $status = $documentRequest->status;
    $documentName = \App\Models\DocumentRequest::typeLabel($documentRequest->document_type);
    $statusLabel = $status === 'approved' ? 'processing' : $status;
@endphp

<h2>Your document request is {{ $statusLabel }}</h2>
<p>Dear {{ $documentRequest->student?->user?->name ?? 'Student' }},</p>
<p>Your request for <strong>{{ $documentName }}</strong> has been updated by the college office.</p>

<div class="info-box">
    <p><strong>Document:</strong> {{ $documentName }}</p>
    <p><strong>Status:</strong> {{ ucfirst($statusLabel) }}</p>
    <p><strong>Purpose:</strong> {{ $documentRequest->purpose ?: 'Not specified' }}</p>
    @if($documentRequest->notes)
        <p><strong>Staff note:</strong> {{ $documentRequest->notes }}</p>
    @endif
</div>

@if($status === 'ready')
    <div class="alert-success">
        <p><strong>Your document is ready.</strong> Log in to the student portal and download it from Document Requests.</p>
    </div>
@elseif($status === 'rejected')
    <div class="alert-danger">
        <p><strong>Action may be required.</strong> Review the staff note and submit a fresh request if needed.</p>
    </div>
@elseif($status === 'approved')
    <div class="alert-warning">
        <p><strong>Your request is being processed.</strong> The office will upload the document once it is ready.</p>
    </div>
@endif

@if(isset($actionUrl))
<p style="text-align:center;">
    <a href="{{ $actionUrl }}" class="btn">Open Document Requests</a>
</p>
@endif

<p>Best regards,<br><strong>Student Services</strong><br>{{ env('INSTITUTE_NAME', config('app.name')) }}</p>
@endsection
