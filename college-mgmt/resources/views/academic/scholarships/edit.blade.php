@extends('layouts.admin')

@section('title', 'Edit Scholarship')
@section('page-title', 'Edit Scholarship')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.scholarships.update', $scholarship) }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Scholarship Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $scholarship->name) }}" required>
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            @foreach(['merit','need_based','category','other'] as $type)
                                <option value="{{ $type }}" {{ old('type',$scholarship->type)==$type?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $scholarship->description) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Percentage (%)</label>
                        <input type="number" name="percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('percentage', $scholarship->percentage) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fixed Amount (₹)</label>
                        <input type="number" name="fixed_amount" class="form-control" step="0.01" min="0" value="{{ old('fixed_amount', $scholarship->fixed_amount) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            @foreach(['active','inactive','expired'] as $s)
                                <option value="{{ $s }}" {{ old('status',$scholarship->status)==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid From</label>
                        <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $scholarship->valid_from?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid To</label>
                        <input type="date" name="valid_to" class="form-control" value="{{ old('valid_to', $scholarship->valid_to?->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Scholarship</button>
                    <a href="{{ route('academic.scholarships.show', $scholarship) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
