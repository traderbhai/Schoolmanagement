@extends('layouts.admin')
@section('title', 'Admission Attention Queues')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h4 mb-1">Attention Queues</h1>
    <div class="text-muted mb-4">Role-scoped admission work needing immediate action.</div>
    <div class="row g-4">
        @foreach($queues as $key => $items)
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <span class="fw-semibold">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                        <span class="badge bg-primary">{{ count($items) }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($items as $item)
                            <a class="list-group-item list-group-item-action" href="{{ $item['route'] }}">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $item['title'] }}</strong>
                                    <span class="badge bg-{{ $item['severity'] }}">{{ $item['severity'] }}</span>
                                </div>
                                <div class="small text-muted">{{ $item['reason'] }} - {{ $item['recommended_action'] }}</div>
                            </a>
                        @empty
                            <div class="list-group-item text-muted">No items.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
