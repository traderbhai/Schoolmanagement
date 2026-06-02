@extends('layouts.admin')
@section('title', 'Classrooms')
@section('page-title', 'Classrooms')
@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Index view</strong> for <em>classrooms</em> — fully wired up in controllers.
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-primary ms-3">Back</a>
</div>
@endsection
