<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Step Name <span class="text-danger">*</span></label>
        <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $step?->name) }}"
            placeholder="e.g. Group Discussion, Personal Interview" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Step Order <span class="text-danger">*</span></label>
        <input aria-label="Step Order" type="number" name="step_order" class="form-control" min="1"
            value="{{ old('step_order', $step?->step_order ?? $nextOrder ?? 1) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select aria-label="Type" name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach(['gd' => 'Group Discussion', 'pi' => 'Personal Interview', 'wat' => 'Written Ability Test', 'written_test' => 'Written Test', 'aptitude' => 'Aptitude Test', 'presentation' => 'Presentation'] as $val => $label)
                <option value="{{ $val }}" {{ old('type', $step?->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Max Score <span class="text-danger">*</span></label>
        <input aria-label="Max Score" type="number" name="max_score" class="form-control" min="1"
            value="{{ old('max_score', $step?->max_score ?? 100) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Weightage (%) <span class="text-danger">*</span></label>
        <input aria-label="Weightage" type="number" name="weightage" class="form-control" min="0" max="100" step="0.01"
            value="{{ old('weightage', $step?->weightage ?? 100) }}" required>
    </div>
    <div class="col-md-12">
        <label class="form-label">Instructions</label>
        <textarea aria-label="Instructions for panelists/candidates" name="instructions" class="form-control" rows="3"
            placeholder="Instructions for panelists/candidates">{{ old('instructions', $step?->instructions) }}</textarea>
    </div>
    @if($step)
    <div class="col-md-12">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                {{ old('is_active', $step->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    @endif
</div>
