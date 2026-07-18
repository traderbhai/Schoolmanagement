@extends('layouts.admin')
@section('title', 'Course Outcomes (CO)')
@section('page-title', 'Course Outcomes')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('academic.obe.co.index') }}">OBE Framework</a></li>
    <li class="breadcrumb-item active">Course Outcomes</li>
@endsection

@section('content')
<div class="row g-3 mb-3">
    {{-- Filter bar --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Program</label>
                        <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Select Program —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(request('program_id') && $subjects->isNotEmpty())
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Subject</label>
                        <select aria-label="Subject" name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Select Subject —</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}" @selected(request('subject_id') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <input type="hidden" name="program_id" value="{{ request('program_id') }}">
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- CO list --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    @if($subject) {{ $subject->name }} — Course Outcomes @else Course Outcomes @endif
                </span>
                @if($subject)
                <a href="{{ route('academic.obe.matrix') }}?program_id={{ $subject->program_id }}&subject_id={{ $subject->id }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-grid-3x3 me-1"></i>CO-PO Matrix
                </a>
                @endif
            </div>
            <div class="card-body p-0">
                @if($outcomes->isEmpty())
                    <div class="text-center text-muted py-5">
                        @if(!$subject) <p>Select a program and subject to manage its COs.</p>
                        @else <p>No course outcomes defined yet.</p> @endif
                    </div>
                @else
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width:80px">Code</th>
                            <th scope="col">Description</th>
                            <th scope="col" style="width:110px">Bloom Level</th>
                            <th scope="col" style="width:60px">Active</th>
                            <th aria-label="Actions" scope="col" style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($outcomes as $co)
                        <tr>
                            <td><span class="badge bg-primary">{{ $co->code }}</span></td>
                            <td><small>{{ $co->description }}</small></td>
                            <td>
                                @php
                                    $bloomColors = ['remember'=>'secondary','understand'=>'info','apply'=>'success','analyze'=>'warning','evaluate'=>'orange','create'=>'danger'];
                                    $bc = $bloomColors[$co->bloom_level] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $bc }} text-capitalize" style="font-size:.65rem">{{ $co->bloom_level }}</span>
                            </td>
                            <td>
                                @if($co->is_active)<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                @else<span class="text-muted"><i class="bi bi-x-circle"></i></span>@endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editCo{{ $co->id }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('academic.obe.co.destroy', $co) }}" class="d-inline"
                                      onsubmit="return confirm('Remove course outcome {{ addslashes($co->code) }}? Confirm it is not used in OBE mappings, attainment records, assessment rubrics, curriculum reports, or accreditation evidence.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" aria-label="Remove course outcome {{ $co->code }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        {{-- Edit Modal --}}
                        <div class="modal fade" id="editCo{{ $co->id }}" tabindex="-1">
                            <div class="modal-dialog"><div class="modal-content">
                                <form method="POST" action="{{ route('academic.obe.co.update', $co) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header"><h6 class="modal-title">Edit {{ $co->code }}</h6>
                                        <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label class="form-label small">Code</label>
                                            <input aria-label="Code" type="text" name="code" class="form-control form-control-sm" value="{{ $co->code }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Description</label>
                                            <textarea aria-label="Description" name="description" class="form-control form-control-sm" rows="3" required>{{ $co->description }}</textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Bloom's Level</label>
                                            <select aria-label="Bloom Level" name="bloom_level" class="form-select form-select-sm">
                                                @foreach(['remember','understand','apply','analyze','evaluate','create'] as $bl)
                                                    <option value="{{ $bl }}" @selected($co->bloom_level === $bl)>{{ ucfirst($bl) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input type="hidden" name="is_active" value="0">
                                            <input aria-label="Course outcome is active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($co->is_active) id="active{{ $co->id }}">
                                            <label class="form-check-label small" for="active{{ $co->id }}">Active</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary">Save outcome</button>
                                    </div>
                                </form>
                            </div></div>
                        </div>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- Add CO form --}}
    @if($subject)
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Add Course Outcome</div>
            <div class="card-body">
                <form method="POST" action="{{ route('academic.obe.co.store') }}">
                    @csrf
                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input aria-label="Course outcome code" type="text" name="code" class="form-control form-control-sm @error('code') is-invalid @enderror"
                               placeholder="CO1, CO2 …" value="{{ old('code') }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea aria-label="Course outcome description" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror"
                                  rows="3" placeholder="Understand and apply sorting algorithms…" required>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bloom's Taxonomy Level</label>
                        <select aria-label="Bloom Level" name="bloom_level" class="form-select form-select-sm">
                            @php $bloomDesc = ['remember'=>'Recall facts','understand'=>'Explain concepts','apply'=>'Use in new situations','analyze'=>'Break into parts','evaluate'=>'Justify decisions','create'=>'Produce original work']; @endphp
                            @foreach($bloomDesc as $level => $desc)
                                <option value="{{ $level }}" @selected(old('bloom_level', 'understand') === $level)>
                                    {{ ucfirst($level) }} — {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Add CO
                    </button>
                </form>
            </div>
        </div>

        {{-- Quick guide --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle text-info me-1"></i>Bloom's Taxonomy Guide</h6>
                <table class="table table-sm table-borderless mb-0" style="font-size:.78rem">
                    <tr><td><span class="badge bg-secondary">Remember</span></td><td>List, recall, define, identify</td></tr>
                    <tr><td><span class="badge bg-info">Understand</span></td><td>Explain, summarize, classify</td></tr>
                    <tr><td><span class="badge bg-success">Apply</span></td><td>Implement, use, demonstrate</td></tr>
                    <tr><td><span class="badge bg-warning text-dark">Analyze</span></td><td>Compare, differentiate, examine</td></tr>
                    <tr><td><span class="badge bg-danger">Evaluate</span></td><td>Judge, defend, critique, justify</td></tr>
                    <tr><td><span class="badge bg-danger">Create</span></td><td>Design, construct, compose</td></tr>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
