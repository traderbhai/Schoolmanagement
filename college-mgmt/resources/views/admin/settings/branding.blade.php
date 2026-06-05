@extends('layouts.admin')

@section('title', 'Branding Settings — EduManage')

@section('page-title', 'Branding Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Settings &rsaquo; Branding</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-palette2"></i> Institute Branding
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                    @csrf

                    {{-- Institute name --}}
                    <div class="mb-4">
                        <label for="institute_name" class="form-label">Institute Name</label>
                        <input type="text" class="form-control" id="institute_name" name="institute_name"
                               value="{{ old('institute_name', $settings['institute_name'] ?? '') }}"
                               placeholder="e.g. City College of Engineering">
                        <div class="form-text">Full official name of the institute.</div>
                    </div>

                    {{-- Short name --}}
                    <div class="mb-4">
                        <label for="short_name" class="form-label">Short Name / Abbreviation</label>
                        <input type="text" class="form-control" id="short_name" name="short_name"
                               value="{{ old('short_name', $settings['short_name'] ?? '') }}"
                               placeholder="e.g. CCE" maxlength="10">
                        <div class="form-text">Used in compact UI areas (sidebar brand, PDF headers).</div>
                    </div>

                    <div class="row g-4 mb-4">
                        {{-- Primary color --}}
                        <div class="col-md-4">
                            <label for="primary_color" class="form-label">Primary Color</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" class="form-control form-control-color" id="primary_color"
                                       name="primary_color" value="{{ old('primary_color', $settings['primary_color'] ?? '#4f46e5') }}" style="width:56px;height:40px;padding:2px;border-radius:var(--radius-sm);">
                                <span class="form-text mb-0" id="colorHexLabel" style="font-family:monospace;font-size:.82rem;">#4f46e5</span>
                            </div>
                            <div class="form-text">Used for active nav, buttons, and accents.</div>
                        </div>

                        {{-- Logo upload --}}
                        <div class="col-md-8">
                            <label for="logo" class="form-label">Institute Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo"
                                   accept="image/png,image/jpeg,image/svg+xml">
                            <div class="form-text">PNG, JPG, or SVG. Recommended: 200&times;60 px, transparent background.</div>
                        </div>
                    </div>

                    <hr style="border-color:var(--clr-border);margin:1.5rem 0;">

                    <h6 class="mb-3" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--clr-text-muted);">Contact Information</h6>

                    {{-- Address --}}
                    <div class="mb-4">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"
                                  placeholder="123 College Road, City, State — 400001">{{ old('address', $settings['address'] ?? '') }}</textarea>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background:var(--clr-body-bg);border-color:var(--clr-border);color:var(--clr-text-muted);font-size:.85rem;">
                                    <i class="bi bi-telephone"></i>
                                </span>
                                <input type="tel" class="form-control" id="contact_phone" name="phone"
                                       value="{{ old('phone', $settings['phone'] ?? '') }}" placeholder="+91 98765 43210">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background:var(--clr-body-bg);border-color:var(--clr-border);color:var(--clr-text-muted);font-size:.85rem;">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="contact_email" name="email"
                                       value="{{ old('email', $settings['email'] ?? '') }}" placeholder="admin@college.edu">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="reset" class="btn btn-sm" style="border:1px solid var(--clr-border);background:transparent;color:var(--clr-text);">
                            Reset
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Save Branding
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Preview card --}}
        <div class="card">
            <div class="card-header">
                <i class="bi bi-eye"></i> Preview
            </div>
            <div class="card-body p-4">
                <p class="text-sm" style="color:var(--clr-text-muted);margin-bottom:1rem;">
                    This is how the sidebar brand will look with your settings.
                </p>
                <div style="background:var(--clr-sidebar-bg);border-radius:var(--radius-md);padding:1rem 1.25rem;display:inline-flex;align-items:center;gap:.7rem;max-width:260px;">
                    <span id="previewIcon" style="width:34px;height:34px;background:#4f46e5;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:white;flex-shrink:0;">
                        <i class="bi bi-mortarboard-fill"></i>
                    </span>
                    <span>
                        <div id="previewName" style="color:#f8fafc;font-weight:700;font-size:.95rem;line-height:1.2;">EduManage</div>
                        <div style="color:rgba(255,255,255,.45);font-size:.68rem;">Admin Portal</div>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
// Update color hex label
document.getElementById('primary_color').addEventListener('input', function () {
    document.getElementById('colorHexLabel').textContent = this.value;
    document.getElementById('previewIcon').style.background = this.value;
});

// Update preview name
document.getElementById('institute_name').addEventListener('input', function () {
    var v = this.value.trim();
    document.getElementById('previewName').textContent = v || 'EduManage';
});
</script>
@endpush
