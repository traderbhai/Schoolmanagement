@extends('layouts.admin')
@section('title', 'Category Report — ' . $program->name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admission.merit-list.show', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Merit List</a>
        <h2 class="fw-bold mb-0 mt-1"><i class="bi bi-bar-chart me-2"></i>Category-Wise Report — {{ $program->name }}</h2>
    </div>
    <form method="GET" class="d-flex gap-2">
        <select name="batch_id" class="form-select form-select-sm" style="width:200px">
            <option value="">All Batches</option>
            @foreach($batches as $b)
                <option value="{{ $b->id }}" @selected($batchId == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>

@if(!$seatMatrix)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>No seat matrix configured for this program.
    <a href="{{ route('admin.seat-matrix.create', $program) }}" class="alert-link">Create Seat Matrix</a>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Category</th>
                        <th class="text-center">Total Seats</th>
                        <th class="text-center">Applied</th>
                        <th class="text-center">Scored</th>
                        <th class="text-center">Selected</th>
                        <th class="text-center">Vacant</th>
                        <th>Fill Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report as $cat => $row)
                    <tr>
                        <td class="fw-semibold">
                            {{ $row['label'] }}
                            @if(in_array($cat, ['pwd','nri']))
                                <small class="text-muted">(supernumerary)</small>
                            @endif
                        </td>
                        <td class="text-center"><strong>{{ $row['seats'] }}</strong></td>
                        <td class="text-center">{{ $row['applied'] }}</td>
                        <td class="text-center">{{ $row['scored'] }}</td>
                        <td class="text-center">
                            <span class="badge {{ $row['selected'] > 0 ? 'bg-success' : 'bg-secondary' }}">
                                {{ $row['selected'] }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($row['seats'] > 0)
                                <span class="badge {{ $row['vacant'] > 0 ? 'bg-warning text-dark' : 'bg-success' }}">
                                    {{ $row['vacant'] }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="min-width:160px">
                            @if($row['seats'] > 0)
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:12px">
                                    <div class="progress-bar {{ $row['fill_pct'] >= 80 ? 'bg-success' : ($row['fill_pct'] >= 40 ? 'bg-warning' : 'bg-danger') }}"
                                         style="width:{{ $row['fill_pct'] }}%"></div>
                                </div>
                                <span class="small text-muted">{{ $row['fill_pct'] }}%</span>
                            </div>
                            @else
                                <span class="text-muted small">N/A</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                @php
                    $totalSeats    = collect($report)->sum('seats');
                    $totalApplied  = collect($report)->sum('applied');
                    $totalScored   = collect($report)->sum('scored');
                    $totalSelected = collect($report)->sum('selected');
                    $totalVacant   = collect($report)->sum('vacant');
                @endphp
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center">{{ $totalSeats }}</td>
                        <td class="text-center">{{ $totalApplied }}</td>
                        <td class="text-center">{{ $totalScored }}</td>
                        <td class="text-center">{{ $totalSelected }}</td>
                        <td class="text-center">{{ $totalVacant }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
