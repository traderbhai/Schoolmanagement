@extends('layouts.admin')
@section('title', 'Academic Transcripts')
@section('page-title', 'Academic Transcripts')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Academic Transcripts</h4>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-4">
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, email, enrollment no..." value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                <a href="{{ route('academic.transcripts.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Enrollment No.</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>CGPA</th>
                        <th>Transcript Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($students as $student)
                    @php $transcript = $student->transcripts()->latest()->first(); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $student->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $student->enrollment_number }}</td>
                        <td class="small">{{ $student->program?->name ?? '—' }}</td>
                        <td class="small">{{ $student->batch?->name ?? '—' }}</td>
                        <td>
                            @if($transcript)
                                <span class="badge bg-primary">{{ $transcript->cgpa }}</span>
                            @else
                                <span class="text-muted small">Not issued</span>
                            @endif
                        </td>
                        <td>
                            @if($transcript)
                                <span class="badge bg-{{ $transcript->status === 'issued' ? 'success' : ($transcript->status === 'draft' ? 'secondary' : 'warning') }}">
                                    {{ ucfirst($transcript->status) }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted">Draft</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('academic.transcripts.show', $student) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="{{ route('academic.transcripts.pdf', $student) }}" class="btn btn-sm btn-outline-danger" target="_blank">
                                <i class="bi bi-file-pdf me-1"></i>PDF
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No students found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $students->links() }}</div>
@endsection
