@if($errors->any())
    <div class="alert alert-danger mb-3">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Batch (leave blank for all batches)</label>
        <select name="batch_id" class="form-select">
            <option value="">All Batches</option>
            @foreach($batches as $batch)
                <option value="{{ $batch->id }}" {{ old('batch_id', $matrix?->batch_id) == $batch->id ? 'selected' : '' }}>{{ $batch->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Total Seats <span class="text-danger">*</span></label>
        <input type="number" name="total_seats" class="form-control" min="1"
            value="{{ old('total_seats', $matrix?->total_seats ?? 60) }}" required id="totalSeats">
    </div>

    <div class="col-12"><hr class="my-1"><p class="text-muted small mb-2">Reserved Category Seats</p></div>
    <div class="col-md-3">
        <label class="form-label">General</label>
        <input type="number" name="general_seats" class="form-control seat-input" min="0" value="{{ old('general_seats', $matrix?->general_seats ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">OBC</label>
        <input type="number" name="obc_seats" class="form-control seat-input" min="0" value="{{ old('obc_seats', $matrix?->obc_seats ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">SC</label>
        <input type="number" name="sc_seats" class="form-control seat-input" min="0" value="{{ old('sc_seats', $matrix?->sc_seats ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">ST</label>
        <input type="number" name="st_seats" class="form-control seat-input" min="0" value="{{ old('st_seats', $matrix?->st_seats ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">EWS</label>
        <input type="number" name="ews_seats" class="form-control seat-input" min="0" value="{{ old('ews_seats', $matrix?->ews_seats ?? 0) }}">
    </div>

    <div class="col-12"><hr class="my-1"><p class="text-muted small mb-2">Quota Seats</p></div>
    <div class="col-md-4">
        <label class="form-label">Management Quota</label>
        <input type="number" name="management_quota" class="form-control seat-input" min="0" value="{{ old('management_quota', $matrix?->management_quota ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">NRI Quota</label>
        <input type="number" name="nri_quota" class="form-control seat-input" min="0" value="{{ old('nri_quota', $matrix?->nri_quota ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Defence Quota</label>
        <input type="number" name="defence_quota" class="form-control seat-input" min="0" value="{{ old('defence_quota', $matrix?->defence_quota ?? 0) }}">
    </div>

    <div class="col-12">
        <div class="alert alert-info py-2 mb-0" id="seatSummary">
            <small>Sum of all category seats: <strong id="seatSum">0</strong> / <strong id="seatTotal">0</strong></small>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateSum() {
    let sum = 0;
    document.querySelectorAll('.seat-input').forEach(el => sum += parseInt(el.value || 0));
    document.getElementById('seatSum').textContent = sum;
    document.getElementById('seatTotal').textContent = document.getElementById('totalSeats').value || 0;
}
document.querySelectorAll('.seat-input, #totalSeats').forEach(el => el.addEventListener('input', updateSum));
updateSum();
</script>
@endpush
