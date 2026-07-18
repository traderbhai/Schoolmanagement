@extends('layouts.admin')
@section('title', $config['title'])
@section('content')
<div class="container-fluid py-3">
    @php
        $filters = $data['filters'] ?? [];
        $metricLinks = [
            'Open' => request()->fullUrlWithQuery(['status' => 'open', 'page' => null]),
            'Critical' => request()->fullUrlWithQuery(['severity' => 'critical', 'page' => null]),
            'Overdue' => request()->fullUrlWithQuery(['status' => 'open', 'sort' => 'due_at', 'direction' => 'asc', 'page' => null]),
            'Avg Score' => request()->fullUrlWithQuery(['sort' => 'score', 'direction' => 'desc', 'page' => null]),
        ];
        $activeSummary = collect([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'severity' => $filters['severity'] ?? null,
            'program' => $filters['program_id'] ?? null,
            'owner' => $filters['owner_user_id'] ?? null,
        ])->filter()->map(fn ($value, $key) => ucfirst($key).': '.$value)->join(' | ');
    @endphp
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">{{ $config['title'] }}</h1><div class="small text-muted">{{ $config['description'] }}</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Operating record workflow</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Filter by status or severity</span>
            <span class="badge text-bg-light border">2. Sort by due date or score</span>
            <span class="badge text-bg-light border">3. Open source owner context</span>
            <span class="badge text-bg-light border">4. Save useful view</span>
            <span class="badge text-bg-light border">5. Export current scope</span>
        </div>
        <div class="text-muted mt-2">Use these lists to manage faculty workload, student success, curriculum, exam readiness, quality, induction, and policy records without losing filter context.</div>
    </div>
    <div class="row g-2 mb-3">
        @foreach(['Open'=>$data['open'],'Critical'=>$data['critical'],'Overdue'=>$data['overdue'],'Avg Score'=>$data['avg_score']] as $label=>$value)
            <div class="col-6 col-lg-3"><a class="card shadow-sm text-decoration-none text-reset h-100" href="{{ $metricLinks[$label] }}"><div class="card-body py-2"><div class="d-flex justify-content-between"><div class="small text-muted">{{ $label }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div><div class="h4 mb-0">{{ $value }}</div></div></a></div>
        @endforeach
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small mb-1">Search</label><input aria-label="Record, source, key" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Record, source, key"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Status</label><select aria-label="Status" name="status" class="form-select form-select-sm"><option value="">All</option>@foreach(['open','in_progress','blocked','resolved','closed','done','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small mb-1">Severity</label><select aria-label="Severity" name="severity" class="form-select form-select-sm"><option value="">All</option>@foreach(['critical','high','medium','low'] as $severity)<option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small mb-1">Sort</label><select aria-label="Sort" name="sort" class="form-select form-select-sm">@foreach(['due_at'=>'Due date','score'=>'Score','severity'=>'Severity','status'=>'Status','title'=>'Title','created_at'=>'Created'] as $key => $label)<option value="{{ $key }}" @selected(($filters['sort'] ?? 'due_at') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-1"><label class="form-label small mb-1">Dir</label><select aria-label="Direction" name="direction" class="form-select form-select-sm"><option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Asc</option><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Desc</option></select></div>
                <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary flex-fill">Apply filters</button><a href="{{ route(request()->route()->getName()) }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
            </form>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-lg-7">
                    <div class="small text-muted mb-1">Saved views</div>
                    <div class="d-flex flex-wrap gap-1">
                        @forelse($savedViews as $view)
                            <a class="btn btn-sm {{ $view->is_default ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route(request()->route()->getName(), $view->filters ?? []) }}">{{ $view->name }}</a>
                        @empty
                            <span class="small text-muted">No saved views for this Dean surface yet.</span>
                        @endforelse
                    </div>
                </div>
                <div class="col-lg-5">
                    <form method="POST" action="{{ route('academics.dean-os.saved-views.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="surface" value="{{ $surface }}">
                        @foreach($filters as $key => $value)
                            @if($value !== null && $value !== '')
                                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="col-7"><label class="form-label small mb-1">Save current filters</label><input aria-label="View name" name="name" class="form-control form-control-sm" placeholder="View name" required></div>
                        <div class="col-3"><label class="form-label small mb-1">Default</label><select aria-label="Is Default" name="is_default" class="form-select form-select-sm"><option value="0">No</option><option value="1">Yes</option></select></div>
                        <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100" onclick="return confirm('Save this Dean filtered view? Confirm current filters, default-view setting, report surface, and future navigation impact before saving the view.')">Save view</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm"><div class="card-header py-2 d-flex flex-wrap gap-2 justify-content-between"><span class="small text-muted">Visible filter summary: {{ str_replace('_',' ', $config['record_type']) }}{{ $activeSummary ? ' | '.$activeSummary : ' | all records' }}</span><a href="{{ route('academics.dean-os.export', ['report' => $config['record_type']] + request()->query()) }}" class="btn btn-sm btn-outline-secondary">Export Current View</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col"><a class="text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort'=>'title','direction'=>($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Record</a></th><th scope="col">Program</th><th scope="col">Owner</th><th scope="col"><a class="text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort'=>'severity','direction'=>($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Severity</a></th><th scope="col"><a class="text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort'=>'status','direction'=>($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Status</a></th><th scope="col"><a class="text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort'=>'due_at','direction'=>($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Due</a></th><th scope="col"><a class="text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort'=>'score','direction'=>($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Score</a></th></tr></thead><tbody>
        @foreach($data['records'] as $record)<tr><td><div class="fw-semibold">{{ $record->title }}</div><div class="small text-muted">{{ $record->source_type }} {{ $record->source_key }}</div></td><td>{{ $record->program?->code ?? '-' }}</td><td>{{ $record->owner?->name ?? 'Unassigned' }}</td><td>{{ $record->severity }}</td><td>{{ $record->status }}</td><td>{{ optional($record->due_at)->format('d M Y') }}</td><td>{{ $record->score }}</td></tr>@endforeach
        @if($data['records']->isEmpty())<tr><td colspan="7" class="text-center text-muted py-4">No records match the current filters.</td></tr>@endif
    </tbody></table></div><div class="card-footer py-2">{{ $data['records']->links() }}</div></div>
</div>
@endsection
