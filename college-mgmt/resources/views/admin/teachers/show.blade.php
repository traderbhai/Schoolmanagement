@extends('layouts.admin')
@section('title', 'Teacher Profile')
@section('page-title', 'Teacher Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.teachers.index') }}">Teachers</a></li>
    <li class="breadcrumb-item active">View Teacher</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div></div>
    <a href="{{ route('admin.teachers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Teachers</a>
</div>

<div class="row g-3">
    {{-- Left: Profile Card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center pt-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                    style="width:80px;height:80px;background:#10b981;color:white;font-size:2rem;font-weight:700">
                    {{ strtoupper(substr($teacher->user->name,0,1)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $teacher->user->name }}</h5>
                <div class="text-muted" style="font-size:.84rem">{{ $teacher->designation }}</div>
                <div class="text-muted" style="font-size:.82rem">{{ $teacher->user->email }}</div>
                <div class="mt-2">
                    <span class="badge {{ $teacher->status === 'active' ? 'badge-active' : 'badge-pending' }}">{{ ucfirst($teacher->status) }}</span>
                </div>
            </div>
            <div class="card-body border-top p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.84rem">
                    <tr><td class="text-muted ps-3">Employee ID</td><td class="fw-semibold pe-3"><code>{{ $teacher->employee_id }}</code></td></tr>
                    <tr><td class="text-muted ps-3">Department</td><td class="pe-3">{{ $teacher->department->name }}</td></tr>
                    <tr><td class="text-muted ps-3">Qualification</td><td class="pe-3">{{ $teacher->qualification ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Specialization</td><td class="pe-3">{{ $teacher->specialization ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Phone</td><td class="pe-3">{{ $teacher->phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Joined</td><td class="pe-3">{{ optional($teacher->date_of_joining)->format('d M Y') ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Type</td><td class="pe-3">{{ ucfirst(str_replace('_',' ',$teacher->employment_type)) }}</td></tr>
                    @if($currentSemester)
                    <tr><td class="text-muted ps-3">Weekly Load</td><td class="pe-3"><span class="badge badge-active">{{ $weeklyLoad }} periods</span></td></tr>
                    @endif
                </table>
            </div>
            <div class="card-body border-top">
                <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-pencil me-1"></i>Edit Profile</a>
            </div>
        </div>
    </div>

    {{-- Right: Tabs --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs ms-0" id="teacherTabs">
                    <li class="nav-item">
                        <a class="nav-link active px-4" data-bs-toggle="tab" href="#tab-schedule">
                            <i class="bi bi-grid-3x3-gap me-1"></i>Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4" data-bs-toggle="tab" href="#tab-subjects">
                            <i class="bi bi-book me-1"></i>Subjects Teaching
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4" data-bs-toggle="tab" href="#tab-leaves">
                            <i class="bi bi-calendar-x me-1"></i>Leave History
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                {{-- Schedule Tab --}}
                <div class="tab-pane fade show active" id="tab-schedule">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Day</th><th>Time Slot</th><th>Subject</th><th>Course</th><th>Room</th></tr></thead>
                            <tbody>
                            @forelse(($officialSchedule ?? collect()) as $e)
                            <tr>
                                <td><span class="badge bg-primary">{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$e->day_of_week] ?? 'Day' }}</span></td>
                                <td class="text-muted" style="font-size:.84rem">{{ $e->slot->start_time }} – {{ $e->slot->end_time }}</td>
                                <td class="fw-semibold">{{ $e->subject?->name ?? 'Subject' }} @if(($e->source ?? null) === 'canonical_pmc_official_session')<span class="badge bg-primary-subtle text-primary ms-1">PMC</span>@endif</td>
                                <td>{{ $e->course?->code ?? $e->course_group?->name ?? '-' }}</td>
                                <td>{{ $e->classroom?->room_number ?? 'Room TBA' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5">
                                <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-calendar-x"></i></div><div class="text-muted">No timetable entries.</div></div>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Subjects Tab --}}
                <div class="tab-pane fade" id="tab-subjects">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Subject</th><th>Code</th><th>Course</th><th>Type</th></tr></thead>
                            <tbody>
                            @php $subjects = ($officialSchedule ?? collect())->pluck('subject')->filter()->unique('id'); @endphp
                            @forelse($subjects as $sub)
                            <tr>
                                <td class="fw-semibold">{{ $sub->name }}</td>
                                <td><code>{{ $sub->code }}</code></td>
                                <td>{{ $sub->department?->code ?? $sub->program?->code ?? '-' }}</td>
                                <td><span class="badge bg-info text-dark">{{ ucfirst($sub->type ?? 'subject') }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4">
                                <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-book"></i></div><div class="text-muted">No subjects assigned.</div></div>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

                {{-- Leave History Tab --}}
                <div class="tab-pane fade" id="tab-leaves">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Applied</th></tr></thead>
                            <tbody>
                            @forelse($teacher->leaveApplications()->latest()->take(10)->get() as $leave)
                            <tr>
                                <td>{{ ucfirst($leave->leave_type) }}</td>
                                <td style="font-size:.84rem">{{ $leave->from_date->format('d M Y') }}</td>
                                <td style="font-size:.84rem">{{ $leave->to_date->format('d M Y') }}</td>
                                <td>{{ $leave->days }}</td>
                                <td>
                                    @if($leave->status === 'pending')
                                        <span class="badge badge-pending">Pending</span>
                                    @elseif($leave->status === 'approved')
                                        <span class="badge badge-active">Approved</span>
                                    @else
                                        <span class="badge badge-danger">Rejected</span>
                                    @endif
                                </td>
                                <td style="font-size:.84rem">{{ $leave->created_at->format('d M Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6">
                                <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-calendar-x"></i></div><div class="text-muted">No leave history.</div></div>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
