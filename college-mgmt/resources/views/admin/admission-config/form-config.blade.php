@extends('layouts.admin')
@section('title','Edit Application Form')
@section('page-title','Application Form Configuration')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}">Programs</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admission-config.index', $program) }}">Config</a></li>
    <li class="breadcrumb-item active">Form Builder</li>
@endsection
@section('content')

<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Configure which fields appear on the applicant's application form. Toggle required/optional for each field. The default template covers all common PGDM admission fields.</div>

<form method="POST" action="{{ route('admin.admission-config.form.update', $program) }}">
    @csrf
    <input type="hidden" name="form_sections" id="form_sections_input">

    @php
        $sections = $config->form_sections ?? \App\Models\AdmissionFormConfig::getDefaultSections();
    @endphp

    @foreach($sections as $si => $section)
    <div class="card mb-3" style="box-shadow:var(--shadow-sm)">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-layout-text-sidebar me-2 text-primary"></i>
            <span class="fw-semibold">{{ $section['section'] }}</span>
            <span class="badge bg-secondary ms-2">{{ count($section['fields']) }} fields</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40%">Field</th>
                        <th>Type</th>
                        <th class="text-center">Include</th>
                        <th class="text-center">Required</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($section['fields'] as $fi => $field)
                <tr>
                    <td class="fw-semibold small">{{ $field['label'] }}</td>
                    <td><span class="badge bg-light text-dark border" style="font-size:.7rem">{{ $field['type'] }}</span></td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input field-include"
                               data-section="{{ $si }}" data-field="{{ $fi }}"
                               id="inc_{{ $si }}_{{ $fi }}" checked>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input field-required"
                               data-section="{{ $si }}" data-field="{{ $fi }}"
                               id="req_{{ $si }}_{{ $fi }}"
                               {{ ($field['required'] ?? false) ? 'checked' : '' }}>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Form Configuration</button>
        <a href="{{ route('admin.admission-config.index', $program) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection
@push('scripts')
<script>
const sections = @json($sections);

document.querySelector('form').addEventListener('submit', function() {
    const result = sections.map((sec, si) => {
        const fields = sec.fields.filter((field, fi) => {
            return document.getElementById(`inc_${si}_${fi}`)?.checked;
        }).map((field, fi) => {
            const origIndex = sec.fields.indexOf(field);
            return { ...field, required: document.getElementById(`req_${si}_${origIndex}`)?.checked ?? false };
        });
        return { section: sec.section, fields };
    }).filter(s => s.fields.length > 0);

    document.getElementById('form_sections_input').value = JSON.stringify(result);
});
</script>
@endpush
