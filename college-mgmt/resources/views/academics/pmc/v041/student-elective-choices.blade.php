@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">{{ $scopeLabel }} &middot; Rank elective preferences for the active PMC choice window.</div>
        </div>
        <div class="d-flex gap-1">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('student.pmc-course-basket') }}">Course Basket</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('student.pmc-timetable') }}">Timetable</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Window', $isOpen ? 'Open' : 'Closed'],
            ['Max Choices', $metrics['max_selections']],
            ['Submitted', $metrics['submitted']],
            ['Allocated', $metrics['allocated']],
            ['Waitlisted', $metrics['waitlisted']],
        ] as [$label, $value])
            <div class="col-6 col-lg">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="h5 mb-0">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Choice Window</div>
                <div class="card-body small">
                    @if($window)
                        <div class="d-flex justify-content-between"><span>Status</span><span class="badge text-bg-{{ $isOpen ? 'success' : 'secondary' }}">{{ $window->status }}</span></div>
                        <div class="d-flex justify-content-between"><span>Opens</span><span>{{ optional($window->opens_at)->format('d M Y H:i') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Closes</span><span>{{ optional($window->closes_at)->format('d M Y H:i') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Max selections</span><span>{{ $window->max_selections }}</span></div>
                        @if($window->instructions)<div class="mt-2 text-muted">{{ $window->instructions }}</div>@endif
                    @else
                        <div class="text-muted">No open elective window is available for your current term.</div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('student.pmc-elective-choices.store') }}" class="card shadow-sm">
                @csrf
                <div class="card-header py-2 fw-semibold">Submit Ranked Choices</div>
                <div class="card-body vstack gap-2">
                    <label class="form-label small mb-0">Term</label>
                    <select class="form-select form-select-sm" name="term_id" required>
                        @foreach($terms as $term)
                            <option value="{{ $term->id }}" @selected((string) $termId === (string) $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small mb-0">Electives In Preference Order</label>
                    <select class="form-select form-select-sm" name="subject_ids[]" multiple size="8" required @disabled(! $isOpen)>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }} ({{ $subject->credits ?? 0 }} credits)</option>
                        @endforeach
                    </select>
                    <div class="small text-muted">Select up to {{ $window?->max_selections ?? 0 }} electives. The selected order becomes your preference order.</div>
                    <button class="btn btn-sm btn-primary" @disabled(! $isOpen)>Submit Choices</button>
                </div>
            </form>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header py-2">
                    <div class="fw-semibold">My Choice Outcomes</div>
                    <div class="small text-muted">Visible filter summary: term={{ $termId ?: 'current' }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Rank</th><th>Elective</th><th>Priority</th><th>Status</th><th>Decision</th></tr></thead>
                        <tbody>
                            @forelse($choices as $choice)
                                <tr>
                                    <td>{{ $choice->preference_rank }}</td>
                                    <td><div class="fw-semibold">{{ $choice->subject?->name ?? $choice->subject?->code ?? 'Unassigned subject' }}</div><div class="small text-muted">{{ $choice->subject?->code }} &middot; {{ $choice->term?->name }}</div></td>
                                    <td>{{ $choice->priority_score }}</td>
                                    <td><span class="badge text-bg-{{ $choice->status === 'allocated' ? 'success' : ($choice->status === 'waitlisted' ? 'warning' : 'secondary') }}">{{ str($choice->status)->headline() }}</span></td>
                                    <td class="small">{{ $choice->decision_reason ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No elective choices have been submitted for this term.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-2">{{ $choices->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
