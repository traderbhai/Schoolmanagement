@extends('layouts.admin')

@section('title', 'Merit List — ' . $program->name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-list-ol me-2"></i>Merit List</h3>
                <div class="text-muted">{{ $program->name }} @if($program->code) <span class="badge bg-secondary">{{ $program->code }}</span> @endif</div>
            </div>
            <div class="d-flex gap-2">
                @if($latestVersion)
                    <a href="{{ route('admission.merit-list.show', $program) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i>View Merit List
                    </a>
                    <a href="{{ route('admission.merit-list.export', $program) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-pdf me-1"></i>Export PDF
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Batch filter --}}
<form method="GET" class="mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small">Filter by Batch</label>
            <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Batches</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected($batchId == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

@if($latestVersion)
{{-- Stats --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total Applicants','value'=>$stats['total'],'color'=>'primary','icon'=>'people'],
        ['label'=>'Selected','value'=>$stats['selected'],'color'=>'success','icon'=>'check-circle'],
        ['label'=>'Waitlisted','value'=>$stats['waitlisted'],'color'=>'warning','icon'=>'clock-history'],
        ['label'=>'Rejected','value'=>$stats['rejected'],'color'=>'danger','icon'=>'x-circle'],
        ['label'=>'Pending','value'=>$stats['pending'],'color'=>'secondary','icon'=>'hourglass-split'],
    ] as $stat)
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-{{ $stat['color'] }}">{{ $stat['value'] }}</div>
                <div class="small text-muted"><i class="bi bi-{{ $stat['icon'] }} me-1"></i>{{ $stat['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body text-muted small">
        <i class="bi bi-info-circle me-1"></i>Merit List Version: <strong>{{ $latestVersion }}</strong>.
        Use "Regenerate" below to recalculate scores.
    </div>
</div>
@endif

{{-- Generate / Regenerate form --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0">{{ $latestVersion ? 'Regenerate Merit List' : 'Generate Merit List' }}</h6>
    </div>
    <div class="card-body">
        @if(!$latestVersion)
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No merit list generated yet. Configure weights and click Generate.</div>
        @endif
        <form method="POST" action="{{ route('admission.merit-list.generate', $program) }}">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-sm-4">
                    <label class="form-label small fw-semibold">Batch (optional)</label>
                    <select name="batch_id" class="form-select form-select-sm">
                        <option value="">All Batches</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}" @selected($batchId == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small fw-semibold">Academic Score Weight: <span id="acad-val">20</span>%</label>
                    <input type="range" name="academic_weight" id="academic_weight" class="form-range"
                        min="0" max="100" step="5" value="20"
                        oninput="document.getElementById('acad-val').textContent=this.value; document.getElementById('sel-val').textContent=100-parseInt(this.value)">
                    <div class="text-muted small">Selection Score Weight: <span id="sel-val">80</span>%</div>
                </div>
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-{{ $latestVersion ? 'warning' : 'success' }} w-100"
                        onclick="return confirm('{{ $latestVersion ? 'Regenerate merit list? This will create version '.($latestVersion+1).'.' : 'Generate merit list?' }}')">
                        <i class="bi bi-{{ $latestVersion ? 'arrow-repeat' : 'play-circle' }} me-1"></i>
                        {{ $latestVersion ? 'Regenerate Merit List' : 'Generate Merit List' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
