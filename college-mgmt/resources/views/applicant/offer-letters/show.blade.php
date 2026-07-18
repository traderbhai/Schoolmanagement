@extends('layouts.applicant')

@section('title', 'Offer Letter')
@section('page-title', 'Offer Letter')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>{{ $offerLetter->offer_number }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('applicant.offer-letters.pdf', $offerLetter) }}" class="btn btn-secondary">Download PDF</a>
            <a href="{{ route('applicant.offer-letters.index') }}" class="btn btn-primary">Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Status</h6>
                    <span class="{{ $offerLetter->status_badge }}">{{ $offerLetter->status_label }}</span>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Program</h6>
                    <p class="mb-0"><strong>{{ $offerLetter->program->name }}</strong></p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Batch</h6>
                    <p class="mb-0">{{ $offerLetter->batch->name }}</p>
                </div>
            </div>

            @if($offerLetter->isPending())
                @php
                    $daysLeft = now()->diffInDays($offerLetter->acceptance_deadline);
                    $alertClass = $daysLeft <= 3 ? 'danger' : 'warning';
                @endphp
                <div class="card mb-3 border-{{ $alertClass }}">
                    <div class="card-body">
                        <h6 class="card-title text-muted mb-2">Days Remaining</h6>
                        <p class="mb-0 text-{{ $alertClass }}">
                            <strong>{{ $daysLeft }}</strong> day{{ $daysLeft !== 1 ? 's' : '' }}
                        </p>
                        <small class="text-muted">Until {{ $offerLetter->acceptance_deadline->format('d M Y') }}</small>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body p-5">
                    <div class="text-center mb-5">
                        <h2 class="text-primary">OFFER LETTER</h2>
                        <p class="text-muted">Admission to Academic Program</p>
                    </div>

                    <p class="mb-4">Dear <strong>{{ auth()->user()->name }}</strong>,</p>

                    <p class="mb-4">
                        Congratulations! We are pleased to inform you that you have been selected for admission to the <strong>{{ $offerLetter->program->name }}</strong> program at our institution.
                    </p>

                    <div class="border rounded p-3 mb-4" style="background-color: #f8f9fa;">
                        <h5 class="text-primary mb-3">Offer Details</h5>
                        <table class="w-100" style="font-size: 14px;">
                            <tr>
                                <td style="padding: 8px 0;"><strong>Offer Number:</strong></td>
                                <td style="padding: 8px 0;">{{ $offerLetter->offer_number }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Program:</strong></td>
                                <td style="padding: 8px 0;">{{ $offerLetter->program->name }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Batch:</strong></td>
                                <td style="padding: 8px 0;">{{ $offerLetter->batch->name }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Application Number:</strong></td>
                                <td style="padding: 8px 0;">{{ auth()->user()->applicant->application_number }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Date of Offer:</strong></td>
                                <td style="padding: 8px 0;">{{ $offerLetter->issued_at->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Acceptance Deadline:</strong></td>
                                <td style="padding: 8px 0; color: #dc3545;"><strong>{{ $offerLetter->acceptance_deadline->format('d M Y') }}</strong></td>
                            </tr>
                        </table>
                    </div>

                    <p class="mb-3">This offer is contingent upon:</p>
                    <ul class="mb-4">
                        <li>Verification of all submitted documents and academic credentials</li>
                        <li>Successful completion of all prescribed qualifying examinations</li>
                        <li>Compliance with all admission terms and conditions</li>
                        <li>Satisfactory conduct and background clearance</li>
                        <li>Payment of admission fees as per the fee schedule</li>
                    </ul>

                    <div class="border-top pt-4">
                        <p class="mb-3"><strong>Action Required:</strong></p>
                        <p>You must accept or decline this offer by <strong>{{ $offerLetter->acceptance_deadline->format('d M Y') }}</strong>. Please use the buttons below to respond to this offer. Failure to respond by the deadline may result in forfeiture of your admission.</p>
                    </div>
                </div>
            </div>

            @if($offerLetter->isPending() && ! $locked)
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Your Response</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-success btn-lg w-100" data-bs-toggle="modal" data-bs-target="#acceptModal">
                                    Accept Offer
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-danger btn-lg w-100" data-bs-toggle="modal" data-bs-target="#declineModal">
                                    Decline Offer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Accept Modal -->
                <div class="modal fade" id="acceptModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header border-success">
                                <h5 class="modal-title">Confirm Acceptance</h5>
                                <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to accept this offer for <strong>{{ $offerLetter->program->name }}</strong>?</p>
                                <div class="alert alert-info">
                                    <small>
                                        By accepting this offer, you are committing to join this program. You can proceed with fee payment and other enrollment procedures.
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form action="{{ route('applicant.offer-letters.accept', $offerLetter) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success">Yes, Accept</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decline Modal -->
                <div class="modal fade" id="declineModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header border-danger">
                                <h5 class="modal-title">Confirm Decline</h5>
                                <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="{{ route('applicant.offer-letters.decline', $offerLetter) }}" method="POST">
                                @csrf
                                <div class="modal-body">
                                    <p>Are you sure you want to decline this offer?</p>
                                    <div class="mb-3">
                                        <label class="form-label">Reason for Declining (Optional)</label>
                                        <textarea aria-label="Offer decline feedback" name="reason" class="form-control" rows="4" placeholder="Help us improve by sharing your feedback..."></textarea>
                                    </div>
                                    <div class="alert alert-danger">
                                        <small>
                                            <strong>Important:</strong> Declining this offer cannot be reversed. If there are other offers in your portal, you may be considered for other programs.
                                        </small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Yes, Decline</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @elseif($offerLetter->isPending() && $locked)
                <div class="alert alert-warning">
                    <h5>Offer Response Closed</h5>
                    <p class="mb-0">Your application is already in a final admission state, so this offer can no longer be accepted or declined online.</p>
                </div>
            @elseif($offerLetter->isAccepted())
                <div class="alert alert-success">
                    <h5>Offer Accepted</h5>
                    <p>Accepted on <strong>{{ $offerLetter->accepted_at->format('d M Y H:i') }}</strong></p>
                    <p>You can now proceed with your enrollment and fee payment. Check your status page for next steps.</p>
                </div>
            @elseif($offerLetter->isDeclined())
                <div class="alert alert-danger">
                    <h5>Offer Declined</h5>
                    <p>Declined on <strong>{{ $offerLetter->declined_at->format('d M Y H:i') }}</strong></p>
                    @if($offerLetter->declined_reason)
                        <p><strong>Reason:</strong> {{ $offerLetter->declined_reason }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
