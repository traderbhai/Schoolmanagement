@extends('layouts.admin')

@section('title', 'Merit List - ' . $program->name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-list-ol me-2"></i>Merit List</h3>
                <div class="text-muted">
                    Selection ranking, waitlist source, and offer-round input for {{ $program->name }}
                    @if($program->code) <span class="badge bg-secondary">{{ $program->code }}</span> @endif
                </div>
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

<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-2"><i class="bi bi-diagram-3 me-1"></i>Merit-list control sequence</div>
    <div class="d-flex flex-wrap gap-2 small">
        <span class="badge bg-light text-dark">1. Shortlist applicants</span>
        <span class="badge bg-light text-dark">2. Record assessment scores</span>
        <span class="badge bg-light text-dark">3. Confirm seat matrix</span>
        <span class="badge bg-light text-dark">4. Generate ranked list</span>
        <span class="badge bg-light text-dark">5. Decide selected or waitlisted</span>
        <span class="badge bg-light text-dark">6. Issue offers</span>
    </div>
    <div class="small text-muted mt-2">Regeneration is blocked once active offer letters exist, so verify scores, batch filter, and seat capacity before publishing selection decisions.</div>
</div>

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
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total Applicants','value'=>$stats['total'],'color'=>'primary','icon'=>'people','decision'=>null],
        ['label'=>'Selected','value'=>$stats['selected'],'color'=>'success','icon'=>'check-circle','decision'=>'selected'],
        ['label'=>'Waitlisted','value'=>$stats['waitlisted'],'color'=>'warning','icon'=>'clock-history','decision'=>'waitlisted'],
        ['label'=>'Rejected','value'=>$stats['rejected'],'color'=>'danger','icon'=>'x-circle','decision'=>'rejected'],
        ['label'=>'Pending','value'=>$stats['pending'],'color'=>'secondary','icon'=>'hourglass-split','decision'=>'pending'],
    ] as $stat)
    @php($statUrl = route('admission.merit-list.show', array_filter(['program' => $program->id, 'batch_id' => $batchId, 'decision' => $stat['decision']])))
    <div class="col-6 col-md">
        <a href="{{ $statUrl }}" class="card border-0 shadow-sm text-center text-decoration-none h-100" aria-label="Open {{ $stat['label'] }} merit list entries">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-{{ $stat['color'] }}">{{ $stat['value'] }}</div>
                <div class="small text-muted"><i class="bi bi-{{ $stat['icon'] }} me-1"></i>{{ $stat['label'] }}</div>
            </div>
        </a>
    </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body text-muted small">
        <i class="bi bi-info-circle me-1"></i>Merit List Version: <strong>{{ $latestVersion }}</strong>.
        Use "Regenerate" below only after confirming no active offer letters are linked to this program/batch list.
    </div>
</div>
@endif

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0">{{ $latestVersion ? 'Regenerate Merit List' : 'Generate Merit List' }}</h6>
    </div>
    <div class="card-body">
        @if(!$latestVersion)
        <div class="alert alert-info">
            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-2"></i>No merit list is generated for this program yet</div>
            <div class="small mb-0">Before generating, confirm shortlisted applicants, assessment scores, academic/entrance data, and seat matrix setup. The generated list becomes the source for selected, waitlisted, rejected, offer-letter, and seat-control workflows.</div>
        </div>
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
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Academic Weight: <span id="acad-val">20</span>%</label>
                        <input type="range" name="academic_weight" id="academic_weight" class="form-range"
                            min="0" max="60" step="5" value="20" oninput="updateWeights()">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Entrance Exam Weight: <span id="ent-val">30</span>%</label>
                        <input type="range" name="entrance_exam_weight" id="entrance_exam_weight" class="form-range"
                            min="0" max="60" step="5" value="30" oninput="updateWeights()">
                    </div>
                    <div class="text-muted small">Selection Process Weight: <span id="sel-val">50</span>% <span id="weight-warning" class="text-danger d-none">(must sum to 100%)</span></div>
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
@push('scripts')
<script>
function updateWeights() {
    const acad = parseInt(document.getElementById('academic_weight').value);
    const ent  = parseInt(document.getElementById('entrance_exam_weight').value);
    const sel  = 100 - acad - ent;
    document.getElementById('acad-val').textContent = acad;
    document.getElementById('ent-val').textContent  = ent;
    document.getElementById('sel-val').textContent  = sel;
    const warn = document.getElementById('weight-warning');
    warn.classList.toggle('d-none', sel >= 0);
}
</script>
@endpush
@endsection
