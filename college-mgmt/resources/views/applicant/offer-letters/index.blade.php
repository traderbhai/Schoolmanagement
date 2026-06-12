@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>My Offer Letters</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('applicant.dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Expiry alerts for offers expiring within 3 days --}}
    @foreach($offerLetters->where('status', 'issued') as $ol)
        @if($ol->acceptance_deadline && \Carbon\Carbon::parse($ol->acceptance_deadline)->diffInDays(now()) <= 3)
        <div class="alert alert-warning">
            <i class="bi bi-alarm me-2"></i>
            Your offer for <strong>{{ $ol->program->name }}</strong> expires on
            <strong>{{ \Carbon\Carbon::parse($ol->acceptance_deadline)->format('d M Y') }}</strong>.
            Please accept or decline soon!
        </div>
        @endif
    @endforeach

    @if($offerLetters->isEmpty())
        <div class="alert alert-info">
            <h5>No Offer Letters Yet</h5>
            <p>You will see your offer letters here once the admission team has issued them.</p>
        </div>
    @else
        <div class="row">
            @foreach($offerLetters as $offer)
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">{{ $offer->program->name }}</h5>
                                <span class="{{ $offer->status_badge }}">{{ $offer->status_label }}</span>
                            </div>
                        </div>

                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Offer Number:</strong> {{ $offer->offer_number }}
                            </p>
                            <p class="mb-2">
                                <strong>Batch:</strong> {{ $offer->batch->name }}
                            </p>
                            <p class="mb-3">
                                <strong>Acceptance Deadline:</strong>
                                <span class="text-danger">{{ $offer->acceptance_deadline->format('d M Y') }}</span>
                            </p>

                            @if($offer->isPending())
                                @php
                                    $daysLeft = now()->diffInDays($offer->acceptance_deadline);
                                @endphp
                                <div class="alert alert-warning mb-3">
                                    <small>
                                        <strong>⏰ {{ $daysLeft }}</strong> day{{ $daysLeft !== 1 ? 's' : '' }} left to respond
                                    </small>
                                </div>
                            @elseif($offer->isAccepted())
                                <div class="alert alert-success mb-3">
                                    <small>
                                        ✓ Accepted on {{ $offer->accepted_at?->format('d M Y') ?? '—' }}
                                    </small>
                                </div>
                            @elseif($offer->isDeclined())
                                <div class="alert alert-danger mb-3">
                                    <small>
                                        ✗ Declined on {{ $offer->declined_at?->format('d M Y') ?? '—' }}
                                        @if($offer->declined_reason)
                                            <br>Reason: {{ $offer->declined_reason }}
                                        @endif
                                    </small>
                                </div>
                            @endif

                            <div class="d-flex gap-2">
                                <a href="{{ route('applicant.offer-letters.show', $offer) }}" class="btn btn-sm btn-primary flex-fill">
                                    View Details
                                </a>
                                <a href="{{ route('applicant.offer-letters.pdf', $offer) }}" class="btn btn-sm btn-secondary flex-fill">
                                    Download PDF
                                </a>
                            </div>

                            @if($offer->isPending())
                                <div class="d-flex gap-2 mt-3">
                                    <button class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#acceptModal{{ $offer->id }}">
                                        Accept Offer
                                    </button>
                                    <button class="btn btn-sm btn-danger flex-fill" data-bs-toggle="modal" data-bs-target="#declineModal{{ $offer->id }}">
                                        Decline Offer
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Accept Modal -->
                @if($offer->isPending())
                    <div class="modal fade" id="acceptModal{{ $offer->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Acceptance</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to accept this offer for <strong>{{ $offer->program->name }}</strong>?</p>
                                    <p class="text-muted small">Once accepted, this action cannot be reversed.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <form action="{{ route('applicant.offer-letters.accept', $offer) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success">Accept Offer</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decline Modal -->
                    <div class="modal fade" id="declineModal{{ $offer->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Decline</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="{{ route('applicant.offer-letters.decline', $offer) }}" method="POST">
                                    @csrf
                                    <div class="modal-body">
                                        <p>Are you sure you want to decline this offer for <strong>{{ $offer->program->name }}</strong>?</p>
                                        <div class="mb-3">
                                            <label class="form-label">Reason (Optional)</label>
                                            <textarea name="reason" class="form-control" rows="3" placeholder="Please tell us why you're declining..."></textarea>
                                        </div>
                                        <p class="text-muted small">Once declined, you cannot revert this action.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Decline Offer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

<style>
    .card {
        border-left: 4px solid #007bff;
    }
    .card.accepted {
        border-left-color: #28a745;
    }
    .card.declined {
        border-left-color: #dc3545;
    }
</style>
@endsection
