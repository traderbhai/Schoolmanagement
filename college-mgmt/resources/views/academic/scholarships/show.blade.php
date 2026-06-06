@extends('layouts.admin')

@section('title', 'Scholarship Details')
@section('page-title', 'Scholarship Details')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <h5>{{ $scholarship->name }}</h5>
            <p><strong>Type:</strong> {{ ucfirst(str_replace('_',' ',$scholarship->type)) }}</p>
            <p><strong>Percentage:</strong> {{ $scholarship->percentage }}%</p>
            @if($scholarship->fixed_amount)
                <p><strong>Fixed Amount:</strong> ₹{{ number_format($scholarship->fixed_amount) }}</p>
            @endif
            <p><strong>Status:</strong> {{ ucfirst($scholarship->status) }}</p>
            @if($scholarship->valid_from)
                <p><strong>Valid From:</strong> {{ $scholarship->valid_from->format('d M Y') }}</p>
            @endif
            @if($scholarship->valid_to)
                <p><strong>Valid To:</strong> {{ $scholarship->valid_to->format('d M Y') }}</p>
            @endif
            <div class="mt-3">
                <a href="{{ route('academic.scholarships.edit', $scholarship) }}" class="btn btn-warning">Edit</a>
                <form action="{{ route('academic.scholarships.destroy', $scholarship) }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" onclick="return confirm('Delete this scholarship?')">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
