@extends('layouts.admin')

@section('title', 'Import Timetable from CSV')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Bulk Import Timetable from CSV</h2>
            <p class="text-muted">Upload a CSV file to quickly populate timetable slots for a program, term, and batch.</p>
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
                    <form id="importForm" enctype="multipart/form-data" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="program_id" class="form-label">Program *</label>
                            <select name="program_id" id="program_id" class="form-select" required>
                                <option value="">Select Program</option>
                                @foreach ($programs as $prog)
                                    <option value="{{ $prog->id }}" {{ $selectedProgram?->id == $prog->id ? 'selected' : '' }}>
                                        {{ $prog->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="term_id" class="form-label">Term *</label>
                            <select name="term_id" id="term_id" class="form-select" required>
                                <option value="">Select Term</option>
                                @foreach ($terms as $term)
                                    <option value="{{ $term->id }}" {{ $selectedTerm?->id == $term->id ? 'selected' : '' }}>
                                        {{ $term->name }} ({{ $term->start_date->format('M Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="batch_id" class="form-label">Batch (Optional)</label>
                            <select name="batch_id" id="batch_id" class="form-select">
                                <option value="">All Batches</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" {{ $selectedBatch?->id == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">If selected, all rows in CSV will use this batch.</small>
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">CSV File *</label>
                            <input type="file" name="file" id="file" class="form-control" accept=".csv,.txt" required>
                            <small class="text-muted">
                                Max 5MB. Required columns: day_of_week, timetable_slot_id, subject_id, teacher_id, classroom_id
                                {{ !$selectedBatch ? ', batch_id' : '' }}
                            </small>
                        </div>

                        <div id="validationResult" class="mb-3"></div>

                        <div class="d-flex gap-2">
                            <button type="button" id="validateBtn" class="btn btn-primary">
                                <i class="fa fa-check-circle"></i> Validate First
                            </button>
                            <button type="submit" id="importBtn" class="btn btn-success" disabled>
                                <i class="fa fa-upload"></i> Import CSV
                            </button>
                            <a href="{{ route('chair.timetable.download-sample', ['batch_id' => $selectedBatch?->id]) }}" class="btn btn-outline-secondary">
                                <i class="fa fa-download"></i> Download Sample
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title mb-3">CSV Format</h5>
                    <p class="small"><strong>Required Columns:</strong></p>
                    <ul class="small">
                        <li><code>day_of_week</code> — 1=Mon, 2=Tue, ... 6=Sat</li>
                        <li><code>timetable_slot_id</code> — Slot ID</li>
                        <li><code>subject_id</code> — Subject ID</li>
                        <li><code>teacher_id</code> — Teacher ID</li>
                        <li><code>classroom_id</code> — Room ID</li>
                        @if (!$selectedBatch)
                            <li><code>batch_id</code> — Batch ID</li>
                        @endif
                    </ul>

                    <hr>

                    <p class="small"><strong>Example Row:</strong></p>
                    <code class="small d-block bg-white p-2 rounded">1,1,5,3,2@if (!$selectedBatch),1@endif</code>

                    <hr>

                    <p class="small mb-2"><strong>Tips:</strong></p>
                    <ul class="small">
                        <li>No header row in CSV (just data)</li>
                        <li>Empty rows are skipped</li>
                        <li>Conflicts are reported</li>
                        <li>Validates before import</li>
                        <li>Replaces existing entries</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('validateBtn').addEventListener('click', async function() {
    const form = document.getElementById('importForm');
    const formData = new FormData(form);

    const result = document.getElementById('validationResult');
    result.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Validating...';

    try {
        const response = await fetch('{{ route('chair.timetable.validate-import') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        });

        const data = await response.json();

        let html = '';

        if (data.valid) {
            html = '<div class="alert alert-success">';
            html += '<strong>✓ Validation passed!</strong> ' + data.total_rows + ' rows ready to import.<br>';
            if (data.errors && data.errors.length) {
                html += '<small>' + data.errors.join('<br>') + '</small>';
            }
            html += '</div>';

            if (data.preview && data.preview.length) {
                html += '<div class="alert alert-info"><small><strong>Preview:</strong><br>';
                data.preview.slice(0, 5).forEach(row => {
                    html += `${row.day} Slot ${row.slot}: ${row.subject} (${row.teacher} in ${row.classroom})<br>`;
                });
                if (data.preview.length > 5) {
                    html += `... and ${data.preview.length - 5} more rows<br>`;
                }
                html += '</small></div>';
            }

            document.getElementById('importBtn').disabled = false;
        } else {
            html = '<div class="alert alert-danger"><strong>✗ Validation failed:</strong><br>';
            if (data.errors && data.errors.length) {
                data.errors.slice(0, 5).forEach(err => {
                    html += err + '<br>';
                });
                if (data.errors.length > 5) {
                    html += `... and ${data.errors.length - 5} more errors`;
                }
            }
            html += '</div>';
            document.getElementById('importBtn').disabled = true;
        }

        result.innerHTML = html;
    } catch (error) {
        result.innerHTML = '<div class="alert alert-danger">Validation error: ' + error.message + '</div>';
    }
});

document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!confirm('Import ' + document.getElementById('file').files[0].name + '? This will create/replace timetable entries.')) {
        return;
    }

    const form = this;
    const submitBtn = document.getElementById('importBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';

    form.action = '{{ route('chair.timetable.do-import') }}';
    form.method = 'POST';
    form.submit();
});
</script>
@endsection
