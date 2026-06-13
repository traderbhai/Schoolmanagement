<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Application - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8faff; font-family: 'Inter', sans-serif; }
        .tracker-navbar {
            background: #312e81;
            padding: .85rem 1.5rem;
        }
        .tracker-navbar .brand {
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .tracker-navbar .brand:hover { color: #e0e7ff; }
        .tracker-navbar .back-link {
            color: rgba(255,255,255,.85);
            text-decoration: none;
            font-size: .87rem;
            font-weight: 500;
        }
        .tracker-navbar .back-link:hover { color: #fff; }
        .page-hero {
            background: linear-gradient(135deg, #312e81 0%, #4f46e5 100%);
            color: #fff;
            padding: 2.5rem 0 2rem;
            text-align: center;
        }
        .page-hero h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: .4rem; }
        .page-hero p  { font-size: .9rem; color: rgba(255,255,255,.8); margin: 0; }
        .search-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        .result-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        .timeline-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            flex-shrink: 0;
        }
        .timeline-connector {
            width: 2px;
            height: 28px;
            margin: 3px auto;
        }
        .site-footer {
            text-align: center;
            padding: 2rem 0;
            color: #94a3b8;
            font-size: .8rem;
        }
    </style>
</head>
<body>

{{-- Navbar --}}
<nav class="tracker-navbar d-flex align-items-center justify-content-between">
    <a href="{{ url('/') }}" class="brand">
        <i class="bi bi-mortarboard-fill"></i>
        {{ config('app.name') }}
    </a>
    <a href="{{ url('/') }}" class="back-link">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>
</nav>

{{-- Hero --}}
<div class="page-hero">
    <div class="container">
        <h1><i class="bi bi-search me-2"></i>Track Your Application</h1>
        <p>Enter your application number and registered email to check your admission status.</p>
    </div>
</div>

{{-- Main Content --}}
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 480px;">

            {{-- Search Card --}}
            <div class="search-card mb-4">
                <h5 class="fw-bold mb-1">Application Status Lookup</h5>
                <p class="text-muted small mb-4">Provide the details you used during registration.</p>

                <form method="POST" action="{{ route('public.status-tracker.track') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="application_number" class="form-label fw-semibold">Application Number</label>
                        <input
                            type="text"
                            class="form-control @error('application_number') is-invalid @enderror"
                            id="application_number"
                            name="application_number"
                            placeholder="APP-2024-00001"
                            value="{{ old('application_number') }}"
                            maxlength="50"
                            required
                        >
                        @error('application_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            value="{{ old('email') }}"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-search me-1"></i>Track Status
                    </button>
                </form>
            </div>

            {{-- Result Section --}}
            @if($searched)
                @if($applicant === null)
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>No application found.</strong><br>
                            No application found matching those details. Please check your application number and email.
                            <div class="mt-3 d-flex flex-wrap gap-2">
                                <a href="{{ route('apply') }}" class="btn btn-sm btn-outline-danger">Apply for an open intake</a>
                                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Applicant login</a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="result-card">
                        {{-- Applicant Info --}}
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div style="width:48px;height:48px;border-radius:50%;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-6">{{ $applicant->user->name }}</div>
                                    <div class="text-muted small">{{ $applicant->application_number }}</div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="bi bi-mortarboard text-primary"></i>
                                        <span>{{ $applicant->program->name }}</span>
                                    </div>
                                </div>
                                @if($applicant->batch)
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="bi bi-collection text-primary"></i>
                                        <span>{{ $applicant->batch->name }}</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Current Status Badge --}}
                        <div class="mb-4 text-center">
                            <p class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.06em;">Current Status</p>
                            @php
                                $statusColor = match($applicant->status) {
                                    'draft'        => '#6c757d',
                                    'submitted'    => '#0d6efd',
                                    'under_review' => '#fd7e14',
                                    'shortlisted'  => '#0dcaf0',
                                    'selected'     => '#198754',
                                    'rejected'     => '#dc3545',
                                    'withdrawn'    => '#6c757d',
                                    default        => '#6c757d',
                                };
                                $statusLabel = match($applicant->status) {
                                    'draft'        => 'Application Draft',
                                    'submitted'    => 'Submitted - Under Initial Review',
                                    'under_review' => 'Under Review by Admission Team',
                                    'shortlisted'  => 'Shortlisted for Interview',
                                    'selected'     => 'Selected - Congratulations!',
                                    'rejected'     => 'Not Selected',
                                    'withdrawn'    => 'Withdrawn',
                                    default        => ucfirst($applicant->status),
                                };
                                $nextAction = match($applicant->status) {
                                    'draft' => [
                                        'title' => 'Complete your application',
                                        'body' => 'Log in to the applicant portal, finish all required sections, upload documents, and submit before the intake deadline.',
                                        'label' => 'Continue application',
                                        'route' => route('login'),
                                        'tone' => 'primary',
                                    ],
                                    'submitted', 'under_review' => [
                                        'title' => 'Watch for admission updates',
                                        'body' => 'Your application is with the admission team. Keep your email and phone reachable for document, interview, or counselling requests.',
                                        'label' => 'Applicant login',
                                        'route' => route('login'),
                                        'tone' => 'warning',
                                    ],
                                    'shortlisted' => [
                                        'title' => 'Prepare for selection steps',
                                        'body' => 'You have moved ahead in the process. Check the applicant portal and your email for session, interview, document, or fee instructions.',
                                        'label' => 'View applicant portal',
                                        'route' => route('login'),
                                        'tone' => 'info',
                                    ],
                                    'selected' => [
                                        'title' => 'Complete your admission offer tasks',
                                        'body' => 'Log in to review offer letters, respond before the deadline, submit required payments, and complete enrollment requirements.',
                                        'label' => 'Review offer tasks',
                                        'route' => route('login'),
                                        'tone' => 'success',
                                    ],
                                    'rejected' => [
                                        'title' => 'Contact admissions for guidance',
                                        'body' => 'This application was not selected at this stage. Contact the admission office if you need counselling or information about future intakes.',
                                        'label' => 'View open intakes',
                                        'route' => route('apply'),
                                        'tone' => 'danger',
                                    ],
                                    'withdrawn' => [
                                        'title' => 'Application withdrawn',
                                        'body' => 'This application is no longer active. You can review open intakes if you want to apply again.',
                                        'label' => 'View open intakes',
                                        'route' => route('apply'),
                                        'tone' => 'secondary',
                                    ],
                                    default => [
                                        'title' => 'Check your applicant portal',
                                        'body' => 'Log in to view the latest required actions and messages from the admission team.',
                                        'label' => 'Applicant login',
                                        'route' => route('login'),
                                        'tone' => 'primary',
                                    ],
                                };
                            @endphp
                            <div style="background: {{ $statusColor }}; color: white; padding: 12px 20px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; display: inline-block;">
                                {{ $statusLabel }}
                            </div>
                        </div>

                        <div class="alert alert-{{ $nextAction['tone'] }} d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-arrow-right-circle fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="fw-bold">{{ $nextAction['title'] }}</div>
                                <div class="small mb-3">{{ $nextAction['body'] }}</div>
                                <a href="{{ $nextAction['route'] }}" class="btn btn-sm btn-{{ $nextAction['tone'] === 'warning' ? 'dark' : $nextAction['tone'] }}">
                                    {{ $nextAction['label'] }}
                                </a>
                            </div>
                        </div>

                        {{-- Timeline --}}
                        <div class="mb-4">
                            <p class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.06em;">Application Journey</p>

                            @php
                                $timelineSteps = [
                                    ['key' => 'draft',        'label' => 'Application Started'],
                                    ['key' => 'submitted',    'label' => 'Submitted'],
                                    ['key' => 'under_review', 'label' => 'Under Review'],
                                    ['key' => 'shortlisted',  'label' => 'Shortlisted'],
                                    ['key' => 'selected',     'label' => 'Selected'],
                                ];
                                $statusOrder  = [
                                    'draft'        => 0,
                                    'submitted'    => 1,
                                    'under_review' => 2,
                                    'shortlisted'  => 3,
                                    'selected'     => 4,
                                    'rejected'     => 2,
                                    'withdrawn'    => 1,
                                ];
                                $currentOrder = $statusOrder[$applicant->status] ?? 0;
                                $isTerminal   = in_array($applicant->status, ['rejected', 'withdrawn']);
                            @endphp

                            @foreach($timelineSteps as $i => $step)
                                @php
                                    $stepOrder   = $statusOrder[$step['key']] ?? $i;
                                    $isCompleted = $currentOrder > $stepOrder;
                                    $isCurrent   = $currentOrder === $stepOrder && !$isTerminal;
                                    $isFuture    = $currentOrder < $stepOrder;
                                @endphp

                                <div class="d-flex align-items-start">
                                    <div class="d-flex flex-column align-items-center me-3">
                                        <div class="timeline-dot
                                            @if($isCompleted) bg-success text-white
                                            @elseif($isCurrent) text-white
                                            @else bg-light border text-muted
                                            @endif"
                                            @if($isCurrent) style="background:{{ $statusColor }};" @endif
                                        >
                                            @if($isCompleted)
                                                <i class="bi bi-check-lg"></i>
                                            @elseif($isCurrent)
                                                <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                            @else
                                                <i class="bi bi-circle" style="font-size:.55rem;"></i>
                                            @endif
                                        </div>
                                        @if($i < count($timelineSteps) - 1)
                                            <div class="timeline-connector {{ $isCompleted ? 'bg-success' : 'bg-light border-start' }}"></div>
                                        @endif
                                    </div>
                                    <div class="pb-{{ $i < count($timelineSteps) - 1 ? '1' : '0' }} pt-1">
                                        <p class="fw-semibold mb-0 small {{ $isFuture ? 'text-muted' : '' }}">
                                            {{ $step['label'] }}
                                        </p>
                                        <p class="mb-2 small
                                            @if($isCompleted) text-success
                                            @elseif($isCurrent) fw-semibold
                                            @else text-muted
                                            @endif
                                        ">
                                            @if($isCompleted)
                                                <i class="bi bi-check me-1"></i>Completed
                                            @elseif($isCurrent)
                                                Current Step
                                            @else
                                                Pending
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach

                            @if($isTerminal)
                                <div class="alert alert-{{ $applicant->status === 'rejected' ? 'danger' : 'secondary' }} mt-3 mb-0 py-2 px-3 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    @if($applicant->status === 'rejected')
                                        This application was not selected at this stage.
                                    @else
                                        This application has been withdrawn.
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Applied On --}}
                        <div class="d-flex align-items-center gap-2 text-muted small mb-4 pb-3 border-bottom">
                            <i class="bi bi-calendar3 text-primary"></i>
                            <span>Applied on: <strong class="text-dark">{{ $applicant->applied_at?->format('d M Y') ?? 'Not yet submitted' }}</strong></span>
                        </div>

                        {{-- Support Note --}}
                        <div class="text-center small text-muted">
                            <i class="bi bi-envelope me-1"></i>
                            For support, contact the <strong>Admission Office</strong>.
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>

{{-- Footer --}}
<footer class="site-footer">
    <div class="container">
        Powered by {{ config('app.name') }}
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
