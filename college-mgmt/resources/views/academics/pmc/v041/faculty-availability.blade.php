@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">{{ $scopeLabel }}</div>
        </div>
        @if(request()->routeIs('academics.pmc.*'))
            @include('academics.pmc.v041.partials.nav')
        @endif
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Availability Requests</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Faculty</th><th scope="col">Days</th><th scope="col">Preferred Slots</th><th scope="col">Unavailable</th><th scope="col">Status</th><th scope="col">Decision</th></tr></thead>
                        <tbody>
                            @forelse($requests as $availability)
                                <tr>
                                    <td><div class="fw-semibold">{{ $availability->teacher?->user?->name }}</div><div class="small text-muted">{{ $availability->reason }}</div></td>
                                    <td>{{ collect($availability->available_days ?? [])->join(', ') ?: '-' }}</td>
                                    <td>{{ collect($availability->preferred_slots ?? [])->join(', ') ?: '-' }}</td>
                                    <td>{{ collect($availability->unavailable_slots ?? [])->map(fn($slot) => ($slot['day'] ?? '?') . ':' . ($slot['slot_id'] ?? '?'))->join(', ') ?: '-' }}</td>
                                    <td>{{ $availability->status }}</td>
                                    <td>
                                        @if(request()->routeIs('academics.pmc.*') && $availability->status === 'submitted')
                                            <form method="POST" action="{{ route('academics.pmc.faculty-availability-requests.decide', $availability) }}" class="d-flex gap-1">@csrf @method('PATCH')
                                                <input type="hidden" name="status" value="approved">
                                                <input aria-label="Decision note" class="form-control form-control-sm" name="decision_note" placeholder="Decision note">
                                                <button class="btn btn-sm btn-outline-primary">Approve availability</button>
                                            </form>
                                        @else
                                            {{ $availability->decision_note ?: '-' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No availability requests.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-2">{{ $requests->links() }}</div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-2 fw-semibold">Applied Faculty Preferences</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th scope="col">Faculty</th><th scope="col">Type</th><th scope="col">Available Days</th><th scope="col">Max/Day</th><th scope="col">Max/Week</th></tr></thead>
                        <tbody>
                            @forelse($preferences as $preference)
                                <tr><td>{{ $preference->teacher?->user?->name }}</td><td>{{ $preference->faculty_type }}</td><td>{{ collect($preference->available_days ?? [])->join(', ') }}</td><td>{{ $preference->max_classes_per_day }}</td><td>{{ $preference->max_weekly_load }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No applied preferences.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($preferences, 'links'))<div class="card-footer py-2">{{ $preferences->links() }}</div>@endif
            </div>
        </div>

        <div class="col-xl-4">
            <form method="POST" action="{{ request()->routeIs('academics.pmc.*') ? route('academics.pmc.faculty-availability-requests.store') : route('teacher.pmc-availability.store') }}" class="card shadow-sm">@csrf
                <div class="card-header py-2 fw-semibold">Submit Availability</div>
                <div class="card-body vstack gap-2">
                    @if(request()->routeIs('academics.pmc.*'))
                        <select aria-label="Teacher" class="form-select form-select-sm" name="teacher_id"><option value="">Select faculty</option>@foreach($selectorOptions['teachers'] ?? [] as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->user?->name ?? $teacher->employee_id ?? 'Unassigned faculty' }}{{ $teacher->employee_id ? ' - ' . $teacher->employee_id : '' }}</option>@endforeach</select>
                    @endif
                    <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Any term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
                    <input aria-label="Available days" class="form-control form-control-sm" name="available_days" placeholder="Available days e.g. 1,2,4">
                    <select aria-label="Preferred slots" class="form-select form-select-sm" name="preferred_slots[]" multiple>@foreach($selectorOptions['slots'] ?? [] as $slot)<option value="{{ $slot->id }}">{{ $slot->name }} {{ $slot->start_time }}-{{ $slot->end_time }}</option>@endforeach</select>
                    <input aria-label="Unavailable slots" class="form-control form-control-sm" name="unavailable_slots" placeholder="Unavailable pairs e.g. 1:2,3:1">
                    <div class="d-flex gap-2"><input aria-label="Max classes per day" class="form-control form-control-sm" name="max_classes_per_day" placeholder="Max/day"><input aria-label="Max weekly load" class="form-control form-control-sm" name="max_weekly_load" placeholder="Max/week"></div>
                    <input aria-label="Max consecutive classes" class="form-control form-control-sm" name="max_consecutive_classes" placeholder="Max consecutive">
                    <textarea aria-label="Reason or notes" class="form-control form-control-sm" name="reason" placeholder="Reason / notes"></textarea>
                    <button class="btn btn-sm btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
