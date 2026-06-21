@extends('layouts.student')
@section('title', $subject->name . ' — Study Materials')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.courses.index') }}">Courses</a></li>
            <li class="breadcrumb-item"><a href="{{ route('student.courses.show', $subject) }}">{{ $subject->name }}</a></li>
            <li class="breadcrumb-item active">Study Materials</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Study Materials — {{ $subject->name }}</h4>

    @php
        $typeLabels = [
            'pre_read'  => ['label' => 'Pre-Read',   'icon' => 'bi-book',         'color' => 'primary'],
            'post_read' => ['label' => 'Post-Read',  'icon' => 'bi-book-fill',    'color' => 'success'],
            'notes'     => ['label' => 'Notes',      'icon' => 'bi-journal-text', 'color' => 'warning'],
            'slides'    => ['label' => 'Slides',     'icon' => 'bi-easel2',       'color' => 'info'],
            'reference' => ['label' => 'Reference',  'icon' => 'bi-bookmark-star','color' => 'secondary'],
            'video'     => ['label' => 'Video',      'icon' => 'bi-play-circle',  'color' => 'danger'],
            'other'     => ['label' => 'Other',      'icon' => 'bi-file-earmark', 'color' => 'dark'],
        ];
    @endphp

    @if($materials->isEmpty())
        <div class="alert alert-info">
            <div class="fw-semibold mb-1">No published study materials are available yet</div>
            <div class="small">
                Notes, slides, readings, videos, and reference links will appear here after your faculty publishes them for this enrolled course.
            </div>
        </div>
    @else
        @foreach($materials as $type => $items)
        @php $meta = $typeLabels[$type] ?? ['label' => ucfirst($type), 'icon' => 'bi-file', 'color' => 'secondary']; @endphp
        <div class="mb-4">
            <h6 class="text-{{ $meta['color'] }} fw-semibold mb-3">
                <i class="bi {{ $meta['icon'] }} me-2"></i>{{ $meta['label'] }}
                <span class="badge bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} ms-2">{{ $items->count() }}</span>
            </h6>
            <div class="row g-2">
                @foreach($items as $material)
                <div class="col-md-6">
                    <div class="card border shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="fw-semibold">{{ $material->title }}</div>
                            @if($material->description)
                            <div class="text-muted small">{{ Str::limit($material->description, 80) }}</div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-muted" style="font-size:.75rem">{{ $material->uploader->name ?? '' }} · {{ $material->created_at->format('d M Y') }}</span>
                                @if($material->external_url)
                                <a href="{{ $material->external_url }}" target="_blank" class="btn btn-sm btn-outline-{{ $meta['color'] }}">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                </a>
                                @elseif($material->file_path)
                                <span class="text-muted small">{{ $material->file_size_kb ? round($material->file_size_kb/1024,1).'MB' : 'File' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    @endif
</div>
@endsection
