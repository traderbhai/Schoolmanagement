@extends('layouts.admin')
@section('title', 'Subjects')
@section('page-title', 'Subjects')
@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Show view</strong> for <em>subjects</em> — fully wired up in controllers.
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-primary ms-3">Back</a>
</div>
@endsection
