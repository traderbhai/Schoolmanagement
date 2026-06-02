@extends('layouts.admin')
@section('title','Exams')
@section('page-title','Exams & Schedule')
@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
            <div class="col-auto ms-auto"><a href="{{ route('admin.exams.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Schedule Exam</a></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Exam Name</th><th>Subject</th><th>Type</th><th>Date</th><th>Time</th><th>Marks</th><th>Semester</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($exams as $e)
            <tr>
                <td>{{ $exams->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $e->name }}</td>
                <td>{{ $e->subject->name }}</td>
                <td><span class="badge {{ match($e->type){'internal'=>'bg-info text-dark','midterm'=>'bg-warning text-dark','final'=>'bg-danger','practical'=>'bg-success','assignment'=>'bg-secondary',default=>'bg-secondary'} }}">{{ ucfirst($e->type) }}</span></td>
                <td>{{ $e->exam_date->format('d M Y') }}</td>
                <td>{{ $e->start_time ?? '–' }}</td>
                <td>{{ $e->passing_marks }}/{{ $e->total_marks }}</td>
                <td>{{ $e->semester->name }}</td>
                <td>
                    <a href="{{ route('admin.exams.show', $e) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.exams.results', $e) }}" class="btn btn-sm btn-outline-success">Results</a>
                    <a href="{{ route('admin.exams.edit', $e) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.exams.destroy', $e) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No exams scheduled.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($exams->hasPages())<div class="card-footer">{{ $exams->links() }}</div>@endif
</div>
@endsection
