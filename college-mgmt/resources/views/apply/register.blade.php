<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for {{ $program->name }} - EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="text-center mb-4">
                <i class="bi bi-mortarboard-fill fs-1 text-primary"></i>
                <h2 class="fw-bold mt-2">Create Your Application</h2>
                <p class="text-muted">Program: <strong>{{ $program->name }}</strong></p>
            </div>

            <div class="alert alert-info d-flex align-items-start gap-2">
                <i class="bi bi-calendar-check mt-1"></i>
                <div>
                    <div class="fw-semibold">Applications are open for this intake.</div>
                    <div class="small">
                        Deadline: {{ $window->closes_at->format('d M Y, h:i A') }}
                        @if($window->batch)
                            <span class="mx-1">|</span> Batch: {{ $window->batch->name }}
                        @endif
                        @if(! is_null($window->getRemainingCapacity()))
                            <span class="mx-1">|</span> {{ $window->getRemainingCapacity() }} seats remaining
                        @endif
                    </div>
                </div>
            </div>

            @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('apply.program.register', $program) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input aria-label="Full name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Enter your full name" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input aria-label="Email address" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="you@example.com" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <input aria-label="Mobile number" type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" placeholder="+91 98765 43210" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input aria-label="Password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Minimum 8 characters" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input aria-label="Confirm password" type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-person-plus me-2"></i>Create Application
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('apply') }}" class="text-muted small">&larr; Choose a different program</a>
                <span class="text-muted small mx-2">|</span>
                <a href="{{ route('login') }}" class="text-muted small">Already have an account? Login</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
