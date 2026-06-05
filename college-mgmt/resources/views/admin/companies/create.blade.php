@extends('layouts.admin')
@section('title', 'Add Company')
@section('page-title', 'Add Company')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.companies.index') }}">Companies</a></li>
    <li class="breadcrumb-item active">Add Company</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold">Add New Company</h6>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.companies.store') }}">
                    @csrf
                    @include('admin.companies._form')
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary">Save Company</button>
                        <a href="{{ route('admin.companies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
