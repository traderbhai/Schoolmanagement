@extends('layouts.admin')

@section('title', 'Term Promotions')
@section('page-title', 'Term Promotions')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>CGPA</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($promotions as $promotion)
                    <tr>
                        <td>{{ $promotion->student->enrollment_number ?? 'N/A' }}</td>
                        <td>{{ $promotion->cgpa }}</td>
                        <td>{{ $promotion->attendance_percentage }}%</td>
                        <td><span class="badge bg-{{ $promotion->status === 'approved' ? 'success' : ($promotion->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($promotion->status) }}</span></td>
                        <td><a href="{{ route('academic.term-promotions.show', $promotion) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $promotions->links() }}
        </div>
    </div>
</div>
@endsection
