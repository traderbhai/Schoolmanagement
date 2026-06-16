@extends('layouts.teacher')

@section('title', 'Study Materials')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>Study Materials</h4>
        <a href="{{ route('teacher.materials.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-cloud-upload me-1"></i> Upload Material
        </a>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('teacher.materials.index') }}" id="filter-form">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Subject</label>
                        <select name="subject_id" class="form-select form-select-sm" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Types</option>
                            <option value="pre_read"        {{ request('type') === 'pre_read'        ? 'selected' : '' }}>Pre-Read</option>
                            <option value="post_read"       {{ request('type') === 'post_read'       ? 'selected' : '' }}>Post-Read</option>
                            <option value="notes"           {{ request('type') === 'notes'           ? 'selected' : '' }}>Notes</option>
                            <option value="reference"       {{ request('type') === 'reference'       ? 'selected' : '' }}>Reference</option>
                            <option value="slides"          {{ request('type') === 'slides'          ? 'selected' : '' }}>Slides</option>
                            <option value="video"           {{ request('type') === 'video'           ? 'selected' : '' }}>Video</option>
                            <option value="other"           {{ request('type') === 'other'           ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        @if(request()->hasAny(['subject_id', 'type']))
                            <a href="{{ route('teacher.materials.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle me-1"></i>Clear Filters
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @php
        $typeBadges = [
            'pre_read'         => 'bg-primary',
            'post_read'        => 'bg-info text-dark',
            'notes'            => 'bg-success',
            'reference'        => 'bg-secondary',
            'slides'           => 'bg-warning text-dark',
            'video'            => 'bg-danger',
            'other'            => 'bg-dark',
        ];
        $typeLabels = [
            'pre_read'         => 'Pre-Read',
            'post_read'        => 'Post-Read',
            'notes'            => 'Notes',
            'reference'        => 'Reference',
            'slides'           => 'Slides',
            'video'            => 'Video',
            'other'            => 'Other',
        ];
    @endphp

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($materials->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                    No materials found. <a href="{{ route('teacher.materials.create') }}">Upload the first one.</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Type</th>
                                <th>Published</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materials as $material)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $material->title }}</div>
                                        @if($material->description)
                                            <div class="text-muted small">{{ Str::limit($material->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $material->subject->code ?? '' }}</span><br>
                                        {{ $material->subject->name ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $typeBadges[$material->type] ?? 'bg-secondary' }}">
                                            {{ $typeLabels[$material->type] ?? ucfirst($material->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($material->is_published)
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small text-nowrap">
                                        @if($material->file_path && $material->file_size_kb)
                                            {{ number_format($material->file_size_kb, 1) }} KB
                                        @elseif($material->external_url)
                                            <span class="text-info"><i class="bi bi-link-45deg"></i> URL</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-muted small text-nowrap">
                                        {{ $material->created_at->format('d M Y') }}
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('teacher.materials.destroy', $material) }}"
                                              onsubmit="return confirm('Delete this material?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($materials->hasPages())
                    <div class="card-footer d-flex justify-content-end">
                        {{ $materials->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection
