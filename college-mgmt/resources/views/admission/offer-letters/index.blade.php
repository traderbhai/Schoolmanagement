@extends('layouts.admin')

@section('title', 'Offer Letters — ' . $program->name)
@section('page-title', 'Offer Letters')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Offer Letters - {{ $program->name }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admission.merit-list.show', $program) }}" class="btn btn-primary">Back to Merit List</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Offers</h5>
                    <h2 class="text-info">{{ $stats['total'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Issued</h5>
                    <h2 class="text-primary">{{ $stats['issued'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Accepted</h5>
                    <h2 class="text-success">{{ $stats['accepted'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Declined</h5>
                    <h2 class="text-danger">{{ $stats['declined'] }}</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Generate Offer Letters</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admission.offer-letters.bulk-generate', $program) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" class="form-control">
                            <option value="">All Batches</option>
                            @foreach($batches as $batch)
                                <option value="{{ $batch->id }}" {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                    {{ $batch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Acceptance Days</label>
                        <input type="number" name="acceptance_days" class="form-control" value="14" min="1" max="180" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Generate for All Selected</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admission.offer-letters.index', $program) }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Batch</label>
                    <select name="batch_id" class="form-control">
                        <option value="">All Batches</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" {{ $batchId == $batch->id ? 'selected' : '' }}>
                                {{ $batch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="issued" {{ $status == 'issued' ? 'selected' : '' }}>Issued</option>
                        <option value="accepted" {{ $status == 'accepted' ? 'selected' : '' }}>Accepted</option>
                        <option value="declined" {{ $status == 'declined' ? 'selected' : '' }}>Declined</option>
                        <option value="expired" {{ $status == 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="{{ route('admission.offer-letters.index', $program) }}" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Offer Letters Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Offer #</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerLetters as $offer)
                        <tr>
                            <td><strong>{{ $offer->offer_number }}</strong></td>
                            <td>{{ $offer->applicant->user->name }}</td>
                            <td>{{ $offer->applicant->user->email }}</td>
                            <td>{{ $offer->batch->name }}</td>
                            <td>
                                <span class="{{ $offer->status_badge }}">{{ $offer->status_label }}</span>
                            </td>
                            <td>{{ $offer->acceptance_deadline->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('admission.offer-letters.show', $offer) }}" class="btn btn-sm btn-info">View</a>
                                <a href="{{ route('admission.offer-letters.export', $offer) }}" class="btn btn-sm btn-secondary">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No offer letters found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $offerLetters->links() }}
</div>
@endsection
