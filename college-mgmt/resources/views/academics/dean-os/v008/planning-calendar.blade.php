@extends('layouts.admin')
@section('title', 'Dean Planning Calendar')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Interactive Planning Calendar</h1><div class="small text-muted">Today, week, term, list views with owners, dependencies, reminders, filters, and source links.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-2 mb-3">@foreach(['Today'=>$today,'This Week'=>$week,'Overdue'=>$overdue] as $label=>$value)<div class="col-md-4"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>@endforeach</div>
    <div class="card shadow-sm"><div class="card-header py-2 small text-muted">Visible filter summary: event_type={{ request('event_type','all') }}, status={{ request('status','all') }}</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Event</th><th>Type</th><th>Status</th><th>Starts</th><th>Source</th></tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->title }}</td><td>{{ str_replace('_',' ', $event->event_type) }}</td><td>{{ $event->status }}</td><td>{{ optional($event->starts_at)->format('d M Y H:i') }}</td><td>{{ $event->source_type }} {{ $event->source_key }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $events->links() }}</div></div>
</div>
@endsection
