<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="text-center mb-5">
        <i class="bi bi-mortarboard-fill fs-1 text-primary"></i>
        <h1 class="fw-bold mt-2">Apply for Admission</h1>
        <p class="text-muted mb-3">Choose an open intake to begin your application.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Already applied? Login</a>
            <a href="{{ route('public.status-tracker.index') }}" class="btn btn-sm btn-outline-primary">Track application</a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-warning d-flex align-items-start gap-2 mx-auto mb-4" style="max-width: 760px;">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <div class="row g-4 justify-content-center">
        @forelse($applicationWindows as $window)
            @php
                $program = $window->program;
                $remainingCapacity = $window->getRemainingCapacity();
            @endphp
            @if($program && $program->is_active)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm hover-lift border-0">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="mb-3 d-flex flex-wrap gap-2">
                                <span class="badge bg-success">Open now</span>
                                <span class="badge bg-primary-subtle text-primary">{{ strtoupper($program->system_type) }}</span>
                                <span class="badge bg-secondary-subtle text-secondary">{{ $program->duration_years }} Years</span>
                            </div>

                            <h5 class="card-title fw-bold">{{ $program->name }}</h5>
                            <p class="text-muted small mb-2">{{ $program->abbreviation }}</p>
                            @if($program->description)
                                <p class="card-text small text-muted">{{ Str::limit($program->description, 110) }}</p>
                            @endif

                            <div class="border rounded p-3 bg-light small mt-auto mb-3">
                                @if($window->batch)
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-collection text-primary"></i>
                                        <span><strong>Batch:</strong> {{ $window->batch->name }}</span>
                                    </div>
                                @endif
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    <span><strong>Deadline:</strong> {{ $window->closes_at->format('d M Y, h:i A') }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-people text-primary"></i>
                                    <span>
                                        <strong>Seats:</strong>
                                        @if(is_null($remainingCapacity))
                                            Open intake
                                        @else
                                            {{ $remainingCapacity }} remaining
                                        @endif
                                    </span>
                                </div>
                            </div>

                            <a href="{{ route('apply.program', $program) }}" class="btn btn-primary w-100">
                                <i class="bi bi-arrow-right-circle me-2"></i>Start Application
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm mx-auto" style="max-width: 680px;">
                    <div class="card-body text-center p-5">
                        <i class="bi bi-calendar-x fs-1 text-muted"></i>
                        <h5 class="fw-bold mt-3">No application intakes are open right now</h5>
                        <p class="text-muted mb-4">Please check back later or use the status tracker if you already applied.</p>
                        <a href="{{ route('public.status-tracker.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-search me-1"></i>Track existing application
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
