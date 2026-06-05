@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Fee Demands</h1>
    <table class="table">
        <thead>
            <tr><th>Student</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($feeDemands as $demand)
            <tr>
                <td>{{ $demand->student->name }}</td>
                <td>{{ $demand->final_amount }}</td>
                <td>{{ $demand->due_date }}</td>
                <td>{{ $demand->status }}</td>
                <td><a href="{{ route('academic.fee-demands.show', $demand) }}">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
