@extends('layouts.admin')
@section('title', 'Curriculum Changes')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-journals me-2 text-primary"></i>Curriculum Changes</h4>
            <span class="text-muted small">Proposals for curriculum modification across programs</span>
        </div>
        @role('program_chair|dean_academics|admin')
        <a href="{{ route('academic.curriculum-changes.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Propose Change
        </a>
        @endrole
    </div>

    {{-- Filters --}}
    <form method="GET" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label class="form-label small fw-semibold text-muted">Status</label>
                    <select aria-label="Status" name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        @foreach(['draft','submitted','under_review','approved','rejected'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small fw-semibold text-muted">Program</label>
                    <select aria-label="Program" name="program_id" class="form-select form-select-sm">
                        <option value="">All Programs</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-outline-primary btn-sm me-1">Filter</button>
                    <a href="{{ route('academic.curriculum-changes.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Program</th>
                        <th scope="col">Type</th>
                        <th scope="col">Proposed By</th>
                        <th scope="col">Status</th>
                        <th scope="col">Date</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($changes as $change)
                    <tr>
                        <td class="fw-semibold">{{ $change->title }}</td>
                        <td>{{ $change->program->name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_',' ',$change->change_type)) }}</span></td>
                        <td>{{ $change->proposedBy->name ?? '—' }}</td>
                        <td>
                            @php
                                $colors = ['draft'=>'secondary','submitted'=>'warning','under_review'=>'info','approved'=>'success','rejected'=>'danger'];
                                $color = $colors[$change->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_',' ',$change->status)) }}</span>
                        </td>
                        <td class="text-muted small">{{ $change->submitted_at?->format('d M Y') ?? $change->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('academic.curriculum-changes.show', $change) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No curriculum changes found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($changes->hasPages())
        <div class="card-footer bg-transparent">{{ $changes->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
