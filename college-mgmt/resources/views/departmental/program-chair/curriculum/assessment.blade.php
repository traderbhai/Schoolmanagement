@extends('layouts.admin')

@section('title', 'Assessment Scheme — Program Chair')
@section('page-title', 'Assessment Scheme')

@section('content')
<div class="container-fluid px-4">

    {{-- Quick Nav --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('chair.curriculum.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Curriculum
        </a>
        <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person-badge me-1"></i> Assignments
        </a>
        <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-stars me-1"></i> Electives
        </a>
        <span class="btn btn-info btn-sm disabled text-white">
            <i class="bi bi-clipboard2-data me-1"></i> Assessment
        </span>
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
            <form method="GET" action="{{ route('chair.curriculum.assessment') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Program</label>
                        <select aria-label="Program" name="program_id" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
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
                        <label class="form-label fw-semibold mb-1">Term</label>
                        <select aria-label="Term" name="term_id" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Terms —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}"
                                    {{ ($currentTerm && $currentTerm->id == $term->id) ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="{{ route('chair.curriculum.assessment') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Common Presets Info --}}
    <div class="alert alert-info d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-lightbulb-fill fs-5 mt-1 text-info"></i>
        <div>
            <strong>Quick Preset:</strong> Use the <em>Standard</em> button below each subject to pre-fill
            IA1 (20%) + IA2 (20%) + End-Sem (60%). You can adjust values before saving.
        </div>
    </div>

    @if(!$selectedProgram)
        <div class="text-center py-5 text-muted">
            <i class="bi bi-clipboard-x display-3 d-block mb-3 opacity-25"></i>
            <h5 class="fw-semibold">Select a program to set up assessment schemes</h5>
            <p class="small">Use the filter above to choose a program and term.</p>
        </div>
    @elseif($programSubjects->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No subjects found for the selected filters.
            <a href="{{ route('chair.curriculum.index', ['program_id' => $selectedProgram->id]) }}" class="alert-link ms-1">
                Add subjects to the curriculum first.
            </a>
        </div>
    @else
        @foreach($programSubjects as $ps)
            @php
                $components    = $ps->subject->assessmentComponentConfigs ?? collect();
                $totalWeightage = $components->sum('weightage');
                $weightageOk   = abs($totalWeightage - 100) < 0.01;
            @endphp
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <div>
                        <span class="fw-bold text-dark">{{ $ps->subject->name ?? '—' }}</span>
                        <code class="ms-2 text-secondary small">{{ $ps->subject->code ?? '' }}</code>
                        <span class="badge bg-light text-dark border ms-2">{{ $ps->credits ?? '' }} cr.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Total weightage:</span>
                        <span class="badge bg-{{ $weightageOk ? 'success' : 'danger' }} fs-6">
                            {{ number_format($totalWeightage, 1) }}%
                        </span>
                        @if(!$weightageOk && $components->isNotEmpty())
                            <span class="badge bg-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>Must be 100%
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    {{-- Existing components table --}}
                    @if($components->isNotEmpty())
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th scope="col" class="ps-3">Component</th>
                                        <th scope="col" class="text-center">Max Marks</th>
                                        <th scope="col" class="text-center">Weightage %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($components as $comp)
                                        <tr class="{{ !$weightageOk ? 'table-danger' : '' }}">
                                            <td class="ps-3 fw-semibold">{{ $comp->name }}</td>
                                            <td class="text-center">{{ $comp->max_marks }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-{{ $weightageOk ? 'success' : 'danger' }}">
                                                    {{ $comp->weightage }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="table-light fw-bold">
                                        <td class="ps-3">Total</td>
                                        <td class="text-center">{{ $components->sum('max_marks') }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $weightageOk ? 'success' : 'danger' }}">
                                                {{ number_format($totalWeightage, 1) }}%
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i>No assessment components defined yet.
                        </p>
                    @endif

                    {{-- Add Component Form --}}
                    <form method="POST" action="{{ route('chair.curriculum.assessment.save') }}"
                          class="border rounded p-3 bg-light" id="addCompForm_{{ $ps->id }}">
                        @csrf
                        <input type="hidden" name="program_subject_id" value="{{ $ps->id }}">
                        <input type="hidden" name="subject_id" value="{{ $ps->subject_id ?? $ps->id }}">
                        @if($currentTerm)
                            <input type="hidden" name="term_id" value="{{ $currentTerm->id }}">
                        @endif

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0 fw-semibold text-secondary">
                                <i class="bi bi-plus-circle me-1"></i>Add Component
                            </h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="applyStandardPreset('{{ $ps->id }}')">
                                <i class="bi bi-lightning me-1"></i>Standard (IA1 20% + IA2 20% + End-Sem 60%)
                            </button>
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Component Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-sm"
                                       id="compName_{{ $ps->id }}" placeholder="e.g. IA1, End-Sem" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Max Marks <span class="text-danger">*</span></label>
                                <input type="number" name="max_marks" class="form-control form-control-sm"
                                       id="compMaxMarks_{{ $ps->id }}" min="1" placeholder="e.g. 50" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Weightage % <span class="text-danger">*</span></label>
                                <input type="number" name="weightage" class="form-control form-control-sm"
                                       id="compWeightage_{{ $ps->id }}" min="1" max="100" placeholder="e.g. 20" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

@push('scripts')
<script>
function applyStandardPreset(psId) {
    // Standard preset fills just the name/marks/weightage — user reviews before submitting
    const presets = [
        { name: 'IA1', max_marks: 20, weightage: 20 },
        { name: 'IA2', max_marks: 20, weightage: 20 },
        { name: 'End-Sem', max_marks: 100, weightage: 60 },
    ];

    // We fill in the form with IA1 as a starting point and notify user to add the rest
    const nameInput      = document.getElementById('compName_' + psId);
    const maxMarksInput  = document.getElementById('compMaxMarks_' + psId);
    const weightageInput = document.getElementById('compWeightage_' + psId);

    if (!nameInput) return;

    // Find which preset to fill (cycle through)
    const currentName = nameInput.value;
    let nextPreset = presets[0];
    for (let i = 0; i < presets.length; i++) {
        if (presets[i].name === currentName && i + 1 < presets.length) {
            nextPreset = presets[i + 1];
            break;
        }
    }

    nameInput.value      = nextPreset.name;
    maxMarksInput.value  = nextPreset.max_marks;
    weightageInput.value = nextPreset.weightage;
    nameInput.focus();
}
</script>
@endpush
@endsection
