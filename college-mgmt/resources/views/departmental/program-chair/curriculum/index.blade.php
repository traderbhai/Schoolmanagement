@extends('layouts.admin')

@section('title', 'Curriculum Manager — Program Chair')
@section('page-title', 'Curriculum Manager')

@section('content')
<div class="container-fluid px-4">

    {{-- Quick Nav --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <span class="btn btn-primary btn-sm disabled">
            <i class="bi bi-journal-bookmark-fill me-1"></i> Curriculum
        </span>
        <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-outline-secondary btn-sm">
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
            <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('chair.curriculum.index') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Program</label>
                        <select aria-label="Program" name="program_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
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
                        <select aria-label="Term" name="term_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Terms —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}"
                                    {{ request('term_id') == $term->id ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2 align-items-end">
                        @if($selectedProgram)
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
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
        <div class="d-flex align-items-center mb-3">
            <h5 class="mb-0 text-primary fw-semibold">
                <i class="bi bi-mortarboard me-2"></i>{{ $selectedProgram->name }} — Curriculum
            </h5>
        </div>

        @if($programSubjects->isEmpty())
            <div class="alert alert-info d-flex align-items-center">
                <i class="bi bi-info-circle me-2 fs-5"></i>
                <div>
                    No subjects found for the selected filters.
                    <button type="button" class="btn btn-sm btn-primary ms-2"
                            data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Subject
                    </button>
                </div>
            </div>
        @else
            @foreach($programSubjects->groupBy('term_id') as $termId => $subjects)
                @php $termName = $terms->firstWhere('id', $termId)?->name ?? ('Term ' . $termId); @endphp
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
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
                                        <th scope="col" class="ps-3" style="width:50px;">#</th>
                                        <th scope="col">Subject Name</th>
                                        <th scope="col">Code</th>
                                        <th scope="col">Type</th>
                                        <th scope="col" class="text-center">Credits</th>
                                        <th scope="col" class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subjects as $i => $ps)
                                        @php
                                            $typeBadges = [
                                                'compulsory'    => 'primary',
                                                'elective'      => 'warning',
                                                'lab'           => 'info',
                                                'project'       => 'secondary',
                                                'audit'         => 'light text-dark border',
                                                'open_elective' => 'success',
                                            ];
                                            $badge = $typeBadges[$ps->type] ?? 'secondary';
                                            $usage = $curriculumUsage[$ps->id] ?? ['locked' => false, 'labels' => [], 'summary' => 'No downstream usage found.'];
                                        @endphp
                                        <tr>
                                            <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                            <td class="fw-semibold">{{ $ps->subject->name ?? '—' }}</td>
                                            <td><code class="text-secondary">{{ $ps->subject->code ?? '—' }}</code></td>
                                            <td>
                                                <span class="badge bg-{{ $badge }} text-capitalize">
                                                    {{ str_replace('_', ' ', $ps->type ?? 'N/A') }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">
                                                    {{ $ps->credits ?? $ps->subject->credits ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                @if($usage['locked'])
                                                    <span class="badge text-bg-warning border" title="Locked by {{ $usage['summary'] }}">
                                                        <i class="bi bi-lock-fill me-1"></i>Locked
                                                    </span>
                                                    <div class="small text-muted mt-1">Use curriculum revision</div>
                                                @else
                                                    <form method="POST"
                                                          action="{{ route('chair.curriculum.remove-subject', $ps->id) }}"
                                                          class="d-inline"
                                                          onsubmit="return confirm('Remove \'{{ addslashes($ps->subject->name ?? 'this subject') }}\' from {{ addslashes($selectedProgram->name ?? 'this program') }} / {{ addslashes($termName) }}? This can affect subject allocation, timetable groups, attendance, and course delivery records. Continue only after confirming no published workflow depends on it.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove subject" aria-label="Remove curriculum subject">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
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
            <i class="bi bi-mortarboard display-3 d-block mb-3 opacity-25"></i>
            <h5 class="fw-semibold">Select a program to manage its curriculum</h5>
            <p class="small">Use the filter above to choose a program and optionally filter by term.</p>
        </div>
    @endif
</div>

{{-- ===================== ADD SUBJECT MODAL ===================== --}}
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="POST" action="{{ route('chair.curriculum.add-subject') }}">
                @csrf
                @if($selectedProgram)
                    <input type="hidden" name="program_id" value="{{ $selectedProgram->id }}">
                @endif

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-semibold" id="addSubjectModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add Subject to Curriculum
                    </h5>
                    <button aria-label="Close dialog" type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select aria-label="Subject" name="subject_id" class="form-select" required>
                                <option value="">— Select Subject —</option>
                                @foreach($allSubjects as $subject)
                                    <option value="{{ $subject->id }}">
                                        {{ $subject->name }} ({{ $subject->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Term / Semester <span class="text-danger">*</span></label>
                            <select aria-label="Term" name="term_id" class="form-select" required>
                                <option value="">— Select Term —</option>
                                @foreach($terms as $term)
                                    <option value="{{ $term->id }}"
                                        {{ request('term_id') == $term->id ? 'selected' : '' }}>
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
                            <input aria-label="Elective group number" type="number" name="elective_group" class="form-control" min="1" placeholder="e.g. 1">
                            <div class="form-text">Group number for elective pooling (1, 2, 3…)</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Credits <span class="text-danger">*</span></label>
                            <input aria-label="Subject credits" type="number" name="credits" class="form-control"
                                   min="0" max="10" step="0.5" placeholder="e.g. 4" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Max Elective Choices</label>
                            <input aria-label="Maximum elective choices" type="number" name="max_elective_choices" class="form-control"
                                   min="1" placeholder="e.g. 1">
                            <div class="form-text">How many subjects can be selected from this group.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
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
