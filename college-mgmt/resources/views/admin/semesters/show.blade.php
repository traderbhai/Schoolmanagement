@extends('layouts.admin')
@section('title', 'Semesters')
@section('page-title', 'Semesters')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.semesters.index') }}">Semesters</a></li>
    <li class="breadcrumb-item active">View Semester</li>
@endsection

@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Show view</strong> for <em>semesters</em> — fully wired up in controllers.
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-primary ms-3">Back</a>
</div>
@endsection
