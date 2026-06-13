@extends('layouts.student')
@section('title', 'Hostel Complaints')
@section('page-title', 'Hostel Complaints')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Hostel Complaints</li>
@endsection

@section('content')
<div class="container-fluid py-3" style="max-width:1080px">
    @if(!$allocation)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            You do not have an active hostel allocation. Contact the hostel office if this is incorrect.
        </div>
    @else
        <div class="alert alert-info mb-4">
            <strong>Your Room:</strong> {{ $allocation->room?->block?->name }} / Room {{ $allocation->room?->room_number }}, Bed {{ $allocation->bed_number }}
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">New Complaint</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('student.hostel.complaints.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255" placeholder="e.g. Fan not working">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                        @foreach(['maintenance' => 'Maintenance', 'hygiene' => 'Hygiene', 'food' => 'Food', 'security' => 'Security', 'ragging' => 'Ragging', 'other' => 'Other'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                        @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Details <span class="text-danger">*</span></label>
                                <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required minlength="20" maxlength="2000" placeholder="Describe the issue, location, and urgency.">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-primary w-100">Submit Complaint</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">My Complaints</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Issue</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($complaints as $complaint)
                                @php
                                    $priorityColors = ['low' => 'success', 'medium' => 'warning text-dark', 'high' => 'danger'];
                                    $statusColors = ['open' => 'warning text-dark', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $complaint->title }}</div>
                                        <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $complaint->category)) }}</div>
                                        @if($complaint->resolution_notes)
                                            <div class="small text-muted mt-1">{{ $complaint->resolution_notes }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $priorityColors[$complaint->priority] ?? 'secondary' }}">{{ ucfirst($complaint->priority) }}</span></td>
                                    <td><span class="badge bg-{{ $statusColors[$complaint->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span></td>
                                    <td class="small text-muted">{{ $complaint->updated_at->format('d M Y') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No hostel complaints submitted yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($complaints->hasPages())
                        <div class="card-footer bg-transparent">{{ $complaints->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
