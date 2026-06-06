@extends('layouts.admin')
@section('title', 'Placement Analytics')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Placement Analytics</h4>
            <span class="text-muted small">Program-wise and company-wise placement statistics</span>
        </div>
        <a href="{{ route('cmc.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Placements by Program</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Program</th>
                                    <th class="text-end pe-3">Placed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($byProgram as $row)
                                <tr>
                                    <td class="ps-3">{{ $row->program_name }}</td>
                                    <td class="text-end pe-3">
                                        <span class="badge bg-success-subtle text-success px-2">{{ $row->placed_count }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">No placement data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Drive Status Overview</h6>
                </div>
                <div class="card-body">
                    @php
                        $statusColors = ['open'=>'success','active'=>'primary','closed'=>'secondary','completed'=>'info'];
                    @endphp
                    @forelse($driveStats as $status => $count)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="badge bg-{{ $statusColors[$status] ?? 'secondary' }}-subtle text-{{ $statusColors[$status] ?? 'secondary' }} px-3">
                            {{ ucfirst($status) }}
                        </span>
                        <span class="fw-bold">{{ $count }}</span>
                    </div>
                    @empty
                    <div class="text-muted small fst-italic">No drive data.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
