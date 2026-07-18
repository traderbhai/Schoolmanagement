@extends('layouts.admin')

@section('title', 'Copy Timetable from Previous Term')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Copy Timetable from Previous Term</h2>
            <p class="text-muted">Duplicate timetable entries from a previous term to quickly set up the current term.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('chair.timetable.builder') }}" class="btn btn-secondary">Back to Builder</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form id="copyForm" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="program_id" class="form-label">Program *</label>
                            <select name="program_id" id="program_id" class="form-select" required onchange="updateSourceTerms()">
                                <option value="">Select Program</option>
                                @foreach ($programs as $prog)
                                    <option value="{{ $prog->id }}" {{ $selectedProgram?->id == $prog->id ? 'selected' : '' }}>
                                        {{ $prog->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="source_term_id" class="form-label">Source Term (From) *</label>
                                    <select name="source_term_id" id="source_term_id" class="form-select" required onchange="previewCopy()">
                                        <option value="">Select Source Term</option>
                                        <option id="source_terms_placeholder" disabled>Loading available terms...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="target_term_id" class="form-label">Target Term (To) *</label>
                                    <select name="target_term_id" id="target_term_id" class="form-select" required onchange="previewCopy()">
                                        <option value="">Select Target Term</option>
                                        @foreach ($allTerms as $term)
                                            <option value="{{ $term->id }}" {{ $selectedTargetTerm?->id == $term->id ? 'selected' : '' }}>
                                                {{ $term->name }} ({{ $term->start_date->format('M Y') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="batch_id" class="form-label">Batch (Optional)</label>
                            <select name="batch_id" id="batch_id" class="form-select" onchange="previewCopy()">
                                <option value="">All Batches</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" {{ $selectedBatch?->id == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">If selected, only this batch's timetable will be copied.</small>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <h5>Copy Options</h5>
                            <div class="form-check">
                                <input type="checkbox" name="replace_existing" id="replace_existing" class="form-check-input" value="1">
                                <label class="form-check-label" for="replace_existing">
                                    <strong>Replace existing entries</strong>
                                    <small class="text-muted d-block">If unchecked, won't copy to slots already occupied</small>
                                </label>
                            </div>

                            <div class="form-check mt-2">
                                <input type="checkbox" name="reassign_teachers" id="reassign_teachers" class="form-check-input" value="1">
                                <label class="form-check-label" for="reassign_teachers">
                                    <strong>Reassign teachers</strong>
                                    <small class="text-muted d-block">Try to find substitute teachers if originals unavailable</small>
                                </label>
                            </div>

                            <div class="form-check mt-2">
                                <input type="checkbox" name="reassign_classrooms" id="reassign_classrooms" class="form-check-input" value="1">
                                <label class="form-check-label" for="reassign_classrooms">
                                    <strong>Reassign classrooms</strong>
                                    <small class="text-muted d-block">Find rooms with similar capacity if originals unavailable</small>
                                </label>
                            </div>
                        </div>

                        <div id="previewSection" class="d-none">
                            <hr>
                            <div id="previewResult"></div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" id="previewBtn" class="btn btn-primary" onclick="previewCopy()">
                                <i class="fa fa-eye"></i> Preview
                            </button>
                            <button type="submit" id="copyBtn" class="btn btn-success" disabled>
                                <i class="fa fa-copy"></i> Copy Timetable
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">How It Works</h5>
                    <ol class="small">
                        <li>Select source term (from)</li>
                        <li>Select target term (to)</li>
                        <li>Choose copy options</li>
                        <li>Click Preview</li>
                        <li>Review conflicts</li>
                        <li>Click Copy</li>
                    </ol>

                    <hr>

                    <p class="small"><strong>Time Saved:</strong></p>
                    <p class="small text-success">
                        Saves <strong>80% time</strong> vs manual entry — most subjects repeat every term.
                    </p>

                    <hr>

                    <p class="small"><strong>Notes:</strong></p>
                    <ul class="small">
                        <li>Creates new entries (doesn't delete old ones)</li>
                        <li>Validates for conflicts</li>
                        <li>Entries are in draft status</li>
                        <li>Can be edited before publishing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let availableSourceTerms = {!! json_encode($availableSourceTerms) !!};

function updateSourceTerms() {
    const programId = document.getElementById('program_id').value;
    const sourceSelect = document.getElementById('source_term_id');

    sourceSelect.innerHTML = '<option value="">Select Source Term</option>';

    if (!programId) {
        sourceSelect.innerHTML += '<option disabled>Select a program first</option>';
        return;
    }

    if (!availableSourceTerms.length) {
        sourceSelect.innerHTML += '<option disabled>No previous terms with timetable data</option>';
        return;
    }

    availableSourceTerms.forEach(term => {
        const option = document.createElement('option');
        option.value = term.id;
        option.textContent = `${term.name} (${term.start_date}) — ${term.entry_count} entries`;
        sourceSelect.appendChild(option);
    });
}

function previewCopy() {
    const programId = document.getElementById('program_id').value;
    const sourcTermId = document.getElementById('source_term_id').value;
    const targetTermId = document.getElementById('target_term_id').value;
    const batchId = document.getElementById('batch_id').value || null;

    if (!programId || !sourcTermId || !targetTermId) {
        alert('Please select program, source term, and target term');
        return;
    }

    const previewSection = document.getElementById('previewSection');
    const previewResult = document.getElementById('previewResult');
    previewResult.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Loading preview...';
    previewSection.classList.remove('d-none');

    fetch('{{ route('chair.timetable.preview-copy') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
            program_id: programId,
            source_term_id: sourcTermId,
            target_term_id: targetTermId,
            batch_id: batchId,
        }),
    })
    .then(r => r.json())
    .then(data => {
        let html = '';

        if (!data.can_copy && data.conflicts.length) {
            html += '<div class="alert alert-warning"><strong>⚠ Conflicts found:</strong><br>';
            data.conflicts.slice(0, 5).forEach(c => {
                html += c + '<br>';
            });
            if (data.conflicts.length > 5) {
                html += `... and ${data.conflicts.length - 5} more`;
            }
            html += '</div>';
            document.getElementById('copyBtn').disabled = true;
        } else {
            html += `<div class="alert alert-success"><strong>✓ Preview ready!</strong> ${data.source_count} entries from `;
            html += data.source_batches.join(', ') + '</div>';
            document.getElementById('copyBtn').disabled = false;
        }

        if (data.preview && data.preview.length) {
            html += '<div class="alert alert-info"><small><strong>Preview (first 5 rows):</strong><br>';
            data.preview.slice(0, 5).forEach(row => {
                html += `${row.day} Slot ${row.slot}: ${row.subject} (${row.teacher} in ${row.room}) — ${row.batch}<br>`;
            });
            if (data.preview.length > 5) {
                html += `... and ${data.preview.length - 5} more entries`;
            }
            html += '</small></div>';
        }

        previewResult.innerHTML = html;
    })
    .catch(err => {
        previewResult.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}

document.getElementById('copyForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const sourcTermId = document.getElementById('source_term_id').value;
    if (!sourcTermId) {
        alert('Please select source term');
        return;
    }

    if (!confirm('Copy timetable into the selected target term? Confirm source term, target term, copied slots, faculty/room conflicts, and draft-review status before creating new timetable entries.')) {
        return;
    }

    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Copying...';

    this.action = '{{ route('chair.timetable.execute-copy') }}';
    this.method = 'POST';
    this.submit();
});

// Load source terms on page load if program is selected
if (document.getElementById('program_id').value) {
    updateSourceTerms();
}
</script>
@endsection
