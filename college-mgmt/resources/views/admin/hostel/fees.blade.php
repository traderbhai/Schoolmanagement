@extends('layouts.admin')

@section('title', 'Hostel Fee Demands')
@section('page-title', 'Hostel Fee Demands')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">Fees</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pending</div>
                <div class="fs-3 fw-bold">{{ $stats['pending'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Paid</div>
                <div class="fs-3 fw-bold text-success">{{ $stats['paid'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Waived</div>
                <div class="fs-3 fw-bold text-secondary">{{ $stats['waived'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pending Amount</div>
                <div class="fs-3 fw-bold text-danger">Rs. {{ number_format($stats['pending_amount'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Generate Monthly Demands</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.hostel.fees.generate') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Month</label>
                <input type="month" name="month" value="{{ old('month', now()->format('Y-m')) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Generate Demands
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['pending' => 'Pending', 'paid' => 'Paid', 'waived' => 'Waived'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" name="month" value="{{ request('month') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('admin.hostel.fees') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        @if($demands->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                No hostel fee demands found.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Room</th>
                            <th>Month</th>
                            <th class="text-end">Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($demands as $demand)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $demand->student->user->name ?? 'Unknown Student' }}</div>
                                    <div class="small text-muted">{{ $demand->student->enrollment_number ?? '' }}</div>
                                </td>
                                <td>
                                    {{ $demand->allocation->room->block->name ?? 'Hostel' }} /
                                    Room {{ $demand->allocation->room->room_number ?? '-' }}
                                </td>
                                <td>{{ $demand->month }}</td>
                                <td class="text-end fw-semibold">Rs. {{ number_format($demand->amount, 2) }}</td>
                                <td>{{ $demand->due_date?->format('d M Y') ?? '-' }}</td>
                                <td>
                                    @if($demand->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($demand->status === 'waived')
                                        <span class="badge bg-secondary">Waived</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($demand->status === 'pending')
                                        <form method="POST" action="{{ route('admin.hostel.fees.paid', $demand) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success">Mark Paid</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.hostel.fees.waive', $demand) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Waive</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @if($demands->hasPages())
        <div class="card-footer bg-transparent">{{ $demands->links() }}</div>
    @endif
</div>
@endsection
