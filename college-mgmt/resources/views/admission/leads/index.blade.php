@extends('layouts.admin')

@section('title', 'Leads & Enquiries')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Leads & Enquiries</h1>
        </div>
        <div class="col-md-4 text-end d-flex justify-content-end gap-2">
            <a href="{{ route('admission.leads.export-csv', request()->query()) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
            <a href="{{ route('admission.leads.analytics') }}" class="btn btn-info">Analytics</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total</h6>
                    <h3 class="text-primary">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">New</h6>
                    <h3 class="text-info">{{ $stats['new'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Contacted</h6>
                    <h3 class="text-secondary">{{ $stats['contacted'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Interested</h6>
                    <h3 class="text-warning">{{ $stats['interested'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Converted</h6>
                    <h3 class="text-success">{{ $stats['converted'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Conversion</h6>
                    <h3 class="text-success">{{ $stats['conversion_rate'] }}%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admission.leads.index') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="new" {{ $status === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ $status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="interested" {{ $status === 'interested' ? 'selected' : '' }}>Interested</option>
                        <option value="not_interested" {{ $status === 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                        <option value="converted" {{ $status === 'converted' ? 'selected' : '' }}>Converted</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-control">
                        <option value="">All Sources</option>
                        <option value="web_form" {{ $source === 'web_form' ? 'selected' : '' }}>Web Form</option>
                        <option value="referral" {{ $source === 'referral' ? 'selected' : '' }}>Referral</option>
                        <option value="advertisement" {{ $source === 'advertisement' ? 'selected' : '' }}>Advertisement</option>
                        <option value="social_media" {{ $source === 'social_media' ? 'selected' : '' }}>Social Media</option>
                        <option value="event" {{ $source === 'event' ? 'selected' : '' }}>Event</option>
                        <option value="agent" {{ $source === 'agent' ? 'selected' : '' }}>Agent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-control">
                        <option value="">All Programs</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" {{ $programId == $program->id ? 'selected' : '' }}>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leads Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Program</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Last Contacted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td><strong>{{ $lead->name }}</strong></td>
                            <td>{{ $lead->email }}</td>
                            <td>{{ $lead->phone ?? '-' }}</td>
                            <td>{{ $lead->program?->name ?? '-' }}</td>
                            <td>{{ $lead->source_label }}</td>
                            <td><span class="{{ $lead->status_badge }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span></td>
                            <td>{{ $lead->last_contacted_at?->format('d M Y H:i') ?? 'Never' }}</td>
                            <td>
                                <a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-info">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No leads found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $leads->links() }}
</div>
@endsection
