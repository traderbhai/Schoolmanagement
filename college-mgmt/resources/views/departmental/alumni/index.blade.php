@extends('layouts.admin')
@section('title', 'Alumni Database')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Alumni Database</h4>
            <span class="text-muted small">Track graduates, career outcomes, and verified student networking profiles.</span>
        </div>
        <a href="{{ route('cmc.alumni.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Alumni
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Alumni Priority</div>
                <h5 class="fw-bold mb-1">{{ $alumniPriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $alumniPriority['body'] }}</p>
            </div>
            <a href="{{ $alumniPriority['route'] }}" class="btn btn-sm {{ $alumniPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary' }}">
                <i class="bi bi-arrow-right-circle me-1"></i>{{ $alumniPriority['action'] }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-primary">{{ $totalAlumni }}</div><div class="text-muted small">Total Profiles</div></div></div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-success">{{ $verifiedCount }}</div><div class="text-muted small">Verified</div></div></div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-warning">{{ $unverifiedCount }}</div><div class="text-muted small">Needs Verification</div></div></div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-info">{{ $recentGraduatesMissingProfiles }}</div><div class="text-muted small">Graduates Missing Profiles</div></div></div>
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold text-muted">Graduation Year</label>
                    <select name="graduation_year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}" @selected(request('graduation_year')==$y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold text-muted">Verification</label>
                    <select name="verified" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" @selected(request('verified')==='1')>Verified</option>
                        <option value="0" @selected(request('verified')==='0')>Unverified</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm me-1">Filter</button>
                    <a href="{{ route('cmc.alumni.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Employer</th>
                        <th>Role</th>
                        <th>Location</th>
                        <th>Salary</th>
                        <th>Verification</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alumni as $a)
                    <tr>
                        <td class="fw-semibold">{{ $a->student->user->name ?? '-' }}</td>
                        <td class="small text-muted">{{ $a->student->program->name ?? '-' }}</td>
                        <td>{{ $a->graduation_year }}</td>
                        <td>{{ $a->current_employer ?? '-' }}</td>
                        <td class="small">{{ $a->current_role ?? '-' }}</td>
                        <td class="small text-muted">{{ implode(', ', array_filter([$a->city, $a->country])) ?: '-' }}</td>
                        <td>{{ $a->current_salary ? 'Rs. ' . number_format($a->current_salary, 0) : '-' }}</td>
                        <td>
                            @if($a->is_verified)
                                <span class="badge bg-success"><i class="bi bi-check me-1"></i>Verified</span>
                            @else
                                <form method="POST" action="{{ route('cmc.alumni.verify', $a) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2">Verify</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @if($a->linkedin_url)
                            <a href="{{ $a->linkedin_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm py-0 px-2"><i class="bi bi-linkedin"></i></a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No alumni records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($alumni->hasPages())
        <div class="card-footer bg-transparent">{{ $alumni->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
