@extends('layouts.admin')
@section('title', 'Program Outcomes (PO/PSO)')
@section('page-title', 'Program Outcomes')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">OBE Framework</a></li>
    <li class="breadcrumb-item active">Program Outcomes</li>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Program</label>
                        <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Select Program —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($program)
<div class="row g-3">
    {{-- PO list --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">
                Program Outcomes (PO) — {{ $program->name }}
            </div>
            <div class="card-body p-0">
                @if($outcomes->isEmpty())
                    <div class="text-center text-muted py-4">No program outcomes defined yet.</div>
                @else
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">Code</th>
                            <th>Description</th>
                            <th style="width:100px">Category</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($outcomes as $po)
                        <tr>
                            <td><span class="badge bg-success">{{ $po->code }}</span></td>
                            <td><small>{{ $po->description }}</small></td>
                            <td><small class="text-muted text-capitalize">{{ $po->category }}</small></td>
                            <td>
                                <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editPo{{ $po->id }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('academic.obe.po.destroy', $po) }}" class="d-inline"
                                      onsubmit="return confirm('Remove {{ $po->code }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <div class="modal fade" id="editPo{{ $po->id }}" tabindex="-1">
                            <div class="modal-dialog"><div class="modal-content">
                                <form method="POST" action="{{ route('academic.obe.po.update', $po) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header"><h6 class="modal-title">Edit {{ $po->code }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label class="form-label small">Code</label>
                                            <input type="text" name="code" class="form-control form-control-sm" value="{{ $po->code }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Description</label>
                                            <textarea name="description" class="form-control form-control-sm" rows="3" required>{{ $po->description }}</textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Category</label>
                                            <select name="category" class="form-select form-select-sm">
                                                @foreach(['engineering','management','science','commerce','general'] as $cat)
                                                    <option value="{{ $cat }}" @selected($po->category === $cat)>{{ ucfirst($cat) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
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

        {{-- PSO list --}}
        @if($psos->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-bottom fw-semibold">
                Program Specific Outcomes (PSO) — NBA
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th style="width:80px">Code</th><th>Description</th><th style="width:50px"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($psos as $pso)
                        <tr>
                            <td><span class="badge bg-warning text-dark">{{ $pso->code }}</span></td>
                            <td><small>{{ $pso->description }}</small></td>
                            <td>
                                <form method="POST" action="{{ route('academic.obe.pso.destroy', $pso) }}" class="d-inline"
                                      onsubmit="return confirm('Remove {{ $pso->code }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Add forms --}}
    <div class="col-lg-5">
        {{-- Add PO --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Add Program Outcome (PO)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('academic.obe.po.store') }}">
                    @csrf
                    <input type="hidden" name="program_id" value="{{ $program->id }}">
                    <div class="mb-2">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm"
                               placeholder="PO1 … PO12" value="{{ old('code') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" required>{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            @foreach(['engineering','management','science','commerce','general'] as $cat)
                                <option value="{{ $cat }}" @selected(old('category', 'general') === $cat)>{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Add PO
                    </button>
                </form>
            </div>
        </div>

        {{-- Add PSO --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-bottom fw-semibold">Add Program Specific Outcome (PSO)</div>
            <div class="card-body">
                <p class="text-muted" style="font-size:.78rem">PSOs are program-specific competencies beyond the standard POs, used in NBA accreditation.</p>
                <form method="POST" action="{{ route('academic.obe.pso.store') }}">
                    @csrf
                    <input type="hidden" name="program_id" value="{{ $program->id }}">
                    <div class="mb-2">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="PSO1, PSO2 …" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Add PSO
                    </button>
                </form>
            </div>
        </div>

        {{-- NBA standard POs quick-fill --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle text-info me-1"></i>NBA Standard POs (Engineering)</h6>
                <ol style="font-size:.75rem;padding-left:1.2rem;color:#666">
                    <li>Engineering Knowledge</li>
                    <li>Problem Analysis</li>
                    <li>Design/Development of Solutions</li>
                    <li>Conduct Investigations of Complex Problems</li>
                    <li>Modern Tool Usage</li>
                    <li>The Engineer and Society</li>
                    <li>Environment and Sustainability</li>
                    <li>Ethics</li>
                    <li>Individual and Team Work</li>
                    <li>Communication</li>
                    <li>Project Management and Finance</li>
                    <li>Life-long Learning</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@else
<div class="text-center text-muted py-5">
    <i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>
    Select a program above to manage its Program Outcomes.
</div>
@endif
@endsection
