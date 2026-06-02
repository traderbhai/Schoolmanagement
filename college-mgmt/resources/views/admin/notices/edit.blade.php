@extends('layouts.admin')
@section('title','Edit Notice')
@section('page-title','Edit Notice')
@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header">Edit Notice</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.notices.update', $notice) }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" class="form-control" value="{{ old('title', $notice->title) }}" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Content *</label><textarea name="content" class="form-control" rows="5" required>{{ old('content', $notice->content) }}</textarea></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Audience *</label>
                    <select name="audience" class="form-select" required>
                        @foreach(['all','students','teachers','admin'] as $a)<option value="{{ $a }}" @selected(old('audience',$notice->audience)==$a)>{{ ucfirst($a) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Publish Date *</label><input type="date" name="publish_date" class="form-control" value="{{ old('publish_date', $notice->publish_date->format('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Expiry Date</label><input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date', optional($notice->expiry_date)->format('Y-m-d')) }}"></div>
            </div>
            <div class="mt-3 form-check"><input type="checkbox" name="is_published" class="form-check-input" id="ip" value="1" @checked(old('is_published',$notice->is_published))><label class="form-check-label" for="ip">Published</label></div>
            <div class="mt-3 d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
