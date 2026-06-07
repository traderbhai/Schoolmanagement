@extends('layouts.student')
@section('title', 'Apply for Leave')

@section('content')
<div class="container py-4" style="max-width:600px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.leave.index') }}">Leave Applications</a></li>
            <li class="breadcrumb-item active">New Application</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Apply for Leave</h4>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('student.leave.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" value="{{ old('from_date') }}" class="form-control @error('from_date') is-invalid @enderror" min="{{ date('Y-m-d') }}">
                        @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" value="{{ old('to_date') }}" class="form-control @error('to_date') is-invalid @enderror" min="{{ date('Y-m-d') }}">
                        @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" value="{{ old('reason') }}" class="form-control @error('reason') is-invalid @enderror" placeholder="e.g. Medical, Family emergency">
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Additional Details <span class="text-muted">(optional)</span></label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Provide more details...">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Supporting Document <span class="text-muted">(optional, max 5MB)</span></label>
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror">
                        @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                    <a href="{{ route('student.leave.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
