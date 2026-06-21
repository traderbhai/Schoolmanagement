@extends('layouts.admin')
@section('title', 'New Notice')
@section('page-title', 'Create Notice')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.notices.index') }}">Notices</a></li>
    <li class="breadcrumb-item active">Add Notice</li>
@endsection

@section('content')

<div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 px-3" style="max-width:720px">
    <span class="fw-semibold text-sm">Create sequence:</span>
    <span class="text-muted text-sm">1. write official title/body</span>
    <span class="text-muted text-sm">2. choose audience</span>
    <span class="text-muted text-sm">3. choose publish/expiry dates</span>
    <span class="text-muted text-sm">4. publish only when ready for portal visibility</span>
</div>

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>New Notice</span>
        <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <div class="small text-muted mb-3">
            Owner: Admin / Director. Source after save: official notice board, portal notice lists, notification email queue for current visible student notices.
        </div>
        <form method="POST" action="{{ route('admin.notices.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    <div class="form-text">Brief, descriptive title for the notice.</div>
                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control @error('content') is-invalid @enderror" rows="5" required>{{ old('content') }}</textarea>
                    <div class="form-text">Full notice body / message.</div>
                    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Audience <span class="text-danger">*</span></label>
                    <select name="audience" class="form-select @error('audience') is-invalid @enderror" required>
                        @foreach(['all' => 'All users', 'students' => 'Students / parents', 'teachers' => 'Teachers', 'admin' => 'Admin / staff'] as $a => $label)
                            <option value="{{ $a }}" @selected(old('audience')==$a)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Who can see this notice in their portal.</div>
                    @error('audience')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Publish Date <span class="text-danger">*</span></label>
                    <input type="date" name="publish_date" class="form-control @error('publish_date') is-invalid @enderror" value="{{ old('publish_date', date('Y-m-d')) }}" required>
                    <div class="form-text">When the notice goes live.</div>
                    @error('publish_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
                    <div class="form-text">Leave blank for no expiry.</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_published" class="form-check-input" id="is_published" value="1" @checked(old('is_published', true))>
                        <label class="form-check-label" for="is_published">Publish immediately</label>
                    </div>
                    <div class="form-text">If published for students or all users with today's publish window, student notice email dispatch is queued. Keep draft unchecked when the content still needs review.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Publish Notice</button>
                <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
