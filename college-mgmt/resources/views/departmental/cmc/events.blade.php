@extends('layouts.admin')
@section('title','Career Events')
@section('page-title','Career Events')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="GET" class="d-flex gap-2">
    <select aria-label="Type" name="type" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
      <option value="">All Types</option>
      @foreach(\App\Models\CareerEvent::TYPE_LABELS as $t => $label)
      <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ $label }}</option>
      @endforeach
    </select>
  </form>
  <div class="d-flex gap-2">
    <a href="{{ route('cmc.events.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
    <a href="{{ route('cmc.events.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>New Event</a>
  </div>
</div>
<div class="text-muted small mb-2">Showing {{ $events->total() }} career event(s){{ request('type') ? ' filtered by type: '.request('type') : '' }}.</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th scope="col" class="ps-3">Title</th><th scope="col">Type</th><th scope="col">Date</th><th scope="col">Venue</th><th scope="col">Seats</th><th scope="col">Published</th><th scope="col" class="text-end pe-3">Actions</th></tr>
      </thead>
      <tbody>
        @forelse($events as $e)
        <tr>
          <td class="ps-3 fw-medium">{{ $e->title }}</td>
          <td class="small text-muted">{{ \App\Models\CareerEvent::TYPE_LABELS[$e->event_type] ?? ucwords(str_replace('_',' ',$e->event_type)) }}</td>
          <td class="small">{{ $e->event_date->format('d M Y') }}</td>
          <td class="small text-muted">{{ $e->venue ?? '-' }}</td>
          <td>{{ $e->seats ?? 'Open' }}</td>
          <td><span class="badge bg-{{ $e->is_published ? 'success' : 'secondary' }}">{{ $e->is_published ? 'Yes' : 'Draft' }}</span></td>
          <td class="text-end pe-3">
            <a href="{{ route('cmc.events.registrations', $e) }}" class="btn btn-sm btn-outline-info py-0 px-2" title="Registrations">
              <i class="bi bi-people"></i>
            </a>
            <a href="{{ route('cmc.events.edit', $e) }}" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            @if(($e->total_registrations_count ?? $e->registrations_count ?? 0) > 0)
            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1" disabled title="Registered events cannot be deleted">
              <i class="bi bi-shield-lock"></i>
            </button>
            @else
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 ms-1"
                data-bs-toggle="modal" data-bs-target="#deleteModal"
                data-action="{{ route('cmc.events.destroy', $e) }}"
                data-name="{{ $e->title }}" title="Delete">
              <i class="bi bi-trash3"></i>
            </button>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No career events found.</td></tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
</div>
{{ $events->withQueryString()->links() }}
@endsection
