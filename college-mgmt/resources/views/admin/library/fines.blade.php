@extends('layouts.admin')
@section('title', 'Library Fines')
@section('page-title', 'Fine Collection')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom fw-semibold">Unpaid Library Fines</div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Borrower</th><th>Book</th><th>Issued</th><th>Due Date</th><th>Days Overdue</th><th>Fine</th><th></th></tr></thead>
            <tbody>
                @forelse($issues as $issue)
                <tr>
                    <td>{{ $issue->student?->user?->name ?? $issue->teacher?->user?->name ?? '—' }}</td>
                    <td><small>{{ Str::limit($issue->bookCopy?->book?->title ?? '—', 40) }}</small></td>
                    <td><small>{{ $issue->issued_at?->format('d M Y') }}</small></td>
                    <td><small class="text-danger">{{ $issue->due_date?->format('d M Y') }}</small></td>
                    <td>{{ $issue->days_overdue }} days</td>
                    <td><strong>₹{{ number_format($issue->fine_amount, 2) }}</strong></td>
                    <td><form method="POST" action="{{ route('admin.library.fines.pay', $issue) }}">@csrf<button class="btn btn-xs btn-success">Mark Paid</button></form></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No unpaid fines.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($issues->hasPages())<div class="card-footer bg-white">{{ $issues->links() }}</div>@endif
</div>
@endsection
