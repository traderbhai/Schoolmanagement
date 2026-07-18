@php $editing = isset($session) && $session; @endphp

<div class="row g-3">
    {{-- Program --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
        <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror" required>
            <option value="">Select Program</option>
            @foreach($programs as $p)
                <option value="{{ $p->id }}" @selected(old('program_id', $editing ? $session->program_id : '') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        @error('program_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Step --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Selection Step <span class="text-danger">*</span></label>
        <select name="selection_process_step_id" id="selection_process_step_id" class="form-select @error('selection_process_step_id') is-invalid @enderror" required>
            <option value="">Select Step</option>
            @if($editing)
                @foreach($programs->find($session->program_id)?->selectionProcessSteps ?? [] as $step)
                    <option value="{{ $step->id }}" @selected($session->selection_process_step_id == $step->id)>{{ $step->name }} ({{ strtoupper($step->type) }})</option>
                @endforeach
            @endif
        </select>
        @error('selection_process_step_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Session Name --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Session Name <span class="text-danger">*</span></label>
        <input aria-label="Session Name" type="text" name="session_name" class="form-control @error('session_name') is-invalid @enderror"
               value="{{ old('session_name', $editing ? $session->session_name : '') }}" placeholder="e.g. WAT Round 1 - Morning Batch" required>
        @error('session_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Batch --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Batch (Optional)</label>
        <select aria-label="Batch" name="batch_id" class="form-select @error('batch_id') is-invalid @enderror">
            <option value="">All Batches</option>
            @foreach($batches as $b)
                <option value="{{ $b->id }}" @selected(old('batch_id', $editing ? $session->batch_id : '') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Date --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">Scheduled Date <span class="text-danger">*</span></label>
        <input aria-label="Scheduled Date" type="date" name="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror"
               value="{{ old('scheduled_date', $editing ? $session->scheduled_date->format('Y-m-d') : '') }}" required>
        @error('scheduled_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Start Time --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
        <input aria-label="Start Time" type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror"
               value="{{ old('start_time', $editing ? \Carbon\Carbon::parse($session->start_time)->format('H:i') : '') }}" required>
        @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- End Time --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
        <input aria-label="End Time" type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror"
               value="{{ old('end_time', $editing ? \Carbon\Carbon::parse($session->end_time)->format('H:i') : '') }}" required>
        @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Venue --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Venue</label>
        <input aria-label="Venue" type="text" name="venue" class="form-control"
               value="{{ old('venue', $editing ? $session->venue : '') }}" placeholder="e.g. Seminar Hall A">
    </div>

    {{-- Max Candidates --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">Max Candidates</label>
        <input aria-label="Max Candidates" type="number" name="max_candidates" class="form-control" min="1"
               value="{{ old('max_candidates', $editing ? $session->max_candidates : '') }}" placeholder="Leave blank for unlimited">
    </div>

    {{-- Instructions --}}
    <div class="col-12">
        <label class="form-label fw-semibold">Instructions</label>
        <textarea aria-label="Session instructions" name="instructions" class="form-control" rows="3" placeholder="Special instructions for this session...">{{ old('instructions', $editing ? $session->instructions : '') }}</textarea>
    </div>

    @if(!$editing)
    {{-- Auto-assign --}}
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="auto_assign" id="auto_assign" value="1" {{ old('auto_assign') ? 'checked' : '' }}>
            <label class="form-check-label" for="auto_assign">
                <strong>Auto-assign all shortlisted applicants</strong> from the selected program/batch to this session
            </label>
        </div>
    </div>
    @endif
</div>
