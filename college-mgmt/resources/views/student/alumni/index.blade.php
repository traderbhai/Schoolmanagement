@extends('layouts.student')
@section('title', 'Alumni Network')
@section('page-title', 'Alumni Network')

@section('content')
<div class="container-fluid py-3" style="max-width:1100px">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Alumni Network</div>
                <h5 class="fw-bold mb-1">{{ $alumniPriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $alumniPriority['body'] }}</p>
            </div>
            <div class="text-end small text-muted">
                <div><strong>{{ $sameProgramCount }}</strong> same program</div>
                <div><strong>{{ $allVerifiedCount }}</strong> verified total</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('student.alumni.index') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Graduation Year</label>
                    <select aria-label="Year" name="year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        @foreach($years as $y)
                        <option value="{{ $y }}" {{ request('year')==$y?'selected':'' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Company</label>
                    <input aria-label="Search by employer" type="text" name="company" class="form-control form-control-sm" value="{{ request('company') }}" placeholder="Search by employer">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="all_programs" value="1" id="allPrograms" {{ request('all_programs')?'checked':'' }}>
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
        <div class="card-body text-center py-5">
            <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
            <div class="fw-semibold text-dark mb-1">No verified alumni match this view yet</div>
            <div class="text-muted small mx-auto" style="max-width:640px">
                Alumni appear here only after CMC verifies graduate career details. Try clearing filters,
                enabling all programs, or checking again after more alumni profiles are verified for
                mentoring, referrals, and career-path discovery.
            </div>
        </div>
    </div>
    @else
    <div class="row g-3">
        @foreach($alumni as $profile)
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3">
                <div class="card-body">
                    <div class="rounded-circle bg-secondary-subtle d-inline-flex align-items-center justify-content-center mb-2" style="width:52px;height:52px;font-size:1.4rem;">
                        <i class="bi bi-person-fill text-secondary"></i>
                    </div>
                    <div class="fw-semibold">{{ $profile->student->user->name ?? '-' }}</div>
                    <div class="text-muted small">{{ $profile->student->program->name ?? '' }} - {{ $profile->graduation_year }}</div>

                    @if($profile->current_role || $profile->current_employer)
                    <div class="mt-2 small">
                        @if($profile->current_role)<div class="fw-semibold text-primary">{{ $profile->current_role }}</div>@endif
                        @if($profile->current_employer)<div class="text-muted">{{ $profile->current_employer }}</div>@endif
                    </div>
                    @endif

                    @if($profile->city || $profile->country)
                    <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i>{{ implode(', ', array_filter([$profile->city, $profile->country])) }}</div>
                    @endif

                    @if($profile->feedback)
                    <div class="text-muted small mt-2">{{ \Illuminate\Support\Str::limit($profile->feedback, 90) }}</div>
                    @endif

                    @if($profile->linkedin_url)
                    <div class="mt-2">
                        <a href="{{ $profile->linkedin_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-2">
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
