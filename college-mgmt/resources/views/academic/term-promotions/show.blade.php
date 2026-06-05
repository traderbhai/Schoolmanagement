@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Term Promotion Details</h1>
    <div class="card">
        <div class="card-body">
            <p><strong>Student:</strong> {{ $promotion->student->name }}</p>
            <p><strong>CGPA:</strong> {{ $promotion->cgpa }}</p>
            <p><strong>Attendance:</strong> {{ $promotion->attendance_percentage }}%</p>
            <p><strong>Status:</strong> {{ $promotion->status }}</p>
            @if($promotion->status === 'pending')
                @if($promotion->canPromote())
                    <form action="{{ route('academic.term-promotions.approve', $promotion) }}" method="POST" style="display:inline;">
                        @csrf
                        <button class="btn btn-success">Approve</button>
                    </form>
                @endif
                <form action="{{ route('academic.term-promotions.reject', $promotion) }}" method="POST" style="display:inline;">
                    @csrf
                    <textarea name="remarks" placeholder="Remarks"></textarea>
                    <button class="btn btn-danger">Reject</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
