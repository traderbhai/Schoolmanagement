@extends('layouts.admin')
@section('title', 'Dean — Students')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>All Students</h4>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name/enroll…" value="{{ request('search') }}">
    </div>
    <div class="col-sm-2">
        <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)
                <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="batch_id" class="form-select form-select-sm">
            <option value="">All Batches</option>
            @foreach($batches as $b)
                <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="active" @selected(request('status')=='active')>Active</option>
            <option value="inactive" @selected(request('status')=='inactive')>Inactive</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('dean.students') }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Name</th><th>Enroll No.</th><th>Program</th><th>Batch</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse($students as $s)
                    <tr>
                        <td>{{ $loop->iteration + ($students->currentPage()-1)*$students->perPage() }}</td>
                        <td>{{ $s->user->name ?? '—' }}</td>
                        <td>{{ $s->enrollment_number }}</td>
                        <td>{{ $s->program->name ?? '—' }}</td>
                        <td>{{ $s->batch->name ?? '—' }}</td>
                        <td><span class="badge bg-{{ $s->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($s->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($students->hasPages())
    <div class="card-footer bg-transparent">{{ $students->links() }}</div>
    @endif
</div>
@endsection
