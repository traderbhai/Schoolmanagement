@extends('layouts.admin')
@section('title', 'Dean — Attendance')

@section('content')
<h4 class="mb-4"><i class="bi bi-check2-square me-2 text-primary"></i>Program-wise Attendance</h4>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        @forelse($programs as $p)
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">{{ $p->name }}</span>
                <span class="text-muted small">{{ $p->att_pct }}% ({{ $p->att_total }} records)</span>
            </div>
            <div class="progress" style="height:18px">
                <div class="progress-bar bg-{{ $p->att_pct >= 75 ? 'success' : ($p->att_pct >= 60 ? 'warning' : 'danger') }} text-white"
                     style="width:{{ $p->att_pct }}%" role="progressbar">
                    {{ $p->att_pct }}%
                </div>
            </div>
        </div>
        @empty
            <p class="text-muted">No attendance data available.</p>
        @endforelse
    </div>
</div>
@endsection
