@extends('layouts.admin')
@section('title','Event Registrations')
@section('page-title','Event Registrations')
@section('content')
@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0">{{ $event->title }}</h5>
    <div class="text-muted small">{{ ucwords(str_replace('_',' ',$event->event_type)) }} · {{ $event->event_date->format('d M Y') }} · {{ $event->venue ?? 'Venue pending' }}</div>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('cmc.events.registrations.export', $event) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
    <a href="{{ route('cmc.events') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="alert alert-info py-2 px-3 mb-3 d-flex flex-wrap align-items-center gap-3" style="font-size:.85rem;">
  <span><i class="bi bi-people me-1"></i><strong>{{ $registrations->where('status', 'registered')->count() }}</strong> active registration(s)</span>
  @if($event->seats)
    <span>{{ $event->seats }} seats</span>
  @endif
  <span class="text-success">{{ $registrations->where('attended', true)->where('status', 'registered')->count() }} attended</span>
  <span class="text-muted">{{ $registrations->where('attended', false)->where('status', 'registered')->count() }} pending attendance</span>
  <span class="text-secondary">{{ $registrations->where('status', 'cancelled')->count() }} cancelled</span>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">#</th>
            <th>Student Name</th>
            <th>Enrollment No.</th>
            <th>Registered On</th>
            <th>Status</th>
            <th class="text-end pe-3">Attendance</th>
          </tr>
        </thead>
        <tbody>
          @forelse($registrations as $reg)
          <tr>
            <td class="ps-3">{{ $loop->iteration }}</td>
            <td class="fw-medium">{{ $reg->student?->user?->name ?? '-' }}</td>
            <td class="small">{{ $reg->student?->enrollment_number ?? '-' }}</td>
            <td class="small text-muted">{{ $reg->created_at->format('d M Y H:i') }}</td>
            <td>
              @if($reg->status === 'cancelled')
                <span class="badge bg-secondary">Cancelled</span>
              @else
                <span class="badge bg-{{ $reg->attended ? 'success' : 'secondary' }}">{{ $reg->attended ? 'Attended' : 'Registered' }}</span>
              @endif
            </td>
            <td class="text-end pe-3">
              @if($reg->status === 'cancelled')
                <span class="badge bg-secondary-subtle text-secondary">Cancelled history</span>
              @elseif($reg->attended)
                <span class="badge bg-success-subtle text-success">Attendance locked</span>
              @else
                <form method="POST" action="{{ route('cmc.events.registrations.attendance', [$event, $reg]) }}" class="d-inline">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="attended" value="1">
                  <button class="btn btn-sm btn-outline-success py-0 px-2">
                    Mark attended
                  </button>
                </form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No registrations yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
