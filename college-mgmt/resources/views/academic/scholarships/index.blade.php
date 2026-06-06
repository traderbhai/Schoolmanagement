@extends('layouts.admin')

@section('title', 'Scholarships')
@section('page-title', 'Scholarships')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="mb-3">
        <a href="{{ route('academic.scholarships.create') }}" class="btn btn-primary">Add Scholarship</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr><th>Name</th><th>Type</th><th>Percentage</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($scholarships as $scholarship)
                    <tr>
                        <td>{{ $scholarship->name }}</td>
                        <td>{{ ucfirst(str_replace('_',' ',$scholarship->type)) }}</td>
                        <td>{{ $scholarship->percentage }}%</td>
                        <td><span class="badge bg-{{ $scholarship->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($scholarship->status) }}</span></td>
                        <td><a href="{{ route('academic.scholarships.show', $scholarship) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $scholarships->links() }}
        </div>
    </div>
</div>
@endsection
