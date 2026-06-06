@extends('layouts.admin')
@section('title', 'Role Hierarchy')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Role Hierarchy</h4>
            <span class="text-muted small">View role structure and access levels across the institution</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.feature-access.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-grid-3x3 me-1"></i> Feature Access Matrix
            </a>
            <a href="{{ route('admin.users.roles.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i> Assign Role
            </a>
        </div>
    </div>

    @php
    $roleTree = [
        ['name'=>'admin',            'label'=>'Administrator',         'color'=>'danger',  'icon'=>'shield-fill',       'desc'=>'Full system access — all programs, all data', 'children'=>['dean_academics','accounts_officer','exam_cell']],
        ['name'=>'dean_academics',   'label'=>'Dean of Academics',     'color'=>'purple',  'icon'=>'mortarboard-fill',  'desc'=>'Academic oversight, approvals, grievances, curriculum', 'children'=>['program_chair','hod']],
        ['name'=>'program_chair',    'label'=>'Program Chair',         'color'=>'primary', 'icon'=>'person-badge-fill', 'desc'=>'Program-specific: curriculum, cohort, placement', 'children'=>['faculty']],
        ['name'=>'hod',              'label'=>'Head of Department',    'color'=>'info',    'icon'=>'building-fill',     'desc'=>'Department management, faculty, leave, budget', 'children'=>['faculty']],
        ['name'=>'exam_cell',        'label'=>'Exam Cell',             'color'=>'warning', 'icon'=>'journal-text',      'desc'=>'Exam scheduling, results, transcripts, malpractice', 'children'=>[]],
        ['name'=>'faculty',          'label'=>'Faculty / Teacher',     'color'=>'success', 'icon'=>'person-workspace',  'desc'=>'Teaching, marking, attendance, mentoring', 'children'=>[]],
        ['name'=>'accounts_officer', 'label'=>'Accounts Officer',      'color'=>'secondary','icon'=>'cash-stack',       'desc'=>'Fee collections, reconciliation, payments', 'children'=>[]],
        ['name'=>'cmc',              'label'=>'Placement / CMC',       'color'=>'dark',    'icon'=>'briefcase-fill',    'desc'=>'Placement drives, internships, alumni, career services', 'children'=>[]],
        ['name'=>'student',          'label'=>'Student',               'color'=>'light',   'icon'=>'backpack-fill',     'desc'=>'Attendance, results, fees, timetable, notices', 'children'=>[]],
    ];

    $topLevel = ['admin','dean_academics','accounts_officer','exam_cell','cmc','student'];
    $roleMap = collect($roleTree)->keyBy('name');
    @endphp

    {{-- Hierarchy Tree --}}
    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">INSTITUTIONAL HIERARCHY</h6>
                </div>
                <div class="card-body">
                    {{-- Level 1: Admin --}}
                    <div class="text-center mb-3">
                        <span class="badge bg-danger px-3 py-2 fs-6 rounded-pill">
                            <i class="bi bi-shield-fill me-2"></i>Administrator
                        </span>
                        <div class="text-muted small mt-1">Full system — all programs, all data</div>
                    </div>

                    {{-- Connector --}}
                    <div class="text-center mb-2"><div style="border-left:2px solid #dee2e6;height:24px;display:inline-block;"></div></div>

                    {{-- Level 2: Dean --}}
                    <div class="row justify-content-center mb-3">
                        <div class="col-md-4 text-center">
                            <div class="card border-2 border-primary h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-primary-subtle text-primary px-2 py-1 rounded-pill mb-1">Dean of Academics</span>
                                    <div class="text-muted" style="font-size:.75rem">Academic oversight, approvals</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="card border-2 border-secondary h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1 rounded-pill mb-1">Accounts Officer</span>
                                    <div class="text-muted" style="font-size:.75rem">Fees, reconciliation</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="card border-2 border-warning h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-warning-subtle text-warning px-2 py-1 rounded-pill mb-1">Exam Cell</span>
                                    <div class="text-muted" style="font-size:.75rem">Results, transcripts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Connector for Dean branch --}}
                    <div class="row justify-content-center mb-2">
                        <div class="col-md-4 text-center">
                            <div style="border-left:2px solid #dee2e6;height:24px;display:inline-block;"></div>
                        </div>
                    </div>

                    {{-- Level 3: Program Chair + HOD --}}
                    <div class="row justify-content-center mb-3">
                        <div class="col-md-3 text-center">
                            <div class="card border-2 border-info h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-info-subtle text-info px-2 py-1 rounded-pill mb-1">Program Chair</span>
                                    <div class="text-muted" style="font-size:.75rem">Program-specific scope</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center ms-2">
                            <div class="card border-2 border-info h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-info-subtle text-info px-2 py-1 rounded-pill mb-1">Head of Department</span>
                                    <div class="text-muted" style="font-size:.75rem">Department-specific scope</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Level 4: Faculty --}}
                    <div class="row justify-content-center mb-3">
                        <div class="col-md-4 text-center">
                            <div style="border-left:2px solid #dee2e6;height:24px;display:inline-block;"></div>
                        </div>
                    </div>
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-3 text-center">
                            <div class="card border-2 border-success h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill mb-1">Faculty / Teacher</span>
                                    <div class="text-muted" style="font-size:.75rem">Own courses only</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center ms-2">
                            <div class="card border-2 border-dark h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-dark-subtle text-dark px-2 py-1 rounded-pill mb-1">Placement / CMC</span>
                                    <div class="text-muted" style="font-size:.75rem">Drives, internships, alumni</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center ms-2">
                            <div class="card border-2 border-secondary h-100">
                                <div class="card-body py-2">
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1 rounded-pill mb-1">Student</span>
                                    <div class="text-muted" style="font-size:.75rem">Own data only</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Role cards --}}
    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.7rem;letter-spacing:.08em">ALL ROLES — DETAILED</h6>
    <div class="row g-3">
        @foreach($roles as $role)
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $role->name }}</span>
                        <a href="{{ route('admin.roles.permissions.show', $role) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </div>
                    <div class="small text-muted mt-2">
                        @php $count = $role->permissions->count(); @endphp
                        <i class="bi bi-key me-1"></i>{{ $count }} permission{{ $count !== 1 ? 's' : '' }}
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="{{ route('admin.users.roles.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>View All Role Assignments
        </a>
        <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>Audit Log
        </a>
    </div>
</div>
@endsection
