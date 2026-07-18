@extends('layouts.admin')
@section('title', 'PMC Command OS')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Command OS</h1><div class="small text-muted">Daily command desk for curriculum, faculty allocation, timetable, student success, reviews, and PMC actions.</div></div>@include('academics.pmc.v003.partials.nav')</div>
    <div class="row g-2 mb-3">
        @foreach([
            ['Open Work', $kpis['open_work'], route('academics.pmc.workbench')],
            ['Curriculum Plans', $kpis['curriculum_plans'], route('academics.pmc.curriculum-governance')],
            ['Faculty Overload', $kpis['faculty_overload'], route('academics.pmc.faculty-workload')],
            ['Timetable Conflicts', $kpis['timetable_conflicts'], route('academics.pmc.timetable-control')],
            ['Student Risk', $kpis['student_success_risk'], route('academics.pmc.student-success')],
        ] as [$label,$value,$url])
            <div class="col-6 col-xl"><a href="{{ $url }}" class="card shadow-sm text-decoration-none"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></a></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card shadow-sm h-100"><div class="card-header py-2 d-flex justify-content-between"><span class="fw-semibold">PMC Priority Queue</span><a href="{{ route('academics.pmc.workbench') }}" class="btn btn-sm btn-outline-primary">Open workbench</a></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Work</th><th scope="col">Owner</th><th scope="col">Priority</th><th scope="col">Due</th></tr></thead><tbody>@foreach($priorityItems as $item)<tr><td><div class="fw-semibold">{{ $item->title }}</div><div class="small text-muted">{{ str_replace('_',' ', $item->work_type) }}</div></td><td>{{ $item->owner?->name ?? 'Unassigned' }}</td><td>{{ $item->priority }}</td><td>{{ optional($item->due_at)->format('d M') }}</td></tr>@endforeach</tbody></table></div></div></div>
        <div class="col-xl-5"><div class="card shadow-sm h-100"><div class="card-header py-2 fw-semibold">PMC Review Meetings</div><div class="list-group list-group-flush">@foreach($reviews as $review)<a href="{{ route('academics.pmc.reviews') }}" class="list-group-item list-group-item-action py-2"><div class="fw-semibold">{{ $review->title }}</div><div class="small text-muted">{{ str_replace('_',' ', $review->review_type) }} | {{ optional($review->scheduled_for)->format('d M Y H:i') }}</div></a>@endforeach</div></div></div>
    </div>
    <div class="card shadow-sm mt-3"><div class="card-header py-2 fw-semibold">Reports</div><div class="row g-0">@foreach($reports as $report)<div class="col-md-4 col-xl-2 border-end"><a href="{{ $report['route'] }}" class="d-block p-2 text-decoration-none"><div class="small text-muted">{{ $report['label'] }}</div><div class="fw-semibold">{{ $report['count'] }}</div></a></div>@endforeach</div></div>
</div>
@endsection
