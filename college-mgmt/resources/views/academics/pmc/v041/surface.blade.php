@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1">{{ $title }}</h1><div class="small text-muted">{{ $description }}</div></div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small">Program</label><input class="form-control form-control-sm" name="program_id" value="{{ request('program_id') }}"></div>
                <div class="col-md-2"><label class="form-label small">Batch</label><input class="form-control form-control-sm" name="batch_id" value="{{ request('batch_id') }}"></div>
                <div class="col-md-2"><label class="form-label small">Term</label><input class="form-control form-control-sm" name="term_id" value="{{ request('term_id') }}"></div>
                <div class="col-md-2"><label class="form-label small">Subject</label><input class="form-control form-control-sm" name="subject_id" value="{{ request('subject_id') }}"></div>
                <div class="col-md-2"><label class="form-label small">Status</label><input class="form-control form-control-sm" name="status" value="{{ request('status') }}"></div>
                <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All current records' }}</div>
        </div>
    </div>

    @if(isset($batches))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.batches')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.allocation')</div>
        </div>
    @elseif(isset($allocations))
        <div class="card shadow-sm">
            <div class="card-header py-2 fw-semibold">Student Course Baskets</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Student</th><th>Subject</th><th>Type</th><th>Approval</th><th>Basket</th><th>Flags</th></tr></thead>
                <tbody>@forelse($allocations as $allocation)<tr>
                    <td>{{ $allocation->student?->user?->name ?? 'Student #' . $allocation->student_id }}</td>
                    <td>{{ $allocation->subject?->name ?? 'Subject #' . $allocation->subject_id }}</td>
                    <td>{{ $allocation->allocation_type }}</td>
                    <td>{{ $allocation->approval_status }}</td>
                    <td>{{ $allocation->basket_status }}</td>
                    <td>{{ collect($allocation->validation_flags ?? [])->keys()->implode(', ') ?: 'clear' }}</td>
                </tr>@empty<tr><td colspan="6" class="text-muted">No student course basket records.</td></tr>@endforelse</tbody>
            </table></div><div class="card-footer py-2">{{ $allocations->links() }}</div>
        </div>
    @elseif(isset($groups))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.groups')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.group')</div>
        </div>
    @elseif(isset($assignments))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.faculty')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.faculty')</div>
        </div>
    @elseif(isset($lockedSlots))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.locked-slots')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.locked-slot')</div>
        </div>
    @elseif(isset($runs))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.generator')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.generator')</div>
        </div>
    @elseif(isset($items))
        @include('academics.pmc.v041.tables.planner')
    @elseif(isset($versions))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.versions')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.change-request')</div>
        </div>
    @elseif(isset($recommendations))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.substitutions')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.substitution')</div>
        </div>
    @else
        @include('academics.pmc.v041.tables.reports')
    @endif
</div>
@endsection
