@extends('layouts.admin')
@section('title', 'Program Chair — Curriculum')

@section('content')
<h4 class="mb-4"><i class="bi bi-book me-2 text-primary"></i>Curriculum</h4>

@foreach($subjects as $programId => $termGroups)
    @php $prog = $programs[$programId] ?? null; @endphp
    <h5 class="mt-3 mb-2 text-primary">{{ $prog?->name ?? 'Program '.$programId }}</h5>

    @foreach($termGroups as $termNum => $subs)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light fw-semibold">Term {{ $termNum }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Code</th><th>Subject</th><th>Type</th><th>Credits</th><th>Hrs/Week</th></tr>
                    </thead>
                    <tbody>
                    @foreach($subs as $sub)
                        <tr>
                            <td><code>{{ $sub->code }}</code></td>
                            <td>{{ $sub->name }}</td>
                            <td><span class="badge bg-info">{{ ucfirst($sub->type) }}</span></td>
                            <td>{{ $sub->credits }}</td>
                            <td>{{ $sub->hours_per_week }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach
@endforeach

@if($subjects->isEmpty())
    <p class="text-muted">No subjects found for assigned programs.</p>
@endif
@endsection
