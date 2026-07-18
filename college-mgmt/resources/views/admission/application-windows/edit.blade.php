@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Edit Application Window</h1>
            <p class="text-muted">{{ $program->name }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admission.application-windows.update', $window) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Batch <span class="text-muted">(Optional - leave empty for all batches)</span></label>
                        <select aria-label="Batch" name="batch_id" class="form-control @error('batch_id') is-invalid @enderror">
                            <option value="">All Batches</option>
                            @foreach($batches as $batch)
                                <option value="{{ $batch->id }}" {{ $window->batch_id === $batch->id ? 'selected' : '' }}>
                                    {{ $batch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Application Capacity <span class="text-muted">(Optional - leave empty for unlimited)</span></label>
                        <input aria-label="Capacity Limit" type="number" name="capacity_limit" class="form-control @error('capacity_limit') is-invalid @enderror" min="1" value="{{ $window->capacity_limit }}">
                        <small class="text-muted d-block mt-2">Current applications: {{ $window->current_applications }}</small>
                        @error('capacity_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opens At *</label>
                        <input aria-label="Opens At" type="datetime-local" name="opens_at" class="form-control @error('opens_at') is-invalid @enderror" value="{{ $window->opens_at->format('Y-m-d\TH:i') }}" required>
                        @error('opens_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Closes At *</label>
                        <input aria-label="Closes At" type="datetime-local" name="closes_at" class="form-control @error('closes_at') is-invalid @enderror" value="{{ $window->closes_at->format('Y-m-d\TH:i') }}" required>
                        @error('closes_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea aria-label="Description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ $window->description }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" {{ $window->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            Active (allow applications)
                        </label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <a href="{{ route('admission.application-windows.index', $program) }}" class="btn btn-secondary w-100">Cancel</a>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary w-100">Update Window</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
