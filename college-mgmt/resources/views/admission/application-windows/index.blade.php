@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Application Windows - {{ $program->name }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admission.application-windows.create', $program) }}" class="btn btn-primary">New Window</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total</h5>
                    <h2 class="text-info">{{ $stats['total'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Active</h5>
                    <h2 class="text-success">{{ $stats['active'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Currently Open</h5>
                    <h2 class="text-primary">{{ $stats['open'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Programs</h5>
                    <h2 class="text-warning">{{ $program->name }}</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Windows Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Batch</th>
                        <th>Opens</th>
                        <th>Closes</th>
                        <th>Capacity</th>
                        <th>Applications</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($windows as $window)
                        <tr>
                            <td>{{ $window->batch?->name ?? 'All Batches' }}</td>
                            <td>{{ $window->opens_at->format('d M Y H:i') }}</td>
                            <td>{{ $window->closes_at->format('d M Y H:i') }}</td>
                            <td>
                                @if($window->capacity_limit)
                                    {{ $window->capacity_limit }}
                                @else
                                    <span class="text-muted">Unlimited</span>
                                @endif
                            </td>
                            <td>
                                {{ $window->current_applications }}
                                @if($window->capacity_limit)
                                    / {{ $window->capacity_limit }}
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" style="width: {{ ($window->current_applications / $window->capacity_limit) * 100 }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $window->status_badge }}">{{ ucfirst(str_replace('_', ' ', $window->status)) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admission.application-windows.edit', $window) }}" class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('admission.application-windows.toggle', $window) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $window->is_active ? 'btn-warning' : 'btn-success' }}">
                                        {{ $window->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form action="{{ route('admission.application-windows.destroy', $window) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this window?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No application windows found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $windows->links() }}
</div>
@endsection
