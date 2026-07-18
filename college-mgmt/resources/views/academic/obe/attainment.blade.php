@extends('layouts.admin')
@section('title', 'OBE Attainment Dashboard')
@section('page-title', 'Attainment Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('academic.obe.co.index') }}">OBE Framework</a></li>
    <li class="breadcrumb-item active">Attainment</li>
@endsection

@push('styles')
<style>
.attainment-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
.attainment-bar-fill { height: 100%; border-radius: 4px; transition: width .5s; }
.co-card { border-left: 3px solid #0d6efd; }
.met { color: #198754; }
.not-met { color: #dc3545; }
</style>
@endpush

@section('content')
{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-sm-4">
                <label class="form-label small mb-1">Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Program —</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Term</label>
                <select aria-label="Term" name="term_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Term —</option>
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected(request('term_id') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            @if(request('program_id') && request('term_id'))
            <div class="col-sm-auto">
                <form method="POST" action="{{ route('academic.obe.attainment.recalculate') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="program_id" value="{{ request('program_id') }}">
                    <input type="hidden" name="term_id" value="{{ request('term_id') }}">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>Recalculate
                    </button>
                </form>
            </div>
            @endif
        </form>
    </div>
</div>

@if($program && $term)
{{-- PO Attainment Summary --}}
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">
                PO Attainment — {{ $program->name }} | {{ $term->name }}
            </div>
            <div class="card-body">
                @if($poAttainments->isEmpty())
                    <div class="text-muted text-center py-3">
                        No PO attainment data yet.
                        <a href="{{ route('academic.obe.attainment.recalculate') }}" class="text-decoration-none">Click Recalculate above</a>
                        after entering exam results.
                    </div>
                @else
                <div class="row g-2">
                    @foreach($poAttainments as $pa)
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="p-2 border rounded" style="font-size:.8rem">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold">{{ $pa->programOutcome->code ?? 'PO?' }}</span>
                                <span class="{{ $pa->target_met ? 'met' : 'not-met' }}">
                                    {{ number_format($pa->attainment_value, 1) }}%
                                    <i class="bi bi-{{ $pa->target_met ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                                </span>
                            </div>
                            <div class="attainment-bar">
                                <div class="attainment-bar-fill"
                                     style="width:{{ min(100, $pa->attainment_value) }}%;background:{{ $pa->target_met ? '#198754' : '#dc3545' }}"></div>
                            </div>
                            <small class="text-muted">Target: {{ $pa->target_value }}%</small>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- CO Attainment by Subject --}}
@foreach($subjects as $subject)
@php
    $allCos = $subject->courseOutcomes;
    $hasData = $allCos->filter(fn($co) => $co->attainments->isNotEmpty())->isNotEmpty();
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom d-flex justify-content-between">
        <span class="fw-semibold">{{ $subject->name }} <small class="text-muted">{{ $subject->code }}</small></span>
        <a href="{{ route('academic.obe.co.index') }}?program_id={{ $program->id }}&subject_id={{ $subject->id }}"
           class="btn btn-xs btn-outline-secondary">Manage COs</a>
    </div>
    <div class="card-body">
        @if(!$hasData)
            <small class="text-muted">No attainment data. Results must be entered first.</small>
        @else
        <div class="row g-2">
            @foreach($allCos as $co)
            @php $att = $co->attainments->first(); @endphp
            @if($att)
            <div class="col-sm-6 col-lg-4">
                <div class="p-2 border rounded co-card" style="font-size:.8rem">
                    <div class="d-flex justify-content-between mb-1">
                        <span><span class="badge bg-primary me-1">{{ $co->code }}</span>
                            <small class="text-muted">{{ Str::limit($co->description, 40) }}</small></span>
                        <span class="{{ $att->target_met ? 'met' : 'not-met' }} fw-semibold">
                            {{ number_format($att->final_attainment, 1) }}%
                        </span>
                    </div>
                    <div class="attainment-bar mb-1">
                        <div class="attainment-bar-fill"
                             style="width:{{ min(100, $att->final_attainment) }}%;background:{{ $att->target_met ? '#198754' : '#dc3545' }}"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:.7rem;color:#666">
                        <span>Direct: {{ $att->direct_attainment }}%</span>
                        <span>Indirect: {{ $att->indirect_attainment }}%</span>
                        <span>{{ $att->students_attained }}/{{ $att->students_assessed }} students</span>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        @endif
    </div>
</div>
@endforeach

@if($subjects->isEmpty())
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No subjects with Course Outcomes found for this program.
    <a href="{{ route('academic.obe.co.index') }}?program_id={{ $program->id }}" class="alert-link">Define Course Outcomes</a> first.
</div>
@endif

@else
<div class="text-center text-muted py-5">
    <i class="bi bi-bar-chart-line fs-1 d-block mb-2"></i>
    Select a program and term to view attainment data.
</div>
@endif
@endsection
