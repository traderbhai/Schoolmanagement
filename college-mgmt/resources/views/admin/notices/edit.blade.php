@extends('layouts.admin')
@section('title', 'Edit Notice')
@section('page-title', 'Edit Notice')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.notices.index') }}">Notices</a></li>
    <li class="breadcrumb-item active">Edit Notice</li>
@endsection

@section('content')

@php
    $isLockedPublishedNotice = $notice->is_published && $notice->publish_date->lte(now());
@endphp

<div class="alert {{ $isLockedPublishedNotice ? 'alert-warning' : 'alert-light border' }} d-flex flex-wrap align-items-center gap-2 py-2 px-3" style="max-width:720px">
    <span class="fw-semibold text-sm">Edit rule:</span>
    @if($isLockedPublishedNotice)
        <span class="text-sm">This notice is already visible. Title, content, audience, and publish date are locked; archive and create a corrected notice for material changes.</span>
    @else
        <span class="text-muted text-sm">Draft or scheduled notices can be corrected before they become visible. Review audience and dates before publishing.</span>
    @endif
</div>

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Notice</span>
        <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <div class="small text-muted mb-3">
            Owner: Admin / Director. Source after update: official notice board and role-specific portal notice lists.
        </div>
        @error('notice')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('admin.notices.update', $notice) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input aria-label="Title" type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $notice->title) }}" required>
                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea aria-label="Content" name="content" class="form-control @error('content') is-invalid @enderror" rows="5" required>{{ old('content', $notice->content) }}</textarea>
                    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Audience <span class="text-danger">*</span></label>
                    <select aria-label="Audience" name="audience" class="form-select" required>
                        @foreach(['all' => 'All users', 'students' => 'Students / parents', 'teachers' => 'Teachers', 'admin' => 'Admin / staff'] as $a => $label)
                            <option value="{{ $a }}" @selected(old('audience',$notice->audience)==$a)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Audience controls which portal notice lists can show this notice.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Publish Date <span class="text-danger">*</span></label>
                    <input aria-label="Publish Date" type="date" name="publish_date" class="form-control" value="{{ old('publish_date', $notice->publish_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input aria-label="Expiry Date" type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date', optional($notice->expiry_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_published" class="form-check-input" id="ip" value="1" @checked(old('is_published',$notice->is_published))>
                        <label class="form-check-label" for="ip">Published</label>
                    </div>
                    <div class="form-text">Published visible notices preserve their communication contract. Expiry can be extended, but core content changes are blocked.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Notice</button>
                <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
