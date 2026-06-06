@extends('layouts.admin')

@section('title', 'Term Promotion Details')
@section('page-title', 'Term Promotion Details')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <p><strong>Student:</strong> {{ $termPromotion->student->user->name ?? $termPromotion->student->enrollment_number ?? 'N/A' }}</p>
            <p><strong>CGPA:</strong> {{ $termPromotion->cgpa }}</p>
            <p><strong>Attendance:</strong> {{ $termPromotion->attendance_percentage }}%</p>
            <p><strong>Academic Criteria:</strong> {{ $termPromotion->meets_academic_criteria ? 'Met' : 'Not Met' }}</p>
            <p><strong>Attendance Criteria:</strong> {{ $termPromotion->meets_attendance_criteria ? 'Met' : 'Not Met' }}</p>
            <p><strong>Status:</strong> {{ ucfirst($termPromotion->status) }}</p>
            @if($termPromotion->remarks)
                <p><strong>Remarks:</strong> {{ $termPromotion->remarks }}</p>
            @endif

            @if($termPromotion->status === 'pending')
                @if($termPromotion->canPromote())
                    <form action="{{ route('academic.term-promotions.approve', $termPromotion) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-success">Approve</button>
                    </form>
                @endif
                <form action="{{ route('academic.term-promotions.reject', $termPromotion) }}" method="POST" class="d-inline">
                    @csrf
                    <textarea name="remarks" class="form-control mb-2" placeholder="Rejection reason" required></textarea>
                    <button class="btn btn-danger">Reject</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
