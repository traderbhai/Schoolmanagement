@extends('layouts.admin')

@section('title', 'Parent Journeys')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Parent / Guardian Journeys</h3>
            <div class="text-muted small">Track decision-maker calls, guardian reminders, preferred channel, and next action ownership.</div>
        </div>
        <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-outline-primary btn-sm">Open Calling Desk</a>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Operating sequence:</strong> confirm the decision maker, record the preferred channel, schedule the next parent action, then use reminders so parent follow-up does not depend on memory.
    </div>

    <div class="row g-2 mb-3">
        @foreach($dashboard['stats'] as $label => $value)
            <div class="col-6">
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
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <span class="fw-bold">Parent / Guardian Follow-up Queue</span>
            <span class="small text-muted">{{ $dashboard['journeys']->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Parent guardian journeys">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Guardian</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Decision</th>
                        <th scope="col">Next Action</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dashboard['journeys'] as $journey)
                        <tr>
                            <td>
                                <strong>{{ $journey->guardian_name ?: 'Guardian name pending' }}</strong>
                                <div class="small text-muted">{{ $journey->guardian_phone ?: 'Phone not captured' }}</div>
                            </td>
                            <td>{{ class_basename($journey->subject_type) }} #{{ $journey->subject_id }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $journey->decision_status ?: 'contact_pending')) }}</td>
                            <td>{{ $journey->next_action ?: 'Next parent action not set' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admission.parent-journeys.reminder', $journey) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Create Reminder</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No parent or guardian journeys are active</div>
                                <div class="small">Journeys appear after a lead or applicant has guardian details and staff create a parent follow-up from the calling desk, counsellor desk, or applicant timeline.</div>
                                <a href="{{ route('admission.counsellor-desk.index') }}" class="btn btn-sm btn-outline-primary mt-2">Open Counsellor Desk</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $dashboard['journeys']->links() }}</div>
    </div>
</div>
@endsection
