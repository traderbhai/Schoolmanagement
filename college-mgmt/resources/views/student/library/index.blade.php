@extends('layouts.student')
@section('title', 'My Library')
@section('page-title', 'Library')
@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold"><i class="bi bi-book me-2 text-primary"></i>Currently Borrowed</div>
            <div class="card-body p-0">
                @if($currentIssues->isEmpty())
                    <div class="text-center text-muted py-4">No books currently borrowed.</div>
                @else
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($currentIssues as $issue)
                        <tr>
                            <td><small>{{ $issue->bookCopy?->book?->title ?? '—' }}</small></td>
                            <td><small class="{{ $issue->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $issue->due_date?->format('d M Y') }}@if($issue->is_overdue)<br><span style="font-size:.7rem">{{ $issue->days_overdue }} days overdue</span>@endif</small></td>
                            <td><span class="badge bg-{{ $issue->status==='overdue'?'danger':'primary' }}">{{ ucfirst($issue->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
    @if($fines->isNotEmpty())
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-danger">
            <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Outstanding Fines</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Fine</th></tr></thead>
                    <tbody>@foreach($fines as $f)<tr><td><small>{{ $f->bookCopy?->book?->title ?? '—' }}</small></td><td><strong class="text-danger">₹{{ number_format($f->fine_amount,2) }}</strong></td></tr>@endforeach</tbody>
                </table>
                <div class="p-2 border-top"><small class="text-muted">Please pay at the library counter.</small></div>
            </div>
        </div>
    </div>
    @endif
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Borrowing History</div>
            <div class="card-body p-0">
                @if($history->isEmpty())
                    <div class="text-center text-muted py-3">No borrowing history.</div>
                @else
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Issued</th><th>Returned</th><th>Fine</th></tr></thead>
                    <tbody>
                        @foreach($history as $h)
                        <tr>
                            <td><small>{{ $h->bookCopy?->book?->title ?? '—' }}</small></td>
                            <td><small>{{ $h->issued_at?->format('d M Y') }}</small></td>
                            <td><small>{{ $h->returned_at?->format('d M Y') ?? '—' }}</small></td>
                            <td><small>{{ $h->fine_amount > 0 ? '₹'.$h->fine_amount : '—' }}</small></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
