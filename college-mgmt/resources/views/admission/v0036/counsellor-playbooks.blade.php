@extends('layouts.admin')

@section('title', 'Counsellor Playbooks')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Counsellor Playbooks</h3><div class="text-muted small">{{ $playbooks->total() }} active playbooks.</div></div>
    <a href="{{ route('admission.counsellor-desk.index') }}" class="btn btn-outline-primary btn-sm">Counsellor Desk</a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        @foreach($playbooks as $playbook)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><strong>{{ $playbook->name }}</strong><span class="badge bg-secondary ms-2">{{ str_replace('_', ' ', $playbook->playbook_type) }}</span></div>
            <div class="list-group list-group-flush">@foreach($playbook->steps as $step)<div class="list-group-item"><strong>{{ $step->title }}</strong><div class="small text-muted">{{ $step->body }}</div></div>@endforeach</div>
        </div>
        @endforeach
        {{ $playbooks->links() }}
    </div>
    <div class="col-lg-4">
        <form method="POST" action="{{ route('admission.counsellor-playbooks.store') }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-header bg-transparent fw-bold">Create Playbook</div>
            <div class="card-body">
                <input class="form-control form-control-sm mb-2" name="name" placeholder="Playbook name" required>
                <select class="form-select form-select-sm mb-2" name="playbook_type">
                    @foreach(['program_pitch','fee_scholarship','document_checklist','assessment_preparation','parent_conversation','objection_handling'] as $type)<option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>@endforeach
                </select>
                <input class="form-control form-control-sm mb-2" name="stage" placeholder="Optional stage">
                <button class="btn btn-primary btn-sm w-100">Create With Defaults</button>
            </div>
        </form>
    </div>
</div>
@endsection
