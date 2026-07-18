@extends('layouts.admin')
@section('title', $queueData['title'])

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $queueData['title'] }}</h1>
            <div class="text-muted small">{{ $queueData['description'] }}</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('academics.command-center.index') }}">
            <i class="bi bi-arrow-left"></i> Command Center
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold">Filtered Source List</span>
            <span class="badge text-bg-light">{{ $queueData['count'] }} open</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Status</th>
                        <th scope="col">Due / Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queueData['items'] as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item['title'] }}</div>
                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                            </td>
                            <td>{{ $item['status'] }}</td>
                            <td>{{ $item['due'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <div class="fw-semibold text-body">No open records for this queue</div>
                                <div class="small">
                                    This means your current Academics scope has no unresolved items for
                                    <span class="fw-semibold">{{ $queueData['title'] }}</span>.
                                    Review the Command Center for other branches, or create/update the source workflow when a new blocker appears.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
