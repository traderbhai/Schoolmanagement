@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>{{ $lead->name }}</h1>
            <p class="text-muted">{{ $lead->email }}</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admission.leads.index') }}" class="btn btn-primary">Back to Leads</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted">Status</h6>
                    <span class="{{ $lead->status_badge }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted">Source</h6>
                    <p class="mb-0">{{ $lead->source_label }}</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted">Program Interest</h6>
                    <p class="mb-0">{{ $lead->program?->name ?? 'Not specified' }}</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted">Last Contacted</h6>
                    <p class="mb-0">{{ $lead->last_contacted_at?->format('d M Y H:i') ?? 'Never' }}</p>
                </div>
            </div>

            @if($lead->isConverted())
                <div class="card mb-3 border-success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Converted To</h6>
                        <p class="mb-0">
                            <a href="#">{{ $lead->convertedApplicant?->user->name }}</a>
                        </p>
                        <small class="text-muted">{{ $lead->converted_at?->format('d M Y H:i') }}</small>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Lead Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> {{ $lead->name }}</p>
                            <p><strong>Email:</strong> <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Phone:</strong> {{ $lead->phone ?? '-' }}</p>
                            <p><strong>Created:</strong> {{ $lead->created_at->format('d M Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Notes</h5>
                </div>
                <div class="card-body">
                    @if($lead->notes)
                        <p>{{ $lead->notes }}</p>
                    @else
                        <p class="text-muted">No notes yet.</p>
                    @endif
                </div>
            </div>

            @if(!$lead->isConverted())
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($lead->status === 'new')
                                <div class="col-md-6 mb-3">
                                    <form action="{{ route('admission.leads.contact', $lead) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about the contact..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Mark as Contacted</button>
                                    </form>
                                </div>
                            @endif

                            @if(in_array($lead->status, ['new', 'contacted']))
                                <div class="col-md-6 mb-3">
                                    <form action="{{ route('admission.leads.interested', $lead) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Add notes..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100">Mark as Interested</button>
                                    </form>
                                </div>
                            @endif

                            @if(!in_array($lead->status, ['not_interested']))
                                <div class="col-md-6 mb-3">
                                    <form action="{{ route('admission.leads.not-interested', $lead) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Reason (Optional)</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Why not interested..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-danger w-100">Mark as Not Interested</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
