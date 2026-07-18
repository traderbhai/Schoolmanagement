@extends('layouts.admin')
@section('title','Faculty Roster')
@section('page-title','Faculty Roster')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">{{ $department?->name ?? 'Department' }} — Faculty</h5>
    <a href="{{ route('hod.faculty.workload') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart me-1"></i>Workload View</a>
</div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th scope="col" class="ps-3">Name</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Subjects</th><th scope="col">Weekly Slots</th></tr></thead>
      <tbody>
        @forelse($faculty as $t)
        <tr>
          <td class="ps-3 fw-medium">{{ $t->user?->name ?? 'N/A' }}</td>
          <td class="text-muted small">{{ $t->user?->email ?? '' }}</td>
          <td><span class="badge bg-{{ $t->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($t->status) }}</span></td>
          <td>{{ $t->subject_count }}</td>
          <td>{{ $t->weekly_hours }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No faculty found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
