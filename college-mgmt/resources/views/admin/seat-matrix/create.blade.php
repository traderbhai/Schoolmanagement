@extends('layouts.admin')
@section('title', 'Create Seat Matrix — ' . $program->name)
@section('content')
<div class="mb-4">
    <a href="{{ route('admission.seat-matrices.index', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Seat Matrix</a>
    <h2 class="fw-bold mb-0 mt-1">Create Seat Matrix — {{ $program->name }}</h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admission.seat-matrices.store', $program) }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold">Batch (leave blank for all batches)</label>
                <select aria-label="Batch" name="batch_id" class="form-select">
                    <option value="">— All Batches (Default) —</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}" @selected(old('batch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold mb-0">Seat Distribution</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="loadDefaults">
                    <i class="bi bi-magic me-1"></i> Load AICTE Defaults
                </button>
            </div>

            <div class="row g-3">
                @php
                    $seatFields = [
                        ['key' => 'general_seats', 'label' => 'General', 'help' => 'Open category seats'],
                        ['key' => 'obc_seats', 'label' => 'OBC', 'help' => 'Other Backward Classes'],
                        ['key' => 'obc_nc_seats', 'label' => 'OBC-NC', 'help' => 'OBC Non-Creamy Layer'],
                        ['key' => 'sc_seats', 'label' => 'SC', 'help' => 'Scheduled Caste (15%)'],
                        ['key' => 'st_seats', 'label' => 'ST', 'help' => 'Scheduled Tribe (7.5%)'],
                        ['key' => 'ews_seats', 'label' => 'EWS', 'help' => 'Economically Weaker Section (10%)'],
                        ['key' => 'pwd_seats', 'label' => 'PWD', 'help' => 'Persons with Disability — supernumerary (horizontal)'],
                        ['key' => 'nri_seats', 'label' => 'NRI', 'help' => 'Non-Resident Indian — supernumerary'],
                        ['key' => 'management_quota_seats', 'label' => 'Management Quota', 'help' => 'Institute management discretion'],
                    ];
                @endphp
                @foreach($seatFields as $field)
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ $field['label'] }} Seats</label>
                    <input type="number" name="{{ $field['key'] }}" id="{{ $field['key'] }}"
                           class="form-control @error($field['key']) is-invalid @enderror"
                           value="{{ old($field['key'], $defaults[$field['key']] ?? 0) }}"
                           min="0" required>
                    <div class="form-text">{{ $field['help'] }}</div>
                    @error($field['key'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endforeach

                <div class="col-md-4">
                    <label class="form-label fw-semibold">State Quota %</label>
                    <input aria-label="State Quota Percentage" type="number" name="state_quota_percentage" class="form-control"
                           value="{{ old('state_quota_percentage', 0) }}" min="0" max="100" step="0.01">
                    <div class="form-text">Percentage of seats reserved for state domicile</div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Create Seat Matrix
                </button>
                <a href="{{ route('admission.seat-matrices.index', $program) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const defaults = @json($defaults);
document.getElementById('loadDefaults').addEventListener('click', function() {
    Object.entries(defaults).forEach(([key, value]) => {
        const el = document.getElementById(key);
        if (el) el.value = value;
    });
});
</script>
@endpush
@endsection
