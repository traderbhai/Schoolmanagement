@extends('layouts.admin')
@section('title', 'Internship Detail')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('cmc.internships.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">{{ $internship->role_title }} @ {{ $internship->company_name }}</h4>
            <span class="text-muted small">Internship #{{ $internship->id }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $statusColor = ['ongoing'=>'info','completed'=>'success','dropped'=>'secondary'][$internship->status] ?? 'secondary';
    @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Student</dt>
                        <dd class="col-sm-9">{{ $internship->student->user->name ?? '-' }} ({{ $internship->student->enrollment_number ?? '' }})</dd>
                        <dt class="col-sm-3 text-muted">Company</dt>
                        <dd class="col-sm-9">{{ $internship->company_name }}</dd>
                        <dt class="col-sm-3 text-muted">Role</dt>
                        <dd class="col-sm-9">{{ $internship->role_title }}</dd>
                        <dt class="col-sm-3 text-muted">Type</dt>
                        <dd class="col-sm-9"><span class="badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$internship->type)) }}</span></dd>
                        <dt class="col-sm-3 text-muted">Status</dt>
                        <dd class="col-sm-9"><span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst($internship->status) }}</span></dd>
                        <dt class="col-sm-3 text-muted">Start Date</dt>
                        <dd class="col-sm-9">{{ $internship->start_date?->format('d M Y') }}</dd>
                        <dt class="col-sm-3 text-muted">End Date</dt>
                        <dd class="col-sm-9">{{ $internship->end_date?->format('d M Y') ?? 'Ongoing' }}</dd>
                        @if($internship->durationDays())
                        <dt class="col-sm-3 text-muted">Duration</dt>
                        <dd class="col-sm-9">{{ $internship->durationDays() }} days</dd>
                        @endif
                        <dt class="col-sm-3 text-muted">Stipend</dt>
                        <dd class="col-sm-9">{{ $internship->stipend ? 'Rs. ' . number_format($internship->stipend, 0) . '/mo' : '-' }}</dd>
                        <dt class="col-sm-3 text-muted">Supervisor</dt>
                        <dd class="col-sm-9">{{ $internship->supervisor_name ?? '-' }} {{ $internship->supervisor_email ? '(' . $internship->supervisor_email . ')' : '' }}</dd>
                        @if($internship->description)
                        <dt class="col-sm-3 text-muted">Description</dt>
                        <dd class="col-sm-9">{{ $internship->description }}</dd>
                        @endif
                        @if($internship->feedback)
                        <dt class="col-sm-3 text-muted">Feedback</dt>
                        <dd class="col-sm-9">{{ $internship->feedback }}</dd>
                        @endif
                        @if($internship->rating)
                        <dt class="col-sm-3 text-muted">Rating</dt>
                        <dd class="col-sm-9">{{ $internship->rating }}/5</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @if($internship->status === 'ongoing')
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm border-start border-success border-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-success mb-3"><i class="bi bi-check-circle me-1"></i>Mark as Completed</h6>
                    <form method="POST" action="{{ route('cmc.internships.complete', $internship) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" required value="{{ old('end_date', now()->toDateString()) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Supervisor Feedback</label>
                            <textarea name="feedback" rows="3" class="form-control form-control-sm" placeholder="Optional">{{ old('feedback') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Rating (1-5)</label>
                            <input type="number" name="rating" class="form-control form-control-sm" min="1" max="5" value="{{ old('rating') }}" placeholder="e.g. 4">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">Mark Completed</button>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
