@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Offer Letter #{{ $offerLetter->offer_number }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admission.offer-letters.export', $offerLetter) }}" class="btn btn-secondary">Download PDF</a>
            <a href="{{ route('admission.offer-letters.index', $offerLetter->program) }}" class="btn btn-primary">Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title text-muted">Status</h6>
                    <span class="{{ $offerLetter->status_badge }}">{{ $offerLetter->status_label }}</span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title text-muted">Program</h6>
                    <p class="mb-0"><strong>{{ $offerLetter->program->name }}</strong></p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title text-muted">Batch</h6>
                    <p class="mb-0">{{ $offerLetter->batch->name }}</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title text-muted">Issued By</h6>
                    <p class="mb-0">{{ $offerLetter->issuedBy?->name ?? 'N/A' }}</p>
                    <small class="text-muted">{{ $offerLetter->issued_at->format('d M Y H:i') }}</small>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Applicant Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> {{ $offerLetter->applicant->user->name }}</p>
                            <p><strong>Email:</strong> {{ $offerLetter->applicant->user->email }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Application #:</strong> {{ $offerLetter->applicant->application_number }}</p>
                            <p><strong>Applied At:</strong> {{ $offerLetter->applicant->applied_at->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Offer Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Offer Number:</strong> {{ $offerLetter->offer_number }}</p>
                            <p><strong>Acceptance Deadline:</strong> {{ $offerLetter->acceptance_deadline->format('d M Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Issued At:</strong> {{ $offerLetter->issued_at->format('d M Y H:i') }}</p>
                            @if($offerLetter->accepted_at)
                                <p><strong>Accepted At:</strong> <span class="text-success">{{ $offerLetter->accepted_at->format('d M Y H:i') }}</span></p>
                            @elseif($offerLetter->declined_at)
                                <p><strong>Declined At:</strong> <span class="text-danger">{{ $offerLetter->declined_at->format('d M Y H:i') }}</span></p>
                                @if($offerLetter->declined_reason)
                                    <p><strong>Reason:</strong> {{ $offerLetter->declined_reason }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($offerLetter->isPending())
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Admin Actions</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admission.offer-letters.accept', $offerLetter) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success">Mark as Accepted</button>
                        </form>
                        <form action="{{ route('admission.offer-letters.decline', $offerLetter) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger">Mark as Declined</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
