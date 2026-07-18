@extends('layouts.admin')
@section('title', 'Library Reservations')
@section('page-title', 'Library Reservations')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.index') }}">Library</a></li>
    <li class="breadcrumb-item active">Reservations</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <input aria-label="Book, ISBN, borrower" type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Book, ISBN, borrower">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'fulfilled' => 'Fulfilled', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Apply reservation filters</button>
                <a href="{{ route('admin.library.reservations') }}" class="btn btn-sm btn-outline-secondary">Clear reservation filters</a>
                <a href="{{ route('admin.library.reservations.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary ms-auto">Export reservation view</a>
                <a href="{{ route('admin.library.issues') }}" class="btn btn-sm btn-outline-primary">Issues</a>
            </div>
        </form>
    </div>
</div>
<div class="text-muted small mb-2">Showing {{ $reservations->total() }} reservation record(s){{ request('status') ? ' filtered by status: '.request('status') : '' }}{{ request('search') ? ' and search: '.request('search') : '' }}.</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Borrower</th>
                    <th scope="col">Reserved</th>
                    <th scope="col">Expires</th>
                    <th scope="col">Status</th>
                    <th scope="col">Available</th>
                    <th scope="col" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($reservations as $reservation)
                @php
                    $available = (int) ($availableCopiesByBook[$reservation->book_id] ?? 0);
                    $borrower = $reservation->student?->user?->name ?? $reservation->teacher?->user?->name ?? 'Borrower removed';
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $reservation->book?->title ?? 'Book removed' }}</div>
                        <div class="small text-muted">{{ $reservation->book?->isbn ?? 'No ISBN' }}</div>
                    </td>
                    <td>{{ $borrower }}</td>
                    <td>{{ $reservation->reserved_at?->format('d M Y') }}</td>
                    <td>
                        <span @class(['text-danger fw-semibold' => $reservation->status === 'pending' && $reservation->expires_at?->isPast()])>
                            {{ $reservation->expires_at?->format('d M Y') }}
                        </span>
                    </td>
                    <td><span class="badge text-bg-{{ $reservation->status === 'pending' ? 'warning' : ($reservation->status === 'fulfilled' ? 'success' : 'secondary') }}">{{ ucfirst($reservation->status) }}</span></td>
                    <td><span class="badge text-bg-{{ $available > 0 ? 'success' : 'secondary' }}">{{ $available }}</span></td>
                    <td class="text-end">
                        @if($reservation->status === 'pending')
                            <form method="POST" action="{{ route('admin.library.reservations.fulfill', $reservation) }}" class="d-inline" onsubmit="return confirm('Fulfil reservation for {{ addslashes($reservation->book?->title ?? 'this book') }} and issue an available copy to {{ addslashes($borrower) }}? Confirm copy availability, borrower eligibility, due date, and queue fairness before issuing.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" @disabled($available < 1)>Fulfil reservation</button>
                            </form>
                            <form method="POST" action="{{ route('admin.library.reservations.cancel', $reservation) }}" class="d-inline" onsubmit="return confirm('Cancel reservation for {{ addslashes($reservation->book?->title ?? 'this book') }} by {{ addslashes($borrower) }}? Confirm borrower communication and queue impact before cancellation.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel reservation</button>
                            </form>
                        @else
                            <span class="text-muted small">No action</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No reservations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($reservations->hasPages())
        <div class="card-footer bg-white">{{ $reservations->links() }}</div>
    @endif
</div>
@endsection
