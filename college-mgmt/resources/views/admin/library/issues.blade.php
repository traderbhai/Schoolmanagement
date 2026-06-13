@extends('layouts.admin')
@section('title', 'Book Issues')
@section('page-title', 'Book Issues')
@section('content')
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                <option value="">All Status</option>
                @foreach(['issued','overdue','returned','lost'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.library.issues') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Book</th><th>Copy</th><th>Borrower</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($issues as $issue)
                <tr>
                    <td><small>{{ Str::limit($issue->bookCopy?->book?->title ?? '—', 30) }}</small></td>
                    <td><small class="text-muted">{{ $issue->bookCopy?->accession_number }}</small></td>
                    <td><small>{{ $issue->student?->user?->name ?? $issue->teacher?->user?->name ?? '—' }}</small></td>
                    <td><small>{{ $issue->issued_at?->format('d M Y') }}</small></td>
                    <td><small class="{{ $issue->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $issue->due_date?->format('d M Y') }}</small></td>
                    <td><small>{{ $issue->returned_at?->format('d M Y') ?? '—' }}</small></td>
                    <td><small>{{ $issue->fine_amount > 0 ? '₹'.$issue->fine_amount : '—' }}</small></td>
                    <td>@php $colors=['issued'=>'primary','overdue'=>'danger','returned'=>'success','lost'=>'dark']; @endphp<span class="badge bg-{{ $colors[$issue->status] ?? 'secondary' }}">{{ ucfirst($issue->status) }}</span></td>
                    <td>@if(!$issue->returned_at)<form method="POST" action="{{ route('admin.library.issues.return', $issue) }}" class="d-inline">@csrf<button class="btn btn-xs btn-outline-success">Return</button></form>@endif</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No issues found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($issues->hasPages())<div class="card-footer bg-white">{{ $issues->links() }}</div>@endif
</div>
@endsection
