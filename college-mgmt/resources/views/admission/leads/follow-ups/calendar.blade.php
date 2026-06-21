@extends('layouts.admin')

@section('title', 'Follow-up Calendar - ' . $startOfMonth->format('F Y'))
@section('page-title', 'Follow-up Calendar')

@section('content')
<div class="container-fluid py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admission.leads.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> All Leads</a>
            <h2 class="fw-bold mb-0 mt-1">Follow-up Calendar - {{ $startOfMonth->format('F Y') }}</h2>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="alert alert-info border-0 shadow-sm small mb-4">
        <div class="fw-semibold mb-1">Follow-up review workflow</div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge text-bg-light border">1. Choose month and counsellor</span>
            <span class="badge text-bg-light border">2. Review pending callbacks by date</span>
            <span class="badge text-bg-light border">3. Open the lead before changing outcome</span>
            <span class="badge text-bg-light border">4. Mark done only after contact is recorded</span>
            <span class="badge text-bg-light border">5. Schedule the next action if still open</span>
        </div>
        <div class="text-muted">This calendar shows only follow-ups visible to your Admission role and hierarchy. Completing an item updates the daily work queues and lead timeline.</div>
    </div>

    {{-- Filters Row --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admission.leads.follow-ups.calendar') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">Month</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">Counsellor</label>
                    <select name="counsellor_id" class="form-select form-select-sm" style="min-width:180px">
                        <option value="">All Counsellors</option>
                        @foreach($counsellors as $c)
                            <option value="{{ $c->id }}" {{ $counsellorId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Month Navigation --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        @php
            $prevMonth = $startOfMonth->copy()->subMonth()->format('Y-m');
            $nextMonth = $startOfMonth->copy()->addMonth()->format('Y-m');
            $prevQuery = http_build_query(array_merge(request()->except('month'), ['month' => $prevMonth]));
            $nextQuery = http_build_query(array_merge(request()->except('month'), ['month' => $nextMonth]));
        @endphp
        <a href="{{ route('admission.leads.follow-ups.calendar') }}?{{ $prevQuery }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> {{ $startOfMonth->copy()->subMonth()->format('M Y') }}
        </a>
        <h5 class="mb-0 fw-semibold">{{ $startOfMonth->format('F Y') }}</h5>
        <a href="{{ route('admission.leads.follow-ups.calendar') }}?{{ $nextQuery }}" class="btn btn-outline-secondary btn-sm">
            {{ $startOfMonth->copy()->addMonth()->format('M Y') }} <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    {{-- Calendar Grid --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            {{-- Day-of-week header --}}
            <div class="row g-0 border-bottom">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                    <div class="col border-end text-center py-2 text-muted small fw-semibold {{ in_array($day, ['Sun','Sat']) ? 'bg-light' : '' }}">
                        {{ $day }}
                    </div>
                @endforeach
            </div>

            @php
                // Start from Sunday of the week containing the 1st of the month
                $cursor = $startOfMonth->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
                $today  = now()->format('Y-m-d');
                $totalCells = 42; // 6 weeks x 7 days
            @endphp

            @for($row = 0; $row < 6; $row++)
                <div class="row g-0 {{ $row < 5 ? 'border-bottom' : '' }}" style="min-height:100px">
                    @for($col = 0; $col < 7; $col++)
                        @php
                            $cellDate    = $cursor->format('Y-m-d');
                            $isToday     = $cellDate === $today;
                            $isThisMonth = $cursor->month === $startOfMonth->month;
                            $isWeekend   = $col === 0 || $col === 6;
                            $dayFollowUps = $byDate->get($cellDate, collect());
                        @endphp
                        <div class="col border-end p-1 {{ $col === 6 ? 'border-end-0' : '' }} {{ $isWeekend ? 'bg-light' : '' }} {{ $isToday ? 'bg-primary bg-opacity-10 border-primary' : '' }}"
                             style="min-height:100px; vertical-align:top">
                            <div class="text-end mb-1">
                                <span class="small {{ $isThisMonth ? ($isToday ? 'fw-bold text-primary' : 'text-dark') : 'text-muted' }}
                                    {{ $isToday ? 'bg-primary text-white rounded-circle px-1' : '' }}">
                                    {{ $cursor->day }}
                                </span>
                            </div>
                            @foreach($dayFollowUps as $fu)
                                <div class="mb-1" style="font-size:0.7rem; overflow:hidden">
                                    <span class="{{ $fu->type_badge }}" style="font-size:0.65rem">{{ $fu->type_label }}</span>
                                    <span class="{{ $fu->isCompleted() ? 'text-decoration-line-through text-muted' : '' }}">
                                        {{ \Illuminate\Support\Str::limit($fu->lead->name ?? 'Lead not linked', 12) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        @php $cursor->addDay(); @endphp
                    @endfor
                </div>
            @endfor
        </div>
    </div>

    {{-- Upcoming Follow-ups List --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">
            All Follow-ups - {{ $startOfMonth->format('F Y') }}
            <span class="badge bg-secondary ms-2">{{ $followUps->count() }}</span>
        </div>
        <div class="card-body p-0">
            @if($followUps->isEmpty())
                <div class="p-4 text-center text-muted">
                    <div class="fw-semibold text-body mb-1">No follow-ups scheduled for this month.</div>
                    <div class="small mb-3">Create callbacks from a lead detail page, Calling Desk, or reminder workflow. If you filtered by counsellor, clear the filter to confirm whether another team member owns the follow-up.</div>
                    <div class="d-flex justify-content-center flex-wrap gap-2">
                        <a href="{{ route('admission.leads.index') }}" class="btn btn-sm btn-outline-primary">Open Leads</a>
                        <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-sm btn-outline-primary">Calling Desk</a>
                        <a href="{{ route('admission.reminders.index') }}" class="btn btn-sm btn-outline-secondary">Reminder Queue</a>
                    </div>
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date / Time</th>
                            <th>Lead</th>
                            <th>Type</th>
                            <th>Counsellor</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($followUps as $fu)
                        <tr class="{{ $fu->isCompleted() ? 'text-muted' : '' }}">
                            <td class="{{ $fu->isCompleted() ? 'text-decoration-line-through' : '' }}">
                                {{ $fu->scheduled_at->format('d M Y H:i') }}
                            </td>
                            <td>
                                @if($fu->lead)
                                    <a href="{{ route('admission.leads.show', $fu->lead) }}">{{ $fu->lead->name }}</a>
                                    @if($fu->lead->program)
                                        <br><small class="text-muted">{{ $fu->lead->program->name }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">Lead not linked</span>
                                @endif
                            </td>
                            <td><span class="{{ $fu->type_badge }}">{{ $fu->type_label }}</span></td>
                            <td>{{ $fu->counsellor?->name ?? 'Counsellor not assigned' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($fu->notes ?: 'No notes recorded', 50) }}</td>
                            <td>
                                @if($fu->isCompleted())
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if(!$fu->isCompleted())
                                <form action="{{ route('admission.leads.follow-ups.complete', $fu) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-success">Mark Done</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
