@extends('layouts.admin')
@section('title', $queue['label'])

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="h4 mb-1">{{ $queue['label'] }}</h1><div class="small text-muted">Filtered source list with severity, owner, due date, and recommended action.</div></div>
        @include('academics.dean-os.partials.nav')
    </div>
    <div class="card shadow-sm mb-3"><div class="card-body py-2 small text-muted">Visible filter: queue = {{ $queue['key'] }} | Records = {{ $queue['count'] }} <a class="ms-2" href="{{ route('academics.dean-os.export', $queue['key']) }}">Export Current View</a></div></div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Record</th><th>Severity</th><th>Owner</th><th>Due</th><th>Recommended Action</th><th></th></tr></thead>
                <tbody>
                @forelse($queue['items'] as $item)
                    <tr>
                        <td><div class="fw-semibold">{{ $item['title'] }}</div><div class="small text-muted">{{ $item['subtitle'] }}</div></td>
                        <td><span class="badge text-bg-{{ $item['severity'] === 'critical' ? 'danger' : ($item['severity'] === 'high' ? 'warning' : 'light') }}">{{ $item['severity'] }}</span></td>
                        <td class="small">{{ $item['owner'] ?? 'Unassigned' }}</td>
                        <td class="small">{{ $item['due'] ?? '-' }}</td>
                        <td class="small">{{ $item['action'] }}</td>
                        <td class="text-end"><a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-primary">Open source</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No records in this queue.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
