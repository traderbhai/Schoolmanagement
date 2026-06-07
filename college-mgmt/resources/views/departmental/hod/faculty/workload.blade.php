@extends('layouts.admin')
@section('title','Faculty Workload')
@section('page-title','Faculty Workload')
@section('content')
<div class="d-flex justify-content-between mb-3">
  <h5 class="mb-0">{{ $department?->name ?? 'Department' }} — Workload</h5>
  <a href="{{ route('hod.faculty.roster') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Roster</a>
</div>
<div class="row g-3">
@forelse($faculty as $t)
<div class="col-md-6 col-lg-4">
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-center mb-2">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:38px;height:38px;font-size:.85rem;font-weight:700;">
          {{ strtoupper(substr($t->user?->name ?? 'T', 0, 2)) }}
        </div>
        <div>
          <div class="fw-semibold" style="font-size:.9rem;">{{ $t->user?->name ?? 'N/A' }}</div>
          <div class="text-muted" style="font-size:.75rem;">{{ $t->subject_count }} subject(s)</div>
        </div>
      </div>
      <div class="mb-1 small">Weekly Slots: <strong>{{ $t->weekly_slots }}</strong>/20</div>
      <div class="progress mb-2" style="height:6px;">
        <div class="progress-bar bg-{{ $t->weekly_slots > 16 ? 'danger' : ($t->weekly_slots > 10 ? 'warning' : 'success') }}"
             style="width:{{ min(100, $t->weekly_slots / 20 * 100) }}%"></div>
      </div>
      @if($t->subjects->count())
      <div class="text-muted" style="font-size:.75rem;">{{ $t->subjects->implode(', ') }}</div>
      @endif
    </div>
  </div>
</div>
@empty
<div class="col-12"><div class="alert alert-info">No active faculty found.</div></div>
@endforelse
</div>
@endsection
