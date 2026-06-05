<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Company Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $company->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Industry</label>
        <input type="text" name="industry" class="form-control @error('industry') is-invalid @enderror"
            value="{{ old('industry', $company->industry ?? '') }}" placeholder="e.g. IT, Finance, Manufacturing">
        @error('industry')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Website</label>
        <input type="url" name="website" class="form-control @error('website') is-invalid @enderror"
            value="{{ old('website', $company->website ?? '') }}" placeholder="https://example.com">
        @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Contact Person</label>
        <input type="text" name="contact_person" class="form-control @error('contact_person') is-invalid @enderror"
            value="{{ old('contact_person', $company->contact_person ?? '') }}">
        @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Contact Email</label>
        <input type="email" name="contact_email" class="form-control @error('contact_email') is-invalid @enderror"
            value="{{ old('contact_email', $company->contact_email ?? '') }}">
        @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Contact Phone</label>
        <input type="text" name="contact_phone" class="form-control @error('contact_phone') is-invalid @enderror"
            value="{{ old('contact_phone', $company->contact_phone ?? '') }}" maxlength="20">
        @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">Logo URL</label>
        <input type="url" name="logo_url" class="form-control @error('logo_url') is-invalid @enderror"
            value="{{ old('logo_url', $company->logo_url ?? '') }}" placeholder="https://...">
        @error('logo_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $company->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                @checked(old('is_active', $company->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active (visible for drives)</label>
        </div>
    </div>
</div>
