@extends('layouts.admin')
@section('title', 'Courses')
@section('page-title', 'Courses')
@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Create view</strong> for <em>courses</em> — fully wired up in controllers.
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-primary ms-3">Back</a>
</div>
@endsection
