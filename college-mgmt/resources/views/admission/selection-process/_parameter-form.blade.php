<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Parameter Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $parameter?->name) }}"
            placeholder="e.g. Communication Skills, Subject Knowledge" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Max Score <span class="text-danger">*</span></label>
        <input type="number" name="max_score" class="form-control" min="1"
            value="{{ old('max_score', $parameter?->max_score ?? 10) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Order <span class="text-danger">*</span></label>
        <input type="number" name="sort_order" class="form-control" min="1"
            value="{{ old('sort_order', $parameter?->sort_order ?? $nextOrder ?? 1) }}" required>
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"
            placeholder="What does this parameter evaluate?">{{ old('description', $parameter?->description) }}</textarea>
    </div>
</div>
