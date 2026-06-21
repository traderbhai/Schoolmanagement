{{-- Renders a single section's fields dynamically from config JSON --}}
@php
    $sectionKeys = ['personal', 'academic', 'family', 'additional'];
    $sectionKey = $sectionKeys[$stepIndex] ?? 'additional';
    $savedData = $applicant->getFormDataForSection($sectionKey);
    $isLocked = $applicant->status !== 'draft';
@endphp

<form method="POST" action="{{ route('applicant.application.section', $sectionKey) }}">
    @csrf

    <div class="row g-3">
        @foreach($section['fields'] ?? [] as $field)
            @php
                $val = $savedData[$field['key']] ?? old($field['key'], '');
                $required = !empty($field['required']);
                $label = $field['label'] . ($required ? ' *' : '');
                $type = $field['type'] ?? 'text';
            @endphp

            <div class="{{ in_array($type, ['textarea']) ? 'col-12' : 'col-md-6' }}">
                <label class="form-label fw-semibold small">{{ $label }}</label>

                @if($type === 'textarea')
                    <textarea name="{{ $field['key'] }}" rows="3"
                        class="form-control @error($field['key']) is-invalid @enderror"
                        {{ $required ? 'required' : '' }}
                        {{ $isLocked ? 'readonly' : '' }}>{{ $val }}</textarea>

                @elseif($type === 'select')
                    <select name="{{ $field['key'] }}"
                        class="form-select @error($field['key']) is-invalid @enderror"
                        {{ $required ? 'required' : '' }}
                        {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">Select an option</option>
                        @foreach($field['options'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ $val === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>

                @elseif($type === 'checkbox')
                    <div class="form-check mt-2">
                        <input type="checkbox" name="{{ $field['key'] }}" value="1"
                            class="form-check-input @error($field['key']) is-invalid @enderror"
                            id="{{ $field['key'] }}"
                            {{ $val ? 'checked' : '' }}
                            {{ $isLocked ? 'disabled' : '' }}>
                        <label class="form-check-label" for="{{ $field['key'] }}">{{ $field['label'] }}</label>
                    </div>

                @else
                    <input type="{{ $type }}"
                        name="{{ $field['key'] }}"
                        value="{{ $val }}"
                        class="form-control @error($field['key']) is-invalid @enderror"
                        placeholder="{{ $field['label'] }}"
                        {{ $required ? 'required' : '' }}
                        {{ $isLocked ? 'readonly' : '' }}>
                @endif

                @error($field['key'])
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>

    @if(!$isLocked)
        <div class="d-flex justify-content-between mt-4">
            @if($stepIndex > 0)
                <a href="{{ route('applicant.application.show', ['step' => $stepIndex - 1]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Previous
                </a>
            @else
                <div></div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" name="_next" value="0" class="btn btn-outline-primary">
                    <i class="bi bi-save me-1"></i> Save
                </button>
                @if($stepIndex < count($sections) - 1)
                    <button type="submit" name="_next" value="{{ $stepIndex + 1 }}" class="btn btn-primary">
                        Save & Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @endif
            </div>
        </div>
    @endif
</form>
