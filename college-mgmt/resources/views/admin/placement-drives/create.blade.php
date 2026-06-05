@extends('layouts.admin')
@section('title', 'Add Placement Drive')
@section('page-title', 'Add Placement Drive')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.placement-drives.index') }}">Drives</a></li>
    <li class="breadcrumb-item active">Add Drive</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold">Add New Placement Drive</h6>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.placement-drives.store') }}">
                    @csrf
                    @include('admin.placement-drives._form')
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary">Save Drive</button>
                        <a href="{{ route('admin.placement-drives.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
