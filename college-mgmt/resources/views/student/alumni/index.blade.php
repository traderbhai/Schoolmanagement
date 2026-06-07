@extends('layouts.student')
@section('title', 'Alumni Network')
@section('page-title', 'Alumni Network')

@section('content')
<div class="container-fluid py-3" style="max-width:1100px">

    {{-- Filters --}}
    <form method="GET" class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Graduation Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        @foreach($years as $y)
                        <option value="{{ $y }}" {{ request('year')==$y?'selected':'' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Company</label>
                    <input type="text" name="company" class="form-control form-control-sm"
                           value="{{ request('company') }}" placeholder="Search by employer...">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="all_programs" value="1"
                               id="allPrograms" {{ request('all_programs')?'checked':'' }}>
                        <label class="form-check-label small" for="allPrograms">Show all programs</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('student.alumni.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </div>
        </div>
    </form>

    @if($alumni->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-people fs-1 d-block mb-2"></i>No alumni found matching your filters.
        </div>
    </div>
    @else
    <div class="row g-3">
        @foreach($alumni as $profile)
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3">
                <div class="card-body">
                    <div class="rounded-circle bg-secondary-subtle d-inline-flex align-items-center justify-content-center mb-2"
                         style="width:52px;height:52px;font-size:1.4rem;">
                        <i class="bi bi-person-fill text-secondary"></i>
                    </div>
                    <div class="fw-semibold">{{ $profile->student->user->name ?? '—' }}</div>
                    <div class="text-muted small">{{ $profile->student->program->name ?? '' }} · {{ $profile->graduation_year }}</div>

                    @if($profile->current_role || $profile->current_employer)
                    <div class="mt-2 small">
                        @if($profile->current_role)<div class="fw-semibold text-primary">{{ $profile->current_role }}</div>@endif
                        @if($profile->current_employer)<div class="text-muted">{{ $profile->current_employer }}</div>@endif
                    </div>
                    @endif

                    @if($profile->city || $profile->country)
                    <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i>{{ implode(', ', array_filter([$profile->city, $profile->country])) }}</div>
                    @endif

                    @if($profile->linkedin_url)
                    <div class="mt-2">
                        <a href="{{ $profile->linkedin_url }}" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-primary py-0 px-2">
                            <i class="bi bi-linkedin me-1"></i>LinkedIn
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $alumni->links() }}</div>
    @endif
</div>
@endsection
