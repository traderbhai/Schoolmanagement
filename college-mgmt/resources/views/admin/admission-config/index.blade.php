@extends('layouts.admin')
@section('title', 'Admission Config — ' . $program->name)
@section('page-title', 'Admission Configuration')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}">Programs</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.show', $program) }}">{{ $program->name }}</a></li>
    <li class="breadcrumb-item active">Admission Config</li>
@endsection
@section('content')

{{-- Program Banner --}}
<div class="card border-0 mb-4" style="background:linear-gradient(135deg,#312e81,#4f46e5)">
    <div class="card-body py-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="opacity-75 small fw-semibold text-uppercase">Admission Configuration</div>
            <h4 class="fw-bold mb-1">{{ $program->name }}</h4>
            <div class="d-flex gap-3" style="font-size:.83rem;opacity:.85">
                <span><i class="bi bi-mortarboard me-1"></i>{{ $program->code }}</span>
                <span><i class="bi bi-calendar me-1"></i>{{ $program->system_type_label }} system</span>
                <span><i class="bi bi-collection me-1"></i>{{ $program->total_terms }} terms</span>
            </div>
        </div>
        <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-sm btn-light btn-outline-light text-white" style="border-color:rgba(255,255,255,.4)">
            <i class="bi bi-arrow-left me-1"></i>Back to Program
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Config Status Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 text-center p-3" style="box-shadow:var(--shadow-sm);border-left:4px solid {{ $program->admissionFormConfig ? '#059669' : '#d97706' }} !important">
            <div style="font-size:1.8rem">{{ $program->admissionFormConfig ? '✓' : '○' }}</div>
            <div class="fw-semibold small mt-1">Application Form</div>
            <div class="text-muted" style="font-size:.75rem">{{ $program->admissionFormConfig ? count($program->admissionFormConfig->form_sections ?? []).' sections configured' : 'Not configured' }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 text-center p-3" style="box-shadow:var(--shadow-sm);border-left:4px solid {{ $program->requiredDocuments->count() > 0 ? '#059669' : '#d97706' }} !important">
            <div style="font-size:1.8rem">{{ $program->requiredDocuments->count() > 0 ? '✓' : '○' }}</div>
            <div class="fw-semibold small mt-1">Required Documents</div>
            <div class="text-muted" style="font-size:.75rem">{{ $program->requiredDocuments->count() }} documents defined</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 text-center p-3" style="box-shadow:var(--shadow-sm);border-left:4px solid {{ $program->selectionProcessSteps->count() > 0 ? '#059669' : '#d97706' }} !important">
            <div style="font-size:1.8rem">{{ $program->selectionProcessSteps->count() > 0 ? '✓' : '○' }}</div>
            <div class="fw-semibold small mt-1">Selection Process</div>
            <div class="text-muted" style="font-size:.75rem">{{ $program->selectionProcessSteps->count() }} steps configured</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 text-center p-3" style="box-shadow:var(--shadow-sm);border-left:4px solid {{ $program->admissionFeeInstallments->count() > 0 ? '#059669' : '#d97706' }} !important">
            <div style="font-size:1.8rem">{{ $program->admissionFeeInstallments->count() > 0 ? '✓' : '○' }}</div>
            <div class="fw-semibold small mt-1">Fee Structure</div>
            <div class="text-muted" style="font-size:.75rem">{{ $program->admissionFeeInstallments->count() }} installments · ₹{{ number_format($program->admissionFeeInstallments->sum('amount')) }}</div>
        </div>
    </div>
</div>

{{-- Four config sections as Bootstrap tabs --}}
<ul class="nav nav-tabs mb-0" style="border-bottom:none">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-form"><i class="bi bi-ui-checks me-1"></i>Application Form</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-docs"><i class="bi bi-file-earmark-text me-1"></i>Required Documents</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-selection"><i class="bi bi-diagram-3 me-1"></i>Selection Process</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-fees"><i class="bi bi-cash-coin me-1"></i>Admission Fees</a></li>
</ul>

<div class="tab-content">

{{-- TAB 1: Application Form Config --}}
<div class="tab-pane fade show active" id="tab-form">
<div class="card border-0 border-top-0 rounded-0 rounded-bottom" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-1">Application Form Fields</h6>
                <p class="text-muted small mb-0">Configure what information applicants must fill when applying. Click "Edit Form Config" to customise fields.</p>
            </div>
            <a href="{{ route('admin.admission-config.form', $program) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit Form Config
            </a>
        </div>
        @if($program->admissionFormConfig)
        <div class="row g-2">
            @foreach($program->admissionFormConfig->form_sections as $section)
            <div class="col-md-6">
                <div class="card" style="border:1px solid var(--clr-border)">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold small mb-1">{{ $section['section'] }}</div>
                        <div class="text-muted" style="font-size:.78rem">{{ count($section['fields']) }} fields
                            ({{ collect($section['fields'])->where('required', true)->count() }} required)</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="empty-state py-4 text-center">
            <div class="empty-icon"><i class="bi bi-ui-checks" style="font-size:2.5rem;color:var(--clr-text-muted)"></i></div>
            <p class="text-muted small mt-2 mb-3">No form configuration yet. Click "Edit Form Config" to set up or load the default template.</p>
        </div>
        @endif
    </div>
</div>
</div>

{{-- TAB 2: Required Documents --}}
<div class="tab-pane fade" id="tab-docs">
<div class="card border-0 border-top-0 rounded-0 rounded-bottom" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-1">Required Documents</h6>
                <p class="text-muted small mb-0">Documents applicants must upload after selection.</p>
            </div>
            <form method="POST" action="{{ route('admin.admission-config.documents.seed', $program) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="bi bi-download me-1"></i>Load Defaults
                </button>
            </form>
        </div>

        {{-- Existing documents list --}}
        @foreach($program->requiredDocuments as $doc)
        <div class="card mb-2" style="border:1px solid var(--clr-border)">
            <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="{{ $doc->is_mandatory ? 'badge bg-danger' : 'badge bg-secondary' }}" style="font-size:.7rem">
                        {{ $doc->is_mandatory ? 'Required' : 'Optional' }}
                    </span>
                    <div>
                        <div class="fw-semibold" style="font-size:.88rem">{{ $doc->name }}</div>
                        @if($doc->description)<div class="text-muted" style="font-size:.75rem">{{ $doc->description }}</div>@endif
                        <div class="text-muted" style="font-size:.72rem">Formats: {{ $doc->accepted_formats }} · Max: {{ round($doc->max_size_kb/1024,1) }}MB</div>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <form method="POST" action="{{ route('admin.admission-config.documents.destroy', $doc) }}" onsubmit="return confirm('Remove this document?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Add new document form --}}
        <div class="card mt-3" style="border:1px dashed var(--clr-border)">
            <div class="card-body py-3">
                <div class="fw-semibold small mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Document Requirement</div>
                <form method="POST" action="{{ route('admin.admission-config.documents.store', $program) }}" class="row g-2">
                    @csrf
                    <div class="col-md-4">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Document name *" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="Description (optional)">
                    </div>
                    <div class="col-md-2">
                        <select name="is_mandatory" class="form-select form-select-sm">
                            <option value="1">Required</option>
                            <option value="0">Optional</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

{{-- TAB 3: Selection Process --}}
<div class="tab-pane fade" id="tab-selection">
<div class="card border-0 border-top-0 rounded-0 rounded-bottom" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="mb-3">
            <h6 class="fw-bold mb-1">Selection Process Steps</h6>
            <p class="text-muted small mb-0">Configure the selection steps (GD, PI, WAT etc.) in order. Each step can have scoring parameters with individual max scores.</p>
        </div>

        @forelse($program->selectionProcessSteps as $step)
        <div class="card mb-3" style="border:1px solid var(--clr-border)">
            <div class="card-header d-flex align-items-center justify-content-between py-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.75rem">{{ $step->step_order }}</span>
                    <span class="fw-semibold">{{ $step->name }}</span>
                    <span class="badge bg-secondary" style="font-size:.7rem">{{ $step->type_label }}</span>
                </div>
                <div class="d-flex gap-2 align-items-center text-muted small">
                    <span>Max: {{ $step->max_score }}</span>
                    <span>Weight: {{ $step->weightage }}%</span>
                    <form method="POST" action="{{ route('admin.admission-config.steps.destroy', $step) }}" onsubmit="return confirm('Remove this step?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
            </div>
            <div class="card-body py-2">
                @if($step->instructions)<p class="text-muted small mb-2">{{ $step->instructions }}</p>@endif
                <div class="fw-semibold small mb-2">Scoring Parameters:</div>
                @forelse($step->scoringParameters as $param)
                <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
                    <span style="font-size:.83rem">{{ $param->name }}</span>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-light text-dark border">Max: {{ $param->max_score }}</span>
                        <form method="POST" action="{{ route('admin.admission-config.parameters.destroy', $param) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm py-0 px-1 text-danger border-0 bg-transparent"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted small">No parameters added yet.</p>
                @endforelse
                {{-- Add parameter --}}
                <form method="POST" action="{{ route('admin.admission-config.parameters.store', $step) }}" class="row g-1 mt-2">
                    @csrf
                    <div class="col"><input type="text" name="name" class="form-control form-control-sm" placeholder="Parameter name (e.g. Communication)" required></div>
                    <div class="col-2"><input type="number" name="max_score" class="form-control form-control-sm" placeholder="Max" value="10" min="1" max="100" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Add</button></div>
                </form>
            </div>
        </div>
        @empty
        <div class="empty-state py-4 text-center">
            <p class="text-muted small">No selection steps configured. Add your first step below.</p>
        </div>
        @endforelse

        {{-- Add step form --}}
        <div class="card mt-2" style="border:1px dashed var(--clr-border)">
            <div class="card-body py-3">
                <div class="fw-semibold small mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Selection Step</div>
                <form method="POST" action="{{ route('admin.admission-config.steps.store', $program) }}" class="row g-2">
                    @csrf
                    <div class="col-md-3">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Step name *" required>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm" required>
                            <option value="gd">Group Discussion</option>
                            <option value="pi" selected>Personal Interview</option>
                            <option value="wat">Written Ability Test</option>
                            <option value="written_test">Written Test</option>
                            <option value="aptitude">Aptitude Test</option>
                            <option value="presentation">Presentation</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <input type="number" name="step_order" class="form-control form-control-sm" placeholder="Order" value="{{ $program->selectionProcessSteps->count() + 1 }}" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="max_score" class="form-control form-control-sm" placeholder="Max Score" value="100" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="weightage" class="form-control form-control-sm" placeholder="Weightage %" value="100" min="1" max="100" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Add Step</button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="instructions" class="form-control form-control-sm" placeholder="Instructions for evaluators (optional)">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

{{-- TAB 4: Admission Fees --}}
<div class="tab-pane fade" id="tab-fees">
<div class="card border-0 border-top-0 rounded-0 rounded-bottom" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="mb-3">
            <h6 class="fw-bold mb-1">Admission Fee Structure</h6>
            <p class="text-muted small mb-0">Define the admission fee instalments. Can be one lump sum or multiple installments. Can be set per batch or for all batches of this program.</p>
        </div>

        @php $totalFee = $program->admissionFeeInstallments->sum('amount'); @endphp
        @if($program->admissionFeeInstallments->count() > 0)
        <div class="alert alert-info py-2 mb-3" style="font-size:.85rem">
            <i class="bi bi-info-circle me-1"></i>
            Total admission fee: <strong>₹{{ number_format($totalFee) }}</strong> across {{ $program->admissionFeeInstallments->count() }} installment(s)
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Name</th><th>Amount</th><th>Due Date</th><th>Batch</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($program->admissionFeeInstallments as $inst)
                <tr>
                    <td>{{ $inst->installment_number }}</td>
                    <td class="fw-semibold">{{ $inst->name }}</td>
                    <td class="fw-bold text-success">₹{{ number_format($inst->amount) }}</td>
                    <td class="small text-muted">{{ $inst->due_date ? $inst->due_date->format('d M Y') : 'No deadline' }}</td>
                    <td class="small text-muted">{{ $inst->batch ? $inst->batch->name : 'All batches' }}</td>
                    <td>
                        <span class="badge {{ $inst->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $inst->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.admission-config.fee.destroy', $inst) }}" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash3"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No fee installments configured yet.</td></tr>
                @endforelse
                </tbody>
                @if($program->admissionFeeInstallments->count() > 0)
                <tfoot class="table-light fw-bold">
                    <tr><td colspan="2" class="text-end">Total</td><td class="text-success">₹{{ number_format($totalFee) }}</td><td colspan="4"></td></tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Add installment form --}}
        <div class="card mt-3" style="border:1px dashed var(--clr-border)">
            <div class="card-body py-3">
                <div class="fw-semibold small mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Fee Installment</div>
                <form method="POST" action="{{ route('admin.admission-config.fee.store', $program) }}" class="row g-2">
                    @csrf
                    <div class="col-md-3">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Name (e.g. Registration Fee)" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount (₹)" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-1">
                        <input type="number" name="installment_number" class="form-control form-control-sm" placeholder="#" value="{{ $program->admissionFeeInstallments->count() + 1 }}" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="due_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <select name="batch_id" class="form-select form-select-sm">
                            <option value="">All batches</option>
                            @foreach($program->batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

</div>{{-- end tab-content --}}
@endsection
