@extends('layouts.admin')
@section('title', 'Edit Seat Matrix — ' . $program->name)
@section('content')
<div class="mb-4">
    <a href="{{ route('admin.seat-matrix.index', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Seat Matrix</a>
    <h2 class="fw-bold mb-0 mt-1">Edit Seat Matrix — {{ $program->name }}</h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.seat-matrix.update', $matrix) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Batch</label>
                <input type="text" class="form-control" value="{{ $matrix->batch ? $matrix->batch->name : 'All Batches (Default)' }}" disabled>
            </div>

            <h5 class="fw-semibold mb-3">Seat Distribution</h5>
            <div class="row g-3">
                @php
                    $seatFields = [
                        ['key' => 'general_seats', 'label' => 'General'],
                        ['key' => 'obc_seats', 'label' => 'OBC'],
                        ['key' => 'obc_nc_seats', 'label' => 'OBC-NC'],
                        ['key' => 'sc_seats', 'label' => 'SC'],
                        ['key' => 'st_seats', 'label' => 'ST'],
                        ['key' => 'ews_seats', 'label' => 'EWS'],
                        ['key' => 'pwd_seats', 'label' => 'PWD (supernumerary)'],
                        ['key' => 'nri_seats', 'label' => 'NRI (supernumerary)'],
                        ['key' => 'management_quota_seats', 'label' => 'Management Quota'],
                    ];
                @endphp
                @foreach($seatFields as $field)
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ $field['label'] }}</label>
                    <input type="number" name="{{ $field['key'] }}"
                           class="form-control @error($field['key']) is-invalid @enderror"
                           value="{{ old($field['key'], $matrix->{$field['key']}) }}"
                           min="0" required>
                    @error($field['key'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endforeach

                <div class="col-md-4">
                    <label class="form-label fw-semibold">State Quota %</label>
                    <input type="number" name="state_quota_percentage" class="form-control"
                           value="{{ old('state_quota_percentage', $matrix->state_quota_percentage) }}"
                           min="0" max="100" step="0.01">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Seat Matrix
                </button>
                <a href="{{ route('admin.seat-matrix.index', $program) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
