@extends('layouts.admin')
@section('title', 'Time Slots')
@section('page-title', 'Time Slots')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Time Slots</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Timetable Slots</h5>
        <div class="text-muted" style="font-size:.82rem">Define daily period timings</div>
    </div>
    <a href="{{ route('admin.timetable-slots.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Slot
    </a>
</div>

<div class="card" style="max-width:780px">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Name</th>
                    <th scope="col">Time</th>
                    <th scope="col">Duration</th>
                    <th scope="col">Type</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($slots as $s)
            <tr>
                <td class="fw-bold text-muted">{{ $s->sort_order }}</td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td>
                    <span class="badge bg-light text-dark border" style="font-size:.8rem">
                        <i class="bi bi-clock me-1"></i>{{ $s->start_time }} – {{ $s->end_time }}
                    </span>
                </td>
                <td>{{ $s->duration_minutes }} min</td>
                <td>
                    <span class="badge {{ $s->is_break ? 'bg-warning text-dark' : 'badge-active' }}">
                        {{ $s->is_break ? 'Break' : 'Regular' }}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.timetable-slots.edit', $s) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit timetable slot {{ $s->name }}"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            aria-label="Delete timetable slot {{ $s->name }}"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.timetable-slots.destroy', $s) }}"
                            data-name="{{ $s->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-clock-history"></i></div>
                        <div class="mt-2 fw-semibold">No slots defined yet</div>
                        <a href="{{ route('admin.timetable-slots.create') }}" class="btn btn-primary btn-sm mt-3">Add Slot</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
