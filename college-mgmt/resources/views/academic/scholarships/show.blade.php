@extends('layouts.app')
@section('content')
<div class="container">
    <h1>{{ $scholarship->name }}</h1>
    <p>Type: {{ $scholarship->type }}</p>
    <p>Status: {{ $scholarship->status }}</p>
</div>
@endsection
