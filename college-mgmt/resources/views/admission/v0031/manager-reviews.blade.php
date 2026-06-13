@extends('layouts.admin')

@section('title', 'Manager Reviews')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Manager Review Queue</h3><div class="text-muted small">{{ $reviews->total() }} reviews after filters.</div></div>
</div>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm"><option value="">All Status</option>@foreach(['pending','resolved','dismissed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label small mb-1">Review Type</label><input name="review_type" value="{{ request('review_type') }}" class="form-control form-control-sm" placeholder="duplicate_review"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Rows</label><select name="per_page" class="form-select form-select-sm">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill">Apply</button><a href="{{ route('admission.manager-reviews.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Type</th><th>Finding</th><th>Action Required</th><th>Due</th><th>Status</th><th>Resolve</th></tr></thead>
            <tbody>
            @foreach($reviews as $review)
                <tr>
                    <td><span class="badge bg-warning text-dark">{{ str_replace('_', ' ', $review->review_type) }}</span></td>
                    <td>{{ $review->finding }}</td>
                    <td>{{ $review->action_required }}</td>
                    <td>{{ optional($review->due_at)->format('d M Y') }}</td>
                    <td>{{ ucfirst($review->status) }}</td>
                    <td>
                        @if($review->status !== 'resolved')
                        <form method="POST" action="{{ route('admission.manager-reviews.resolve', $review) }}" class="d-flex gap-1">
                            @csrf @method('PATCH')
                            <input class="form-control form-control-sm" name="resolution_notes" placeholder="Resolution" required>
                            <button class="btn btn-sm btn-outline-success">Save</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
        <div class="small text-muted">Showing {{ $reviews->firstItem() ?? 0 }}-{{ $reviews->lastItem() ?? 0 }} of {{ $reviews->total() }}</div>
        {{ $reviews->links() }}
    </div>
</div>
@endsection
