@extends('layouts.student')
@section('title', 'Marks Appeals')
@section('page-title', 'Marks Appeals')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('student.appeals.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Appeal
        </a>
    </div>

    @if($appeals->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
            No appeals submitted yet.
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject</th>
                        <th>Exam</th>
                        <th>Marks Claimed</th>
                        <th>Status</th>
                        <th>Revised Marks</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appeals as $appeal)
                    @php $badge = match($appeal->status) { 'resolved'=>'success','rejected'=>'danger','under_review'=>'info',default=>'warning' }; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $appeal->examResult->exam->subject->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $appeal->examResult->exam->title ?? '—' }}</td>
                        <td>{{ $appeal->marks_claimed }}</td>
                        <td><span class="badge bg-{{ $badge }}">{{ ucwords(str_replace('_',' ',$appeal->status)) }}</span></td>
                        <td>{{ $appeal->revised_marks ?? '—' }}</td>
                        <td class="text-muted small">{{ $appeal->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($appeals->hasPages())
        <div class="card-footer">{{ $appeals->links() }}</div>
        @endif
    </div>
    @endif
</div>
@endsection
