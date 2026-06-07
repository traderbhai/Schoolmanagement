@extends('layouts.admin')

@section('title', 'Curriculum Manager — Program Chair')
@section('page-title', 'Curriculum Manager')

@section('content')
<div class="container-fluid px-4">

    {{-- Quick Nav --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-badge me-1"></i> Subject–Faculty Assignments
        </a>
        <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-stars me-1"></i> Elective Management
        </a>
        <a href="{{ route('chair.curriculum.assessment') }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-clipboard2-data me-1"></i> Assessment Scheme
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('chair.curriculum.index') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Program</label>
                        <select name="program_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— Select Program —</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}"
                                    {{ ($selectedProgram && $selectedProgram->id == $program->id) ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Term / Semester</label>
                        <select name="term_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Terms —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}"
                                    {{ request('term_id') == $term->id ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        @if($selectedProgram)
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Subject
                            </button>
                        @endif
                        <a href="{{ route('chair.curriculum.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($selectedProgram)
        <h5 class="mb-3 text-primary fw-semibold">
            <i class="bi bi-book me-2"></i>{{ $selectedProgram->name }} — Curriculum
        </h5>

        @if($programSubjects->isEmpty())
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>No subjects found for the selected filters.
                <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    Add Subject
                </button>
            </div>
        @else
            @foreach($programSubjects->groupBy('term_id') as $termId => $subjects)
                @php $termName = $terms->firstWhere('id', $termId)?->name ?? 'Term ' . $termId; @endphp
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-bookmark-fill text-primary me-2"></i>{{ $termName }}
                        </h6>
                        <span class="badge bg-secondary">{{ $subjects->count() }} subject(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Subject Name</th>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subjects as $i => $ps)
                                        <tr>
                                            <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                            <td class="fw-semibold">{{ $ps->subject->name }}</td>
                                            <td><code>{{ $ps->subject->code }}</code></td>
                                            <td>
                                                @php
                                                    $typeBadges = [
                                                        'compulsory'   => 'primary',
                                                        'elective'     => 'warning',
                                                        'lab'          => 'info',
                                                        'project'      => 'secondary',
                                                        'audit'        => 'light text-dark border',
                                                        'open_elective'=> 'success',
                                                    ];
                                                    $badge = $typeBadges[$ps->type] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $badge }} text-capitalize">
                                                    {{ str_replace('_', ' ', $ps->type) }}
                                                </span>
                                            </td>
                                            <td class="text-center">{{ $ps->subject->credits ?? $ps->credits ?? '—' }}</td>
                                            <td class="text-end pe-3">
                                                <form method="POST"
                                                      action="{{ route('chair.curriculum.remove-subject', $ps->id) }}"
                                                      onsubmit="return confirm('Remove {{ addslashes($ps->subject->name) }} from this curriculum?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    @else
        <div class="text-center py-5 text-muted">
            <i class="bi bi-mortarboard display-3 d-block mb-3"></i>
            <h5>Select a program to manage its curriculum</h5>
            <p class="small">Use the filter above to choose a program and optionally filter by term.</p>
        </div>
    @endif
</div>

{{-- Add Subject Modal --}}
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('chair.curriculum.add-subject') }}">
                @csrf
                @if($selectedProgram)
                    <input type="hidden" name="program_id" value="{{ $selectedProgram->id }}">
                @endif
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addSubjectModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add Subject to Curriculum
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">— Select Subject —</option>
                                @foreach($allSubjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Term / Semester <span class="text-danger">*</span></label>
                            <select name="term_id" class="form-select" required>
                                <option value="">— Select Term —</option>
                                @foreach($terms as $term)
                                    <option value="{{ $term->id }}" {{ request('term_id') == $term->id ? 'selected' : '' }}>
                                        {{ $term->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" id="subjectType" class="form-select" required
                                    onchange="toggleElectiveGroup(this.value)">
                                <option value="">— Select Type —</option>
                                <option value="compulsory">Compulsory</option>
                                <option value="elective">Elective</option>
                                <option value="lab">Lab</option>
                                <option value="project">Project</option>
                                <option value="audit">Audit</option>
                                <option value="open_elective">Open Elective</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="electiveGroupWrapper" style="display:none;">
                            <label class="form-label fw-semibold">Elective Group</label>
                            <input type="number" name="elective_group" class="form-control"
                                   min="1" placeholder="e.g. 1">
                            <div class="form-text">Group number for elective pooling</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Credits <span class="text-danger">*</span></label>
                            <input type="number" name="credits" class="form-control"
                                   min="0" max="10" step="0.5" placeholder="e.g. 4" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleElectiveGroup(type) {
    const wrapper = document.getElementById('electiveGroupWrapper');
    wrapper.style.display = (type === 'elective' || type === 'open_elective') ? '' : 'none';
}
</script>
@endpush
@endsection
