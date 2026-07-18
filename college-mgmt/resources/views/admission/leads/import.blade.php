@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">

    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <h1 class="mb-0">Import Leads</h1>
            <a href="{{ route('admission.leads.index') }}" class="btn btn-outline-secondary">
                &larr; Back to Leads
            </a>
        </div>
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Import failed:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Import results --}}
    @if(session('import_results'))
        @php $results = session('import_results'); @endphp
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-light fw-semibold">
                Import Results
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 flex-wrap mb-3">
                    <span class="badge bg-success fs-6 px-3 py-2">
                        Created: {{ $results['created'] }}
                    </span>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        Updated: {{ $results['updated'] }}
                    </span>
                    <span class="badge bg-danger fs-6 px-3 py-2">
                        Skipped: {{ $results['skipped'] }}
                    </span>
                </div>

                @if(!empty($results['errors']))
                    <div>
                        <p class="fw-semibold text-muted mb-1">Row-level warnings / errors ({{ count($results['errors']) }}):</p>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                            <ul class="mb-0 ps-3" style="font-size: 0.875rem;">
                                @foreach($results['errors'] as $err)
                                    <li class="text-danger">{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Upload form --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light fw-semibold">
            Upload CSV File
        </div>
        <div class="card-body">
            <form action="{{ route('admission.leads.import') }}"
                  method="POST"
                  enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="csv_file" class="form-label fw-semibold">
                        Select CSV / TXT File <span class="text-danger">*</span>
                    </label>
                    <input
                        type="file"
                        class="form-control @error('csv_file') is-invalid @enderror"
                        id="csv_file"
                        name="csv_file"
                        accept=".csv,.txt"
                    >
                    @error('csv_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted">
                        Accepted formats: .csv, .txt &mdash; Maximum size: 2 MB
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Import Leads
                </button>
            </form>
        </div>
    </div>

    {{-- Sample CSV format --}}
    <div class="accordion" id="sampleAccordion">
        <div class="accordion-item border-0 shadow-sm">
            <h2 class="accordion-header" id="sampleHeading">
                <button
                    class="accordion-button collapsed fw-semibold"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#sampleCollapse"
                    aria-expanded="false"
                    aria-controls="sampleCollapse"
                >
                    Sample CSV Format
                </button>
            </h2>
            <div
                id="sampleCollapse"
                class="accordion-collapse collapse"
                aria-labelledby="sampleHeading"
                data-bs-parent="#sampleAccordion"
            >
                <div class="accordion-body">
                    <p class="text-muted mb-2">
                        Your CSV must include at minimum the <code>name</code> and <code>email</code> columns.
                        All other columns are optional. Use the exact column names shown below.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm font-monospace" style="font-size: 0.85rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">name</th>
                                    <th scope="col">email</th>
                                    <th scope="col">phone</th>
                                    <th scope="col">program_code</th>
                                    <th scope="col">source</th>
                                    <th scope="col">notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>John Smith</td>
                                    <td>john.smith@example.com</td>
                                    <td>9876543210</td>
                                    <td>BTECH-CS</td>
                                    <td>referral</td>
                                    <td>Interested in scholarships</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <p class="mb-1 fw-semibold text-muted" style="font-size: 0.875rem;">Valid values for <code>source</code>:</p>
                        <p class="text-muted" style="font-size: 0.875rem;">
                            <code>web_form</code> &bull;
                            <code>referral</code> &bull;
                            <code>advertisement</code> &bull;
                            <code>social_media</code> &bull;
                            <code>event</code> &bull;
                            <code>agent</code> &bull;
                            <code>other</code>
                        </p>
                        <p class="text-muted mb-0" style="font-size: 0.875rem;">
                            If <code>source</code> is omitted or invalid, it defaults to <code>web_form</code>.<br>
                            <code>program_code</code> must match the code of an active program; if unrecognised the lead is imported without a program assignment.<br>
                            Leads with an existing email that have already been <strong>converted</strong> to applicants will be skipped entirely.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
