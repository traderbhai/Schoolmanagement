@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1">{{ $title }}</h1><div class="small text-muted">{{ $description }}</div></div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small">Program</label><select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">All</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->code ?: $program->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Batch</label><select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">All</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}" @selected((string) request('batch_id') === (string) $batch->id)>{{ $batch->code ?: $batch->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Term</label><select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">All</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}" @selected((string) request('term_id') === (string) $term->id)>{{ $term->name }}</option>@endforeach</select></div>
                <div class="col-md-4 d-flex gap-1"><button class="btn btn-sm btn-primary">Audit</button><a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.timetable-clashes.export', request()->query()) }}">Export</a></div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @foreach([
            'students_not_assigned' => 'Students Not Assigned To Groups',
            'overlapping_group_students' => 'Students With Overlapping Scheduled Groups',
            'elective_clash_matrix' => 'Elective Clash Matrix',
            'unrelated_parallel_electives' => 'Unrelated Parallel Electives',
            'strength_mismatches' => 'Group Strength Mismatches',
        ] as $key => $label)
            @php($section = $audit[$key] ?? ['count' => 0, 'rows' => [], 'status' => 'ready', 'message' => ''])
            <div class="col-xl-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 d-flex justify-content-between gap-2">
                        <div><div class="fw-semibold">{{ $label }}</div><div class="small text-muted">{{ $section['message'] }}</div></div>
                        <span class="badge text-bg-{{ $section['status'] === 'ready' ? 'success' : 'warning' }}">{{ $section['count'] }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th scope="col">Record</th><th scope="col">Detail</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @forelse($section['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['student'] ?? $row['group'] ?? $row['pair'] ?? $row['label'] ?? $row['key'] ?? '-' }}</td>
                                        <td class="small text-muted">
                                            @foreach($row as $field => $value)
                                                @if(!in_array($field, ['student', 'group', 'pair', 'label', 'key', 'status'], true))
                                                    {{ str_replace('_', ' ', $field) }}: {{ is_array($value) ? json_encode($value) : $value }}@if(!$loop->last) | @endif
                                                @endif
                                            @endforeach
                                        </td>
                                        <td>{{ $row['status'] ?? $section['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">No audit rows.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
