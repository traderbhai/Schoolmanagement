@extends('layouts.admin')

@section('title', 'Application Windows — ' . $program->name)
@section('page-title', 'Application Windows')

@section('content')
<div class="container-fluid py-4">

    {{-- Header Row --}}
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h1 class="h3 fw-bold mb-0">Application Windows</h1>
            <p class="text-muted small mb-0 mt-1">{{ $program->name }}</p>
        </div>
        <div class="col-md-6 text-md-end d-flex align-items-center justify-content-md-end gap-3 flex-wrap">
            {{-- Program Switcher --}}
            <div class="d-flex align-items-center gap-2">
                <label for="programSwitcher" class="form-label mb-0 small fw-semibold text-nowrap">Switch Program:</label>
                <select id="programSwitcher" class="form-select form-select-sm" style="min-width: 180px;"
                    onchange="window.location = this.value;">
                    @php
                        $allPrograms = \App\Models\Program::where('is_active', true)->orderBy('name')->get();
                    @endphp
                    @foreach($allPrograms as $prog)
                        <option value="{{ route('admission.application-windows.index', $prog) }}"
                            {{ $prog->id === $program->id ? 'selected' : '' }}>
                            {{ $prog->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('admission.application-windows.create', $program) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Window
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Statistics Cards --}}
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;">Total</div>
                    <div class="fw-bold text-info" style="font-size: 2rem;">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;">Active</div>
                    <div class="fw-bold text-success" style="font-size: 2rem;">{{ $stats['active'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;">Currently Open</div>
                    <div class="fw-bold text-primary" style="font-size: 2rem;">{{ $stats['open'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;">Program</div>
                    <div class="fw-bold text-warning" style="font-size: 1.1rem; line-height: 1.3; margin-top: .35rem;">{{ $program->name }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Application Windows Table --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar-range me-2"></i>Windows</h5>
            <span class="badge bg-secondary">{{ $windows->total() }} total</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Batch</th>
                        <th>Opens</th>
                        <th>Closes</th>
                        <th>Capacity Meter</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($windows as $window)
                        <tr>
                            {{-- Batch --}}
                            <td class="fw-semibold">{{ $window->batch?->name ?? 'All Batches' }}</td>

                            {{-- Opens / Closes --}}
                            <td class="small">{{ $window->opens_at->format('d M Y') }}<br><span class="text-muted">{{ $window->opens_at->format('H:i') }}</span></td>
                            <td class="small">{{ $window->closes_at->format('d M Y') }}<br><span class="text-muted">{{ $window->closes_at->format('H:i') }}</span></td>

                            {{-- Capacity Meter --}}
                            <td style="min-width: 150px;">
                                @if($window->capacity_limit)
                                    @php
                                        $pct     = min(100, ($window->current_applications / $window->capacity_limit) * 100);
                                        $barColor = $pct > 90 ? '#dc3545' : ($pct >= 70 ? '#ffc107' : '#198754');
                                    @endphp
                                    <div class="progress mb-1" style="height: 8px; border-radius: 4px;">
                                        <div class="progress-bar"
                                            role="progressbar"
                                            style="width: {{ $pct }}%; background-color: {{ $barColor }};"
                                            aria-valuenow="{{ $pct }}"
                                            aria-valuemin="0"
                                            aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="small text-muted">
                                        {{ $window->current_applications }} / {{ $window->capacity_limit }}
                                        <span class="ms-1">({{ number_format($pct, 0) }}%)</span>
                                    </div>
                                @else
                                    <span class="text-muted small"><i class="bi bi-infinity me-1"></i>Unlimited</span>
                                @endif
                            </td>

                            {{-- Status Chip --}}
                            <td>
                                @if(!$window->is_active)
                                    <span class="badge bg-dark">Inactive</span>
                                @elseif($window->isOpen())
                                    <span class="badge bg-success">Open</span>
                                @elseif($window->isClosed())
                                    <span class="badge bg-danger">Closed</span>
                                @elseif($window->isNotYetOpen())
                                    <span class="badge bg-secondary">Not Yet Open</span>
                                @else
                                    <span class="badge bg-secondary">Unknown</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="{{ route('admission.application-windows.edit', $window) }}"
                                        class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form action="{{ route('admission.application-windows.toggle', $window) }}"
                                        method="POST" style="display: inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="btn btn-sm {{ $window->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $window->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admission.application-windows.destroy', $window) }}"
                                        method="POST" style="display: inline;"
                                        onsubmit="return confirm('Delete this window? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                No application windows found for this program.
                                <br>
                                <a href="{{ route('admission.application-windows.create', $program) }}"
                                    class="btn btn-primary btn-sm mt-3">
                                    <i class="bi bi-plus-lg me-1"></i>Create First Window
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($windows->hasPages())
            <div class="card-footer py-3">
                {{ $windows->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
