@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Term Promotions</h1>
    <table class="table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($promotions as $promotion)
            <tr>
                <td>{{ $promotion->student->name }}</td>
                <td>{{ $promotion->status }}</td>
                <td>
                    <a href="{{ route('academic.term-promotions.show', $promotion) }}">View</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
