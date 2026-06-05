<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply — EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="text-center mb-5">
        <i class="bi bi-mortarboard-fill fs-1 text-primary"></i>
        <h1 class="fw-bold mt-2">Apply for Admission</h1>
        <p class="text-muted">Select a program to begin your application</p>
        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Already applied? Login</a>
    </div>

    <div class="row g-4 justify-content-center">
        @forelse($programs as $program)
        <div class="col-md-4">
            <div class="card h-100 shadow-sm hover-lift">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary-subtle text-primary">{{ strtoupper($program->system_type) }}</span>
                        <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $program->duration_years }} Years</span>
                    </div>
                    <h5 class="card-title fw-bold">{{ $program->name }}</h5>
                    <p class="text-muted small">{{ $program->abbreviation }}</p>
                    @if($program->description)
                        <p class="card-text small text-muted">{{ Str::limit($program->description, 100) }}</p>
                    @endif
                    <div class="mt-auto pt-3">
                        <a href="{{ route('apply.program', $program) }}" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-2"></i>Apply Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center">
            <p class="text-muted">No programs available for application at this time.</p>
        </div>
        @endforelse
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
