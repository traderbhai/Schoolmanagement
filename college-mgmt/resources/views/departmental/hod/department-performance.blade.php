@extends('layouts.admin')
@section('title','Department Performance')
@section('page-title','Department Performance')
@section('content')
<div class="mb-3">
    <h5 class="mb-1">{{ $department?->name ?? 'Department' }} — Academic Performance</h5>
    <div class="text-muted small">{{ $totalStudents }} active students</div>
</div>

@php
$avgPass = $subjects->whereNotNull('pass_rate')->avg('pass_rate');
$avgAtt  = $subjects->whereNotNull('attendance_pct')->avg('attendance_pct');
@endphp
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="fs-3 fw-bold">{{ $subjects->count() }}</div>
      <div class="text-muted small">Total Subjects</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="fs-3 fw-bold">{{ $totalStudents }}</div>
      <div class="text-muted small">Active Students</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="fs-3 fw-bold {{ $avgPass < 50 ? 'text-danger' : ($avgPass < 70 ? 'text-warning' : 'text-success') }}">
        {{ $avgPass ? round($avgPass,1).'%' : '—' }}</div>
      <div class="text-muted small">Avg Pass Rate</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="fs-3 fw-bold {{ $avgAtt < 75 ? 'text-danger' : 'text-success' }}">
        {{ $avgAtt ? round($avgAtt,1).'%' : '—' }}</div>
      <div class="text-muted small">Avg Attendance</div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th scope="col" class="ps-3">Subject</th><th scope="col">Program</th><th scope="col">Exams</th><th scope="col">Avg Marks</th><th scope="col">Pass Rate</th><th scope="col">Attendance</th></tr>
      </thead>
      <tbody>
        @forelse($subjects as $s)
        <tr>
          <td class="ps-3 fw-medium">{{ $s->name }}</td>
          <td class="small text-muted">{{ $s->program?->name ?? '—' }}</td>
          <td>{{ $s->exam_count }}</td>
          <td>
            @if($s->avg_marks !== null)
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:5px;max-width:80px;">
                  <div class="progress-bar" style="width:{{ min(100,$s->avg_marks) }}%"></div>
                </div>
                <span class="small">{{ $s->avg_marks }}</span>
              </div>
            @else —
            @endif
          </td>
          <td>
            @if($s->pass_rate !== null)
              <span class="badge bg-{{ $s->pass_rate < 50 ? 'danger' : ($s->pass_rate < 70 ? 'warning text-dark' : 'success') }}">{{ $s->pass_rate }}%</span>
            @else —
            @endif
          </td>
          <td>
            @if($s->attendance_pct !== null)
              <span class="badge bg-{{ $s->attendance_pct < 75 ? 'danger' : 'success' }}">{{ $s->attendance_pct }}%</span>
            @else —
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No subjects found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
