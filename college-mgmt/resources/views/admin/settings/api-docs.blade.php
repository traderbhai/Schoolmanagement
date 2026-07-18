@extends('layouts.admin')
@section('title','API Documentation')
@section('page-title','REST API Reference')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
    <li class="breadcrumb-item active">API Docs</li>
@endsection
@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-braces me-2 text-primary"></i>API Endpoints</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Method</th>
                                <th scope="col">Endpoint</th>
                                <th scope="col">Auth</th>
                                <th scope="col">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><span class="badge bg-success">POST</span></td><td><code>/api/v1/login</code></td><td><span class="badge bg-secondary">No</span></td><td>Obtain Bearer token</td></tr>
                            <tr><td><span class="badge bg-danger">POST</span></td><td><code>/api/v1/logout</code></td><td><span class="badge bg-primary">Yes</span></td><td>Revoke current token</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/me</code></td><td><span class="badge bg-primary">Yes</span></td><td>Authenticated user info</td></tr>
                            <tr class="table-light"><td colspan="4" class="fw-semibold text-muted small py-2 px-3">Notices</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/notices</code></td><td><span class="badge bg-primary">Yes</span></td><td>Paginated active notices</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/notices/{id}</code></td><td><span class="badge bg-primary">Yes</span></td><td>Single notice details</td></tr>
                            <tr class="table-light"><td colspan="4" class="fw-semibold text-muted small py-2 px-3">Student (role: student)</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/student/profile</code></td><td><span class="badge bg-primary">Yes</span></td><td>Student profile details</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/student/attendance</code></td><td><span class="badge bg-primary">Yes</span></td><td>Attendance summary and records</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/student/results</code></td><td><span class="badge bg-primary">Yes</span></td><td>Exam results</td></tr>
                            <tr><td><span class="badge bg-primary">GET</span></td><td><code>/api/v1/student/fees</code></td><td><span class="badge bg-primary">Yes</span></td><td>Fee summary and payment history</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 mt-4" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-code-square me-2 text-success"></i>Example: Login</h5>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light rounded p-3 mb-0" style="font-size:.82rem"><code>curl -X POST {{ url('/api/v1/login') }} \
  -H "Content-Type: application/json" \
  -d '{"email":"student@demo.edu","password":"password123"}'

# Response:
{
  "token": "1|abc123...",
  "user": {
    "id": 5,
    "name": "Arjun Kapoor",
    "email": "arjun.k@demo.edu",
    "role": "student"
  }
}</code></pre>
            </div>
        </div>

        <div class="card border-0 mt-4" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-code-square me-2 text-primary"></i>Example: Authenticated Request</h5>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light rounded p-3 mb-0" style="font-size:.82rem"><code>curl {{ url('/api/v1/student/profile') }} \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"</code></pre>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Base URL</h6>
            </div>
            <div class="card-body">
                <code class="d-block bg-light rounded p-2">{{ url('/api/v1') }}</code>
            </div>
        </div>

        <div class="card border-0 mt-3" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-warning"></i>Authentication</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">This API uses secure Bearer token authentication.</p>
                <ol class="small ps-3 mb-0">
                    <li class="mb-1">POST to <code>/api/v1/login</code> with email and password</li>
                    <li class="mb-1">Receive a Bearer token in the response</li>
                    <li>Include <code>Authorization: Bearer &lt;token&gt;</code> header on all authenticated requests</li>
                </ol>
            </div>
        </div>

        <div class="card border-0 mt-3" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-check me-2 text-success"></i>Demo Credentials</h6>
            </div>
            <div class="card-body small">
                <div class="mb-2 p-2 rounded bg-light">
                    <div class="fw-semibold">Admin</div>
                    <div class="text-muted">admin@demo.edu / password123</div>
                </div>
                <div class="mb-2 p-2 rounded bg-light">
                    <div class="fw-semibold">Teacher</div>
                    <div class="text-muted">anjali@demo.edu / password123</div>
                </div>
                <div class="p-2 rounded bg-light">
                    <div class="fw-semibold">Student</div>
                    <div class="text-muted">arjun.k@demo.edu / password123</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
