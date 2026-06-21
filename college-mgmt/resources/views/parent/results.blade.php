@extends('layouts.parent')
@section('title', 'Results - '.$student->user->name)
@section('page-title', 'Results')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parent.children') }}">My Children</a></li>
    <li class="breadcrumb-item active">Results</li>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">{{ $student->user->name }} - Results</h5>
        <div class="text-muted" style="font-size:.82rem">{{ optional($student->program)->name ?? optional($student->course)->name }}</div>
    </div>
    <a href="{{ route('parent.children') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="GET" class="mb-3">
    <div class="d-flex gap-2">
        <select name="semester_id" class="form-select form-select-sm" style="max-width:260px" onchange="this.form.submit()">
            @foreach($semesters as $sem)
            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                {{ $sem->name }} - {{ $sem->academicYear->name ?? '' }}
            </option>
            @endforeach
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Subject</th>
                        <th>Exam</th>
                        <th>Marks Obtained</th>
                        <th>Total Marks</th>
                        <th>Grade</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($results as $result)
                <tr>
                    <td>{{ optional(optional($result->exam)->subject)->name ?? '-' }}</td>
                    <td>{{ optional($result->exam)->name ?? '-' }}</td>
                    <td>
                        @if($result->is_absent)
                        <span class="badge bg-danger">Absent</span>
                        @else
                        {{ $result->marks_obtained }}
                        @endif
                    </td>
                    <td>{{ optional($result->exam)->total_marks ?? '-' }}</td>
                    <td>{{ $result->grade ?? '-' }}</td>
                    <td>
                        @if($result->is_absent)
                        <span class="badge bg-danger">Absent</span>
                        @elseif($result->marks_obtained >= (optional($result->exam)->total_marks * 0.4))
                        <span class="badge badge-active">Pass</span>
                        @else
                        <span class="badge bg-danger">Fail</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark mb-1">No published results are available for this semester yet</div>
                        <div class="small">Results appear here only after the examination office publishes official marks for your child's enrolled subjects. Draft marks, internal review data, and unpublished exams are not shown to parents.</div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
