@extends('layouts.admin')

@section('title', 'Admission Calendar')

@section('content')
<h3 class="fw-bold mb-3">Admission Calendar</h3>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Title</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead>
            <tbody>
            @foreach($events as $event)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($event['starts_at'])->format('d M Y H:i') }}</td>
                    <td><span class="badge bg-info text-dark">{{ str_replace('_', ' ', $event['type']) }}</span></td>
                    <td class="fw-semibold">{{ $event['title'] }}</td>
                    <td>{{ ucfirst($event['status']) }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ $event['route'] }}">Open event</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
