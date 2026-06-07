@extends('layouts.admin')
@section('title', 'HOD Dashboard')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Head of Department</h4>
            <span class="text-muted small">Department management, faculty oversight, leave approvals</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hod.approvals') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-check2-circle me-1"></i> Pending Approvals
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-people-fill text-primary fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $totalStudents }}</div>
                            <div class="text-muted small">Total Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-hourglass-split text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $pendingApprovals }}</div>
                            <div class="text-muted small">Pending Approvals</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-calendar-x text-info fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $pendingLeaves }}</div>
                            <div class="text-muted small">Pending Leave Requests</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-diagram-3-fill text-success fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $programs->count() }}</div>
                            <div class="text-muted small">Active Programs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('hod.approvals') }}" class="btn btn-outline-warning text-start">
                            <i class="bi bi-check2-circle me-2"></i>Review Pending Approvals
                            @if($pendingApprovals > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $pendingApprovals }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-primary text-start">
                            <i class="bi bi-person-badge me-2"></i>View Faculty Members
                        </a>
                        <a href="{{ route('academic.students.index') }}" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-people me-2"></i>View Students
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Programs Under Department</h6>
                </div>
                <div class="card-body">
                    @forelse($programs->take(5) as $program)
                    <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
                        <span class="small">{{ $program->name }}</span>
                        <span class="badge bg-primary-subtle text-primary">{{ $program->code ?? '—' }}</span>
                    </div>
                    @empty
                    <div class="text-muted small fst-italic">No active programs.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
