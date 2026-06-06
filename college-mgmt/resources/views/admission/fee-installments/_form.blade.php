<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Installment Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $installment?->name) }}"
            placeholder="e.g. Confirmation Fee, 1st Semester Fee" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Installment # <span class="text-danger">*</span></label>
        <input type="number" name="installment_number" class="form-control @error('installment_number') is-invalid @enderror"
            value="{{ old('installment_number', $installment?->installment_number ?? $nextNumber ?? 1) }}" min="1" required>
        @error('installment_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                value="{{ old('amount', $installment?->amount) }}" min="1" step="0.01" required>
        </div>
        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Due Date</label>
        <input type="date" name="due_date" class="form-control"
            value="{{ old('due_date', $installment?->due_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-12">
        <label class="form-label">Batch (Leave blank to apply to all batches)</label>
        <select name="batch_id" class="form-select">
            <option value="">All Batches</option>
            @foreach($batches as $batch)
                <option value="{{ $batch->id }}" {{ old('batch_id', $installment?->batch_id) == $batch->id ? 'selected' : '' }}>
                    {{ $batch->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"
            placeholder="Optional notes about this installment">{{ old('description', $installment?->description) }}</textarea>
    </div>
    <div class="col-md-12">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                {{ old('is_active', $installment?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active (visible to applicants)</label>
        </div>
    </div>
</div>
