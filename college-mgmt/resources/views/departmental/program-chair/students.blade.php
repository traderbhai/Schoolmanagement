@extends('layouts.admin')
@section('title', 'Program Chair — Students')

@section('content')
<h4 class="mb-4"><i class="bi bi-people me-2 text-primary"></i>Program Students</h4>

<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-3"><input aria-label="Program chair student search" type="text" name="search" class="form-control form-control-sm" placeholder="Search…" value="{{ request('search') }}"></div>
    <div class="col-sm-2">
        <select aria-label="Program" name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select aria-label="Batch" name="batch_id" class="form-select form-select-sm">
            <option value="">All Batches</option>
            @foreach($batches as $b)<option value="{{ $b->id }}" @selected(request('batch_id')==$b->id)>{{ $b->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select aria-label="Status" name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="active" @selected(request('status')=='active')>Active</option>
            <option value="inactive" @selected(request('status')=='inactive')>Inactive</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('chair.students') }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th scope="col">#</th><th scope="col">Name</th><th scope="col">Enroll No.</th><th scope="col">Program</th><th scope="col">Batch</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                @forelse($students as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
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
