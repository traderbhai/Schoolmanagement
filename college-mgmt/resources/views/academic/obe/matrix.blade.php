@extends('layouts.admin')
@section('title', 'CO-PO Mapping Matrix')
@section('page-title', 'CO-PO Mapping Matrix')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('academic.obe.co.index') }}">OBE Framework</a></li>
    <li class="breadcrumb-item active">CO-PO Matrix</li>
@endsection

@push('styles')
<style>
.matrix-table th, .matrix-table td { font-size: .72rem; padding: 4px 6px; text-align: center; white-space: nowrap; }
.matrix-table th.co-col { text-align: left; min-width: 160px; white-space: normal; }
.matrix-cell select { width: 52px; padding: 1px 2px; font-size: .7rem; border-radius: 4px; border: 1px solid #dee2e6; }
.level-0 { background: #f8f9fa; color: #999; }
.level-1 { background: #fff3cd; color: #856404; font-weight: 600; }
.level-2 { background: #cff4fc; color: #055160; font-weight: 600; }
.level-3 { background: #d1e7dd; color: #0f5132; font-weight: 700; }
</style>
@endpush

@section('content')
{{-- Filter bar --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-sm-4">
                <label class="form-label small mb-1">Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Program —</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" @selected($programId == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($programId && $subjects->isNotEmpty())
            <div class="col-sm-4">
                <label class="form-label small mb-1">Subject (for COs)</label>
                <select aria-label="Subject" name="subject_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Subject —</option>
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}" @selected($subjectId == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </form>
    </div>
</div>

@if($cos->isNotEmpty() && $pos->isNotEmpty())
<form method="POST" action="{{ route('academic.obe.matrix.save') }}" id="matrixForm">
    @csrf
    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    <input type="hidden" name="program_id" value="{{ $programId }}">

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <div>
                <span class="fw-semibold">CO-PO Correlation Matrix</span>
                <small class="text-muted ms-2">0 = No correlation, 1 = Low, 2 = Medium, 3 = High</small>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-save me-1"></i>Save Matrix
            </button>
        </div>
        <div class="card-body p-0 overflow-x-auto">
            <table class="matrix-table table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="co-col">Course Outcome</th>
                        @foreach($pos as $po)
                            <th scope="col" title="{{ $po->description }}">{{ $po->code }}</th>
                        @endforeach
                        @foreach($psos as $pso)
                            <th scope="col" class="table-warning" title="{{ $pso->description }}">{{ $pso->code }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($cos as $co)
                    @php
                        $coMappings = $mappings->get($co->id, collect())->keyBy(function($m) {
                            return $m->program_outcome_id ? 'po_'.$m->program_outcome_id : 'pso_'.$m->program_specific_outcome_id;
                        });
                    @endphp
                    <tr>
                        <td class="co-col text-start">
                            <span class="badge bg-primary me-1">{{ $co->code }}</span>
                            <small class="text-muted">{{ Str::limit($co->description, 60) }}</small>
                        </td>
                        @foreach($pos as $po)
                        @php $level = $coMappings->get('po_'.$po->id)?->correlation_level ?? 0; @endphp
                        <td class="matrix-cell level-{{ $level }}" id="cell-{{ $co->id }}-po-{{ $po->id }}">
                            <select aria-label="Correlation level for {{ $co->code }} and {{ $po->code }}" name="mappings[{{ $co->id }}][po_{{ $po->id }}]"
                                    onchange="updateCell(this, 'cell-{{ $co->id }}-po-{{ $po->id }}')">
                                @for($i = 0; $i <= 3; $i++)
                                    <option value="{{ $i }}" @selected($level == $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </td>
                        @endforeach
                        @foreach($psos as $pso)
                        @php $level = $coMappings->get('pso_'.$pso->id)?->correlation_level ?? 0; @endphp
                        <td class="matrix-cell level-{{ $level }}" id="cell-{{ $co->id }}-pso-{{ $pso->id }}">
                            <select aria-label="Correlation level for {{ $co->code }} and {{ $pso->code }}" name="mappings[{{ $co->id }}][pso_{{ $pso->id }}]"
                                    onchange="updateCell(this, 'cell-{{ $co->id }}-pso-{{ $pso->id }}')">
                                @for($i = 0; $i <= 3; $i++)
                                    <option value="{{ $i }}" @selected($level == $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    {{-- Average row --}}
                    <tr class="table-light fw-semibold">
                        <td class="co-col text-start"><small>Avg Correlation</small></td>
                        @foreach($pos as $po)
                        <td id="avg-po-{{ $po->id }}">—</td>
                        @endforeach
                        @foreach($psos as $pso)
                        <td id="avg-pso-{{ $pso->id }}">—</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top">
            <div class="d-flex gap-3 flex-wrap">
                <span class="badge level-0" style="font-size:.75rem;padding:4px 8px;">0 — No correlation</span>
                <span class="badge level-1 border" style="font-size:.75rem;padding:4px 8px;">1 — Low</span>
                <span class="badge level-2 border" style="font-size:.75rem;padding:4px 8px;">2 — Medium</span>
                <span class="badge level-3 border" style="font-size:.75rem;padding:4px 8px;">3 — High</span>
            </div>
        </div>
    </div>
</form>
@elseif($programId && $subjectId)
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No Course Outcomes defined for this subject, or no Program Outcomes defined for this program.
    <a href="{{ route('academic.obe.co.index') }}?program_id={{ $programId }}&subject_id={{ $subjectId }}" class="alert-link">Add Course Outcomes</a>
    or
    <a href="{{ route('academic.obe.po.index') }}?program_id={{ $programId }}" class="alert-link">Add Program Outcomes</a>.
</div>
@else
<div class="text-center text-muted py-5">
    <i class="bi bi-grid-3x3 fs-1 d-block mb-2"></i>
    Select a program and subject to view the CO-PO mapping matrix.
</div>
@endif
@endsection

@push('scripts')
<script>
function updateCell(select, cellId) {
    const cell = document.getElementById(cellId);
    cell.className = cell.className.replace(/level-\d/, 'level-' + select.value);
    computeAverages();
}

function computeAverages() {
    const table = document.querySelector('.matrix-table');
    if (!table) return;

    const headers = table.querySelectorAll('thead th');
    const bodies  = table.querySelectorAll('tbody tr:not(:last-child)');

    headers.forEach((th, colIdx) => {
        if (colIdx === 0) return;
        const key = th.textContent.trim();
        let sum = 0, count = 0;
        bodies.forEach(row => {
            const cell = row.cells[colIdx];
            if (!cell) return;
            const sel = cell.querySelector('select');
            if (sel) { sum += parseInt(sel.value); count++; }
        });
        const avgCell = key.startsWith('PSO')
            ? document.getElementById('avg-pso-' + key.replace('PSO', '').trim())
            : null;
        const poMatch = key.match(/^PO(\d+)/);
        const avgId = poMatch
            ? 'avg-po-' + [...document.querySelectorAll('thead th')].find(h => h.textContent.trim() === key)?.dataset?.poId
            : null;

        // Use simpler approach: find by column index in avg row
        const avgRow = table.querySelector('tbody tr:last-child');
        if (avgRow && avgRow.cells[colIdx]) {
            const avg = count > 0 ? (sum / count).toFixed(2) : '—';
            avgRow.cells[colIdx].textContent = avg;
        }
    });
}

document.addEventListener('DOMContentLoaded', computeAverages);
</script>
@endpush
