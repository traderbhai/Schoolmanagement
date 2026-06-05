@extends('layouts.admin')
@section('title', 'Companies')
@section('page-title', 'Companies')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Companies</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Companies</h5>
        <div class="text-muted" style="font-size:.82rem">Manage recruitment partners and companies</div>
    </div>
    <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Company
    </a>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-building text-primary fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Total Companies</div>
                    <div class="fw-bold fs-4">{{ $totalCompanies }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-check-circle text-success fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Active Companies</div>
                    <div class="fw-bold fs-4">{{ $activeCompanies }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-briefcase text-info fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Total Drives</div>
                    <div class="fw-bold fs-4">{{ $totalDrives }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Search --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, industry, email…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                @if(request('search'))
                    <a href="{{ route('admin.companies.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Company</th>
                        <th>Industry</th>
                        <th>Contact</th>
                        <th>Website</th>
                        <th>Active Drives</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $company->name }}</div>
                            @if($company->contact_email)
                                <div class="text-muted" style="font-size:.78rem">{{ $company->contact_email }}</div>
                            @endif
                        </td>
                        <td>{{ $company->industry ?? '—' }}</td>
                        <td>
                            {{ $company->contact_person ?? '—' }}
                            @if($company->contact_phone)
                                <div class="text-muted" style="font-size:.78rem">{{ $company->contact_phone }}</div>
                            @endif
                        </td>
                        <td>
                            @if($company->website)
                                <a href="{{ $company->website }}" target="_blank" class="text-primary" style="font-size:.85rem">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Visit
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-primary">{{ $company->drives_count }}</span>
                        </td>
                        <td>
                            @if($company->is_active)
                                <span class="badge badge-active">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="d-inline" onsubmit="return confirm('Delete this company?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No companies found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($companies->hasPages())
    <div class="card-footer">
        {{ $companies->links() }}
    </div>
    @endif
</div>

@endsection
