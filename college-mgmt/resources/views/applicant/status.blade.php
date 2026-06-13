@extends('layouts.applicant')
@section('title', 'Application Status')
@section('page-title', 'Status Tracker')

@section('content')
<div class="container-fluid p-4">

    <div class="row justify-content-center">
        <div class="col-md-8">
            @php
                $latestOffer = $applicant->offerLetters->sortByDesc('created_at')->first();
                $pendingOffer = $applicant->offerLetters->where('status', 'issued')->sortByDesc('created_at')->first();
                $pendingPayment = $applicant->payments->where('status', 'pending')->first();
                $verifiedPaymentCount = $applicant->payments->where('status', 'verified')->count();
                $pendingDocumentCount = $applicant->documents->where('status', 'pending')->count();
                $rejectedDocumentCount = $applicant->documents->where('status', 'rejected')->count();
                $enrollment = $applicant->enrollmentConfirmation;

                $nextStep = match ($applicant->status) {
                    'draft' => [
                        'tone' => 'primary',
                        'icon' => 'bi-pencil-square',
                        'title' => 'Complete your application',
                        'body' => $applicant->hasRegistrationFeePaid()
                            ? 'Review your form and uploaded documents, then submit your application for admission review.'
                            : 'Save your registration fee details, complete the form, upload documents, and submit the application.',
                        'route' => $applicant->hasRegistrationFeePaid() ? route('applicant.application.show') : route('applicant.registration-fee.show'),
                        'label' => $applicant->hasRegistrationFeePaid() ? 'Continue Application' : 'Submit Fee Details',
                    ],
                    'submitted', 'under_review' => [
                        'tone' => 'warning',
                        'icon' => 'bi-hourglass-split',
                        'title' => 'Admission team review is in progress',
                        'body' => $pendingDocumentCount > 0
                            ? 'Your documents are awaiting verification. Watch this page for shortlist and offer updates.'
                            : 'Your application is with the admission team. Watch this page for shortlist and offer updates.',
                        'route' => route('applicant.documents.index'),
                        'label' => 'Review Documents',
                    ],
                    'shortlisted' => [
                        'tone' => 'info',
                        'icon' => 'bi-cash-coin',
                        'title' => $pendingPayment ? 'Payment submitted for verification' : 'Submit admission fee details',
                        'body' => $pendingPayment
                            ? 'Your fee payment has been received and is awaiting verification by the accounts or admission team.'
                            : 'You are shortlisted. Submit the configured admission fee installment details to proceed toward selection.',
                        'route' => route('applicant.fees.index'),
                        'label' => $pendingPayment ? 'View Payment Status' : 'Submit Payment',
                    ],
                    'selected' => [
                        'tone' => 'success',
                        'icon' => 'bi-trophy',
                        'title' => $enrollment?->status === 'completed' ? 'Enrollment completed' : 'Complete your offer and enrollment steps',
                        'body' => $enrollment?->status === 'completed'
                            ? 'Your enrollment has been confirmed. Keep your enrollment number and roll number for academic onboarding.'
                            : ($pendingOffer ? 'Accept your issued offer letter, confirm fee status, and keep required documents ready for enrollment.' : 'Your admission is selected. Check offer letters, payment status, and document verification before enrollment.'),
                        'route' => $pendingOffer ? route('applicant.offer-letters.show', $pendingOffer) : route('applicant.offer-letters.index'),
                        'label' => $pendingOffer ? 'Review Offer Letter' : 'View Offer Letters',
                    ],
                    'rejected' => [
                        'tone' => 'danger',
                        'icon' => 'bi-x-circle',
                        'title' => 'Application closed',
                        'body' => 'This application was not selected. You can track future open intakes from the public application page.',
                        'route' => route('apply'),
                        'label' => 'View Open Intakes',
                    ],
                    'withdrawn' => [
                        'tone' => 'secondary',
                        'icon' => 'bi-dash-circle',
                        'title' => 'Application withdrawn',
                        'body' => 'This application has been withdrawn. Start a fresh application only when a new eligible intake is open.',
                        'route' => route('apply'),
                        'label' => 'View Open Intakes',
                    ],
                    default => [
                        'tone' => 'secondary',
                        'icon' => 'bi-info-circle',
                        'title' => 'Track your application',
                        'body' => 'Use this page to monitor admission updates and required actions.',
                        'route' => route('applicant.dashboard'),
                        'label' => 'Go to Dashboard',
                    ],
                };
            @endphp

            <div class="card mb-4">
                <div class="card-body text-center p-5">
                    <h4 class="fw-bold mb-1">{{ $applicant->application_number }}</h4>
                    <p class="text-muted mb-3">{{ $applicant->program->name }}{{ $applicant->batch ? ' - ' . $applicant->batch->name : '' }}</p>
                    <span class="{{ $applicant->status_badge }} fs-5 px-4 py-2">{{ $applicant->status_label }}</span>
                </div>
            </div>

            <div class="card border-{{ $nextStep['tone'] }} mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-start justify-content-between">
                        <div class="d-flex gap-3">
                            <div class="rounded-circle bg-{{ $nextStep['tone'] }} bg-opacity-10 text-{{ $nextStep['tone'] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                <i class="bi {{ $nextStep['icon'] }} fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">{{ $nextStep['title'] }}</h5>
                                <p class="text-muted mb-0">{{ $nextStep['body'] }}</p>
                            </div>
                        </div>
                        <a href="{{ $nextStep['route'] }}" class="btn btn-{{ $nextStep['tone'] }} flex-shrink-0">
                            {{ $nextStep['label'] }}
                        </a>
                    </div>
                    @if($applicant->status === 'selected' && $enrollment?->status === 'completed')
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <div class="small text-muted">Enrollment Number</div>
                                <div class="fw-semibold">{{ $enrollment->enrollment_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Roll Number</div>
                                <div class="fw-semibold">{{ $enrollment->roll_number ?? '-' }}</div>
                            </div>
                        </div>
                    @elseif($applicant->status === 'selected' && $verifiedPaymentCount > 0)
                        <div class="small text-success mt-3">
                            <i class="bi bi-check-circle me-1"></i>{{ $verifiedPaymentCount }} payment {{ $verifiedPaymentCount === 1 ? 'entry is' : 'entries are' }} verified.
                            @if($rejectedDocumentCount > 0)
                                <span class="text-danger ms-2">{{ $rejectedDocumentCount }} document {{ $rejectedDocumentCount === 1 ? 'needs' : 'need' }} resubmission.</span>
                            @endif
                        </div>
                    @endif
                    @if($latestOffer && $latestOffer->status !== 'issued')
                        <div class="small text-muted mt-3">
                            Latest offer status: {{ $latestOffer->status_label }}.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Application Timeline</h5></div>
                <div class="card-body p-4">
                    @php
                        $steps = [
                            ['key' => 'draft',        'label' => 'Application Started',   'icon' => 'bi-file-earmark-plus'],
                            ['key' => 'submitted',    'label' => 'Application Submitted', 'icon' => 'bi-send-fill'],
                            ['key' => 'under_review', 'label' => 'Under Review',          'icon' => 'bi-search'],
                            ['key' => 'shortlisted',  'label' => 'Shortlisted',           'icon' => 'bi-star-fill'],
                            ['key' => 'selected',     'label' => 'Selected',              'icon' => 'bi-trophy-fill'],
                        ];
                        $statusOrder = ['draft' => 0, 'submitted' => 1, 'under_review' => 2, 'shortlisted' => 3, 'selected' => 4, 'rejected' => 2, 'withdrawn' => 1];
                        $currentOrder = $statusOrder[$applicant->status] ?? 0;
                    @endphp

                    @if(in_array($applicant->status, ['rejected', 'withdrawn']))
                    <div class="alert alert-{{ $applicant->status === 'rejected' ? 'danger' : 'dark' }} mb-4">
                        <i class="bi bi-{{ $applicant->status === 'rejected' ? 'x-circle' : 'dash-circle' }}-fill me-2"></i>
                        Your application was <strong>{{ $applicant->status_label }}</strong>.
                        @if($applicant->reviewed_at)
                            on {{ $applicant->reviewed_at->format('d M Y') }}
                        @endif
                    </div>
                    @endif

                    <div class="position-relative">
                        @foreach($steps as $i => $step)
                            @php
                                $stepOrder = $statusOrder[$step['key']] ?? $i;
                                $isCompleted = $currentOrder > $stepOrder;
                                $isCurrent = $currentOrder === $stepOrder && !in_array($applicant->status, ['rejected','withdrawn']);
                                $isPending = $currentOrder < $stepOrder;
                            @endphp
                            <div class="d-flex align-items-start mb-{{ $i < count($steps) - 1 ? '4' : '0' }} position-relative">
                                <div class="me-3 text-center" style="min-width: 40px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto
                                        {{ $isCompleted ? 'bg-success text-white' : ($isCurrent ? 'bg-primary text-white' : 'bg-light text-muted border') }}"
                                        style="width: 40px; height: 40px;">
                                        <i class="bi {{ $isCompleted ? 'bi-check-lg' : $step['icon'] }}"></i>
                                    </div>
                                    @if($i < count($steps) - 1)
                                        <div class="border-start border-2 {{ $isCompleted ? 'border-success' : 'border-light' }} mx-auto" style="height: 30px; width: 2px; margin-top: 4px;"></div>
                                    @endif
                                </div>
                                <div class="pt-1">
                                    <p class="fw-semibold mb-0 {{ $isPending ? 'text-muted' : '' }}">{{ $step['label'] }}</p>
                                    @if($isCurrent)
                                        <span class="badge bg-primary-subtle text-primary small">Current Status</span>
                                    @elseif($isCompleted)
                                        <span class="text-success small"><i class="bi bi-check me-1"></i>Completed</span>
                                    @else
                                        <span class="text-muted small">Pending</span>
                                    @endif

                                    @if($step['key'] === 'submitted' && $applicant->applied_at)
                                        <p class="text-muted small mb-0 mt-1">{{ $applicant->applied_at?->format('d M Y, h:i A') ?? '-' }}</p>
                                    @elseif($step['key'] === 'shortlisted' && $applicant->reviewed_at && in_array($applicant->status, ['shortlisted', 'selected']))
                                        <p class="text-muted small mb-0 mt-1">{{ $applicant->reviewed_at?->format('d M Y') ?? '-' }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
