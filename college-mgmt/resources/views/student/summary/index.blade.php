@extends('layouts.student')
@section('title', 'Academic Summary')
@section('page-title', 'Academic Summary Card')

@section('content')
<div class="container py-4" style="max-width:860px">

    {{-- Summary Card --}}
    <div class="card border-0 shadow mb-4" id="summaryCard">
        <div class="card-body p-4">

            {{-- Header --}}
            <div class="d-flex align-items-center gap-4 mb-4 pb-3 border-bottom">
                <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;background:#e9ecef;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    @if($student->photo)
                        <img src="{{ Storage::url($student->photo) }}" alt="Profile photo of {{ $student->user->name }}" style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <i class="bi bi-person-fill fs-2 text-secondary"></i>
                    @endif
                </div>
                <div>
                    <h4 class="fw-bold mb-0">{{ $student->user->name }}</h4>
                    <div class="text-muted">{{ $student->enrollment_number }}</div>
                    <div class="small mt-1">
                        <span class="badge bg-primary me-1">{{ $student->program->name ?? '—' }}</span>
                        <span class="badge bg-secondary me-1">{{ $student->batch->name ?? '—' }}</span>
                        <span class="badge bg-info text-dark">{{ $student->currentTerm->name ?? 'Current Term' }}</span>
                    </div>
                </div>
            </div>

            {{-- KPI Row --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded text-center" style="background:#f0f9ff;border:1px solid #bae6fd;">
                        <div class="fw-bold fs-4 text-primary">{{ $cgpa ?? '—' }}</div>
                        <div class="small text-muted">CGPA</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded text-center" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <div class="fw-bold fs-4 {{ ($overallAttendance ?? 100) >= 75 ? 'text-success' : 'text-danger' }}">
                            {{ $overallAttendance !== null ? $overallAttendance . '%' : '—' }}
                        </div>
                        <div class="small text-muted">Attendance</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded text-center" style="background:#fefce8;border:1px solid #fde68a;">
                        <div class="fw-bold fs-4 text-warning">{{ $totalCreditsEnrolled }}</div>
                        <div class="small text-muted">Credits Enrolled</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded text-center" style="background:#fdf2f8;border:1px solid #f9a8d4;">
                        <div class="fw-bold fs-4" style="color:#9d174d;">{{ $passedCount }}<span class="small fw-normal text-muted">/{{ $passedCount + $failedCount }}</span></div>
                        <div class="small text-muted">Exams Passed</div>
                    </div>
                </div>
            </div>

            {{-- Details Grid --}}
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-semibold border-bottom pb-1 mb-2">Personal Details</h6>
                    <dl class="row g-0 small mb-0">
                        <dt class="col-5 text-muted">Department</dt>
                        <dd class="col-7 mb-1">{{ $student->department->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Program</dt>
                        <dd class="col-7 mb-1">{{ $student->program->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Batch</dt>
                        <dd class="col-7 mb-1">{{ $student->batch->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Current Term</dt>
                        <dd class="col-7 mb-1">{{ $student->currentTerm->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Mentor</dt>
                        <dd class="col-7 mb-1">{{ $student->mentor->user->name ?? '—' }}</dd>
                        @if($student->email ?? $student->user->email ?? null)
                        <dt class="col-5 text-muted">Email</dt>
                        <dd class="col-7 mb-1">{{ $student->user->email }}</dd>
                        @endif
                    </dl>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold border-bottom pb-1 mb-2">Enrolled Subjects (Current)</h6>
                    @if($enrolledSubjects->isEmpty())
                    <p class="text-muted small">No active enrollments found.</p>
                    @else
                    <ul class="list-unstyled small mb-0">
                        @foreach($enrolledSubjects->take(8) as $enr)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $enr->subject->name ?? '—' }}</span>
                            <span class="text-muted">{{ $enr->subject->credits ?? '—' }} cr</span>
                        </li>
                        @endforeach
                        @if($enrolledSubjects->count() > 8)
                        <li class="text-muted">+ {{ $enrolledSubjects->count() - 8 }} more</li>
                        @endif
                    </ul>
                    @endif
                </div>
            </div>

            @if($promotions->isNotEmpty())
            <div class="mt-4">
                <h6 class="fw-semibold border-bottom pb-1 mb-2">Term Progression</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($promotions as $p)
                    @php $badge = match($p->status) { 'approved'=>'success','rejected'=>'danger',default=>'secondary' }; @endphp
                    <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} border border-{{ $badge }}-subtle px-2 py-1" style="font-size:.78rem;">
                        {{ $p->currentTerm->name ?? '?' }} → {{ $p->promotedToTerm->name ?? '?' }}
                        <span class="opacity-75">({{ ucfirst($p->status) }})</span>
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">Generated {{ now()->format('d M Y') }}</div>
                <div class="small text-muted">EduManage · {{ config('app.name') }}</div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print / Save as PDF
        </button>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .sidebar-desktop, .sidebar-mobile, .topbar, .breadcrumb,
    .btn, nav, form { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    #summaryCard { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
@endpush
