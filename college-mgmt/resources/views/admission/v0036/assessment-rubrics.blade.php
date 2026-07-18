@extends('layouts.admin')

@section('title', 'Assessment Rubrics')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Assessment Rubrics</h3><div class="text-muted small">{{ $rubrics->total() }} rubric templates after filters.</div></div>
    <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-outline-primary btn-sm">Control Room</a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Criteria</th><th scope="col">Minimum</th></tr></thead>
            <tbody>@foreach($rubrics as $rubric)<tr><td class="fw-semibold">{{ $rubric->name }}</td><td>{{ ucwords(str_replace('_', ' ', $rubric->assessment_type)) }}</td><td>{{ $rubric->criteria->count() }}</td><td>{{ $rubric->minimum_score }}%</td></tr>@endforeach</tbody>
        </table></div></div>
        <div class="mt-3">{{ $rubrics->links() }}</div>
    </div>
    <div class="col-lg-4">
        <form class="card border-0 shadow-sm" method="POST" action="{{ route('admission.assessment-rubrics.store') }}">
            @csrf
            <div class="card-header bg-transparent fw-bold">Create Rubric</div>
            <div class="card-body">
                <input aria-label="Rubric name" class="form-control form-control-sm mb-2" name="name" placeholder="Rubric name" required>
                <select aria-label="Assessment Type" class="form-select form-select-sm mb-2" name="assessment_type" required>
                    @foreach(['group_discussion','personal_interview','case_analysis','written_ability_test','aptitude_test','presentation','portfolio_review','screening_call'] as $type)
                    <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
                <input aria-label="Minimum Score" class="form-control form-control-sm mb-2" name="minimum_score" type="number" min="0" max="100" value="50">
                <button class="btn btn-primary btn-sm w-100">Create With Defaults</button>
            </div>
        </form>
    </div>
</div>
@endsection
