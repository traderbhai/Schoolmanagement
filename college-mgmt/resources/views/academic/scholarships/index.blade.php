@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Scholarships</h1>
    <table class="table">
        <thead>
            <tr><th>Name</th><th>Type</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($scholarships as $scholarship)
            <tr>
                <td>{{ $scholarship->name }}</td>
                <td>{{ $scholarship->type }}</td>
                <td>{{ $scholarship->status }}</td>
                <td><a href="{{ route('academic.scholarships.show', $scholarship) }}">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
