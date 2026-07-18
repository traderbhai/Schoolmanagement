@extends('layouts.admin')

@section('title', 'Assessment Normalization')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Assessment Normalization</h3>
            <div class="text-muted small">Compare raw evaluator scores with panel mean, evaluator mean, normalized score, and outlier status before committee decisions.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admission.assessment-normalization.run') }}">
                @csrf
                <button class="btn btn-primary btn-sm">Refresh</button>
            </form>
            <a class="btn btn-outline-success btn-sm" href="{{ route('admission.v037.exports', 'normalization') }}">Export CSV</a>
        </div>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Chair review workflow:</strong> refresh after evaluator scoring closes, review outliers and high variance, then use the committee board for final selected, waitlist, hold, or rejected decisions.
    </div>

    <div class="row g-2 mb-3">
        @foreach($dashboard['stats'] as $label => $value)
            <div class="col-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ ucwords(str_replace('_', ' ', $label)) }}</div>
                        <div class="fs-4 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Normalized Assessment Scores</span>
            <span class="small text-muted">{{ $dashboard['scores']->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Normalized assessment scores">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Candidate</th>
                        <th scope="col">Panel</th>
                        <th scope="col">Evaluator</th>
                        <th scope="col">Raw</th>
                        <th scope="col">Normalized</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dashboard['scores'] as $score)
                        <tr>
                            <td>{{ $score->applicant?->user?->name ?? 'Applicant name pending' }}</td>
                            <td>{{ $score->panel?->name ?? 'Panel not assigned' }}</td>
                            <td>{{ $score->evaluator?->name ?? 'Evaluator not assigned' }}</td>
                            <td>{{ $score->raw_score ?? 'Score pending' }}</td>
                            <td>{{ $score->normalized_score ?? 'Not normalized' }}</td>
                            <td>
                                <span class="badge bg-{{ $score->outlier_flag ? 'warning text-dark' : 'success' }}">{{ $score->outlier_flag ? 'Chair review' : 'Ready' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No normalized assessment scores are available yet</div>
                                <div class="small">Scores appear after panels have assigned candidates, evaluators submit aggregate scores, and normalization is refreshed.</div>
                                <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-sm btn-outline-primary mt-2">Open Assessment Control Room</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $dashboard['scores']->links() }}</div>
    </div>
</div>
@endsection
