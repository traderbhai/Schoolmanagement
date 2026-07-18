@extends('layouts.admin')
@section('title', 'Dean Academic Calendar')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Academic Calendar</h1><div class="small text-muted">Reviews, action due dates, exams, curriculum due dates, and handoff priorities.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Date</th><th scope="col">Bucket</th><th scope="col">Type</th><th scope="col">Event</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead><tbody>
        @foreach($events as $event)
            <tr><td>{{ $event['date']->toDateString() }}</td><td><span class="badge text-bg-light">{{ $event['bucket'] }}</span></td><td>{{ $event['type'] }}</td><td class="fw-semibold">{{ $event['title'] }}</td><td>{{ str_replace('_',' ', $event['status']) }}</td><td class="text-end"><a href="{{ $event['route'] }}" class="btn btn-sm btn-outline-primary">Open {{ $event['title'] }}</a></td></tr>
        @endforeach
    </tbody></table></div></div>
</div>
@endsection
