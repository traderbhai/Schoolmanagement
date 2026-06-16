@extends('layouts.admin')
@section('title', 'OBE Surveys (Indirect Assessment)')
@section('page-title', 'OBE Surveys')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('academic.obe.co.index') }}">OBE Framework</a></li>
    <li class="breadcrumb-item active">Surveys</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">All OBE Surveys</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Term</th>
                            <th>Closes</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($surveys as $s)
                        <tr>
                            <td>{{ $s->title }}</td>
                            <td><small>{{ $s->subject->name ?? '—' }}</small></td>
                            <td><small>{{ $s->term->name ?? '—' }}</small></td>
                            <td><small>{{ $s->closes_at ?? '—' }}</small></td>
                            <td>
                                @if($s->is_published)
                                    <span class="badge bg-success">Published</span>
                                @else
                                    <span class="badge bg-secondary">Draft</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('academic.obe.surveys.toggle', $s) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-{{ $s->is_published ? 'warning' : 'success' }}">
                                        {{ $s->is_published ? 'Unpublish' : 'Publish' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No surveys yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($surveys->hasPages())
            <div class="card-footer bg-white">{{ $surveys->links() }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Create Survey</div>
            <div class="card-body">
                <form method="POST" action="{{ route('academic.obe.surveys.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small">Subject</label>
                        <select name="subject_id" class="form-select form-select-sm" required>
                            <option value="">— Select —</option>
                            @foreach(\App\Models\Subject::where('is_active',true)->orderBy('name')->get() as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Term</label>
                        <select name="term_id" class="form-select form-select-sm" required>
                            <option value="">— Select —</option>
                            @foreach(\App\Models\Term::orderBy('name')->get() as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Title</label>
                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="Exit Survey — Semester VI">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Closes On</label>
                        <input type="date" name="closes_at" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Create Survey
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
