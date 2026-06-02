@extends('layouts.admin')
@section('title','Time Slots')
@section('page-title','Time Slots')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.timetable-slots.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Slot</a>
</div>
<div class="card" style="max-width:700px">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Name</th><th>Start</th><th>End</th><th>Duration</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($slots as $s)
            <tr>
                <td>{{ $s->sort_order }}</td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td>{{ $s->start_time }}</td>
                <td>{{ $s->end_time }}</td>
                <td>{{ $s->duration_minutes }} min</td>
                <td><span class="badge {{ $s->is_break ? 'bg-warning text-dark' : 'bg-primary' }}">{{ $s->is_break ? 'Break' : 'Class' }}</span></td>
                <td>
                    <a href="{{ route('admin.timetable-slots.edit', $s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.timetable-slots.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
