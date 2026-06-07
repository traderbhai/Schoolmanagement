@extends('layouts.admin')
@section('title','Event Registrations')
@section('page-title','Event Registrations')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0">{{ $event->title }}</h5>
    <div class="text-muted small">{{ ucwords(str_replace('-',' ',$event->event_type)) }} · {{ $event->event_date->format('d M Y') }} · {{ $event->venue ?? 'TBD' }}</div>
  </div>
  <a href="{{ route('cmc.events') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:.85rem;">
  <i class="bi bi-people"></i> <strong>{{ $registrations->count() }}</strong> registration(s)
  @if($event->seats) &nbsp;/ {{ $event->seats }} seats @endif
</div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">#</th><th>Student Name</th><th>Enrollment No.</th><th>Registered On</th></tr>
      </thead>
      <tbody>
        @forelse($registrations as $reg)
        <tr>
          <td class="ps-3">{{ $loop->iteration }}</td>
          <td class="fw-medium">{{ $reg->student?->user?->name ?? '—' }}</td>
          <td class="small">{{ $reg->student?->enrollment_number ?? '—' }}</td>
          <td class="small text-muted">{{ $reg->created_at->format('d M Y H:i') }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="text-center text-muted py-4">No registrations yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
