@extends('layouts.student')
@section('title', 'Outpass Request')
@section('page-title', 'Outpass Request')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Outpass Request</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if(!$allocation)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        You do not have an active hostel allocation. Please contact the hostel warden.
    </div>
@else
    <div class="alert alert-info mb-4">
        <strong>Your Room:</strong> {{ $allocation->room?->block?->name }} / Room {{ $allocation->room?->room_number }}, Bed {{ $allocation->bed_number }}
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">New Outpass Request</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('student.hostel.outpass.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Reason for Going Out <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" required placeholder="Please explain the reason...">{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Out Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="out_datetime" class="form-control @error('out_datetime') is-invalid @enderror" value="{{ old('out_datetime') }}" required>
                            @error('out_datetime')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expected Return <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="expected_return" class="form-control @error('expected_return') is-invalid @enderror" value="{{ old('expected_return') }}" required>
                            @error('expected_return')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">My Recent Requests</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Out DateTime</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $req)
                                @php $statusColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','returned'=>'info']; @endphp
                                <tr>
                                    <td>{{ $req->out_datetime?->format('d M y H:i') }}</td>
                                    <td>{{ $req->expected_return?->format('d M y H:i') }}</td>
                                    <td><span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">{{ ucfirst($req->status) }}</span></td>
                                    <td>{{ $req->remarks ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No outpass requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection
