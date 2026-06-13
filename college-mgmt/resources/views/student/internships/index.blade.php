@extends('layouts.student')
@section('title', 'My Internships')
@section('page-title', 'My Internships')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Internship Priority</div>
            <h5 class="fw-bold mb-1">{{ $internshipPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $internshipPriority['body'] }}</p>
        </div>
    </div>

    @if($internships->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-building fs-1 d-block mb-2"></i>
            No internship records found. Internships are assigned by the placement cell.
        </div>
    </div>
    @else
    <div class="row g-3">
        @foreach($internships as $internship)
        @php
            $badge = [
                'ongoing' => 'info',
                'completed' => 'success',
                'dropped' => 'secondary',
            ][$internship->status ?? 'ongoing'] ?? 'secondary';
        @endphp
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                        <h6 class="fw-semibold mb-0">{{ $internship->role_title }}</h6>
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($internship->status ?? 'ongoing') }}</span>
                    </div>
                    <div class="fw-semibold text-primary mb-2">
                        {{ $internship->company->name ?? $internship->company_name }}
                    </div>

                    <div class="row g-1 text-muted small mb-2">
                        <div class="col-12">
                            <i class="bi bi-calendar3 me-1"></i>
                            {{ $internship->start_date->format('d M Y') }}
                            @if($internship->end_date) to {{ $internship->end_date->format('d M Y') }} @endif
                        </div>
                        @if($internship->durationDays())
                        <div class="col-6">
                            <i class="bi bi-clock me-1"></i>{{ $internship->durationDays() }} days
                        </div>
                        @endif
                        @if($internship->type)
                        <div class="col-6">
                            <i class="bi bi-tag me-1"></i>{{ ucwords(str_replace('_', ' ', $internship->type)) }}
                        </div>
                        @endif
                        @if($internship->stipend)
                        <div class="col-6">
                            <i class="bi bi-cash me-1"></i>Rs. {{ number_format($internship->stipend, 0) }}/mo
                        </div>
                        @endif
                    </div>

                    @if($internship->description)
                    <p class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($internship->description, 120) }}</p>
                    @endif

                    @if($internship->supervisor_name)
                    <div class="small text-muted">
                        <i class="bi bi-person me-1"></i>Supervisor: {{ $internship->supervisor_name }}
                        @if($internship->supervisor_email)
                            - <a href="mailto:{{ $internship->supervisor_email }}">{{ $internship->supervisor_email }}</a>
                        @endif
                    </div>
                    @endif

                    @if($internship->feedback)
                    <div class="alert alert-light mt-2 mb-0 py-2 px-3 small">
                        <strong>Feedback:</strong> {{ $internship->feedback }}
                        @if($internship->rating)
                            <span class="ms-2">Rating: {{ $internship->rating }}/5</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
