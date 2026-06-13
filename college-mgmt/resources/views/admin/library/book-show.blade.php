@extends('layouts.admin')
@section('title', $book->title)
@section('page-title', 'Book Details')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">{{ $book->title }}</h5>
                <p class="text-muted mb-1">{{ $book->author }}</p>
                @if($book->isbn)<p class="small text-muted mb-2">ISBN: {{ $book->isbn }}</p>@endif
                <hr>
                <dl class="row small mb-0">
                    <dt class="col-5">Publisher</dt><dd class="col-7">{{ $book->publisher ?? '—' }}</dd>
                    <dt class="col-5">Edition</dt><dd class="col-7">{{ $book->edition ?? '—' }}</dd>
                    <dt class="col-5">Year</dt><dd class="col-7">{{ $book->year_of_publication ?? '—' }}</dd>
                    <dt class="col-5">Category</dt><dd class="col-7">{{ $book->category ?? '—' }}</dd>
                    <dt class="col-5">Location</dt><dd class="col-7">{{ $book->location ?? '—' }}</dd>
                    <dt class="col-5">Total Copies</dt><dd class="col-7">{{ $book->total_copies }}</dd>
                    <dt class="col-5">Available</dt><dd class="col-7"><span class="badge bg-{{ $book->available_copies > 0 ? 'success' : 'danger' }}">{{ $book->available_copies }}</span></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold">Copies</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Accession No.</th><th>Condition</th><th>Status</th><th>Current Holder</th></tr></thead>
                    <tbody>
                        @foreach($book->copies as $copy)
                        <tr>
                            <td><small>{{ $copy->accession_number }}</small></td>
                            <td><span class="badge bg-{{ ['good'=>'success','fair'=>'warning','damaged'=>'danger','lost'=>'dark'][$copy->condition_status] ?? 'secondary' }}">{{ ucfirst($copy->condition_status) }}</span></td>
                            <td><span class="badge bg-{{ $copy->is_available ? 'success' : 'secondary' }}">{{ $copy->is_available ? 'Available' : 'Issued' }}</span></td>
                            <td><small>{{ $copy->currentIssue?->student?->user?->name ?? $copy->currentIssue?->teacher?->user?->name ?? '—' }}</small></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Issue History</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Borrower</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th></tr></thead>
                    <tbody>
                        @forelse($history as $h)
                        <tr>
                            <td><small>{{ $h->student?->user?->name ?? $h->teacher?->user?->name ?? '—' }}</small></td>
                            <td><small>{{ $h->issued_at?->format('d M Y') }}</small></td>
                            <td><small>{{ $h->due_date?->format('d M Y') }}</small></td>
                            <td><small>{{ $h->returned_at?->format('d M Y') ?? '—' }}</small></td>
                            <td><small>{{ $h->fine_amount > 0 ? '₹'.$h->fine_amount : '—' }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-2">No history.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
