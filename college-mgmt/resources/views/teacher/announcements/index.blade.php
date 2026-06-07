@extends('layouts.teacher')

@section('title', 'Subject Announcements')

@section('content')
<div class="container-fluid px-4">

    <h4 class="mb-3"><i class="bi bi-megaphone me-2 text-primary"></i>Subject Announcements</h4>

    {{-- Post Announcement --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold bg-white">
            <i class="bi bi-pencil-square me-1 text-primary"></i>Post New Announcement
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.announcements.store') }}">
                @csrf

                @if($errors->any())
                    <div class="alert alert-danger py-2 mb-3">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li class="small">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                            <option value="">— Select Subject —</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="form-control @error('title') is-invalid @enderror"
                               placeholder="Announcement title" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                        <textarea name="body" rows="4"
                                  class="form-control @error('body') is-invalid @enderror"
                                  placeholder="Write your announcement here…" required>{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 d-flex align-items-center justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned"
                                   id="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_pinned">
                                <i class="bi bi-pin-angle me-1 text-warning"></i>Pin this announcement
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-megaphone me-1"></i> Post Announcement
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Announcements list --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold bg-white">
            <i class="bi bi-list-ul me-1"></i>Posted Announcements
        </div>
        <div class="card-body p-0">
            @if($announcements->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-25"></i>
                    No announcements posted yet.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Posted At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($announcements as $ann)
                                <tr>
                                    <td>
                                        @if($ann->is_pinned)
                                            <i class="bi bi-pin-angle-fill text-warning me-1" title="Pinned"></i>
                                        @endif
                                        <span class="fw-semibold">{{ $ann->title }}</span>
                                        @if($ann->body)
                                            <div class="text-muted small">{{ Str::limit($ann->body, 80) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small text-muted">{{ $ann->subject->code ?? '' }}</div>
                                        {{ $ann->subject->name ?? '—' }}
                                    </td>
                                    <td class="text-muted small text-nowrap">
                                        {{ $ann->created_at->format('d M Y, H:i') }}
                                    </td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="{{ route('teacher.announcements.destroy', $ann) }}"
                                              onsubmit="return confirm('Delete this announcement?')">
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

                @if($announcements->hasPages())
                    <div class="card-footer d-flex justify-content-end">
                        {{ $announcements->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection
