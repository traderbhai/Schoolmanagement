@extends('layouts.admin')

@section('title', 'Edit Session')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Edit Session</h2>
    <a href="{{ route('admission.sessions.show', $session) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Session
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admission.sessions.update', $session) }}">
            @csrf
            @method('PUT')
            @include('admission.sessions._form', ['session' => $session])
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
                <a href="{{ route('admission.sessions.show', $session) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('program_id').addEventListener('change', function() {
    const programId = this.value;
    const stepSelect = document.getElementById('selection_process_step_id');
    stepSelect.innerHTML = '<option value="">Select Step</option>';

    if (!programId) return;

    const steps = @json($programs->keyBy('id')->map(fn($p) => $p->selectionProcessSteps));
    const programSteps = steps[programId] || [];
    programSteps.forEach(function(step) {
        const opt = document.createElement('option');
        opt.value = step.id;
        opt.textContent = step.name + ' (' + step.type.toUpperCase() + ')';
        stepSelect.appendChild(opt);
    });
});
</script>
@endpush
