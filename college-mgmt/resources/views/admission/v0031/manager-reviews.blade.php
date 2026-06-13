@extends('layouts.admin')

@section('title', 'Manager Reviews')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<h3 class="fw-bold mb-3">Manager Review Queue</h3>
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
</div>
@endsection
