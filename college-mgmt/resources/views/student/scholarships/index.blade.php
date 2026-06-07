@extends('layouts.student')
@section('title', 'Scholarships')
@section('page-title', 'Scholarships')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">

    @if($myApplications->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>My Applications</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Scholarship</th><th>CGPA</th><th>Status</th><th>Applied</th></tr>
                </thead>
                <tbody>
                    @foreach($myApplications as $app)
                    @php $badge = match($app->status) { 'approved'=>'success','disbursed'=>'success','rejected'=>'danger','shortlisted'=>'info',default=>'warning' }; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $app->scheme->name ?? '—' }}</td>
                        <td>{{ $app->cgpa_at_application }}</td>
                        <td><span class="badge bg-{{ $badge }}">{{ ucfirst($app->status) }}</span></td>
                        <td class="text-muted small">{{ $app->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-award me-2"></i>Available Scholarships</div>
        @if($schemes->isEmpty())
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-award fs-1 d-block mb-2"></i>No active scholarships at this time.
        </div>
        @else
        <div class="list-group list-group-flush">
            @foreach($schemes as $scheme)
            @php $applied = isset($myApplications[$scheme->id]); @endphp
            <div class="list-group-item px-4 py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-semibold mb-1">{{ $scheme->name }}</h6>
                        <div class="text-muted small mb-1">
                            <span class="badge bg-secondary me-1">{{ ucfirst($scheme->type ?? 'general') }}</span>
                            @if($scheme->amount) <span>₹{{ number_format($scheme->amount) }}</span> @endif
                            @if($scheme->application_deadline)
                                · Deadline: {{ \Carbon\Carbon::parse($scheme->application_deadline)->format('d M Y') }}
                            @endif
                        </div>
                        @if($scheme->description)
                        <p class="small text-muted mb-1">{{ Str::limit($scheme->description, 140) }}</p>
                        @endif
                        @if($scheme->eligibility_criteria)
                        <p class="small mb-0"><strong>Eligibility:</strong> {{ $scheme->eligibility_criteria }}</p>
                        @endif
                    </div>
                    <div class="ms-3 flex-shrink-0">
                        @if($applied)
                            @php $badge = match($myApplications[$scheme->id]->status) { 'approved'=>'success','disbursed'=>'success','rejected'=>'danger','shortlisted'=>'info',default=>'warning' }; @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($myApplications[$scheme->id]->status) }}</span>
                        @else
                            <form method="POST" action="{{ route('student.scholarships.apply', $scheme) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">Apply</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
