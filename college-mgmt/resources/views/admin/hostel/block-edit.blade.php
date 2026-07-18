@extends('layouts.admin')
@section('title', 'Edit Block: ' . $block->name)
@section('page-title', 'Edit Block: ' . $block->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">Edit Block</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Edit Hostel Block</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.hostel.blocks.update', $block) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Block Name <span class="text-danger">*</span></label>
                        <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $block->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select aria-label="Gender" name="gender" class="form-select" required>
                            @foreach(['boys','girls','mixed'] as $g)
                                <option value="{{ $g }}" @selected(old('gender', $block->gender) === $g)>{{ ucfirst($g) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Floors <span class="text-danger">*</span></label>
                        <input aria-label="Total Floors" type="number" name="total_floors" class="form-control" value="{{ old('total_floors', $block->total_floors) }}" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Warden</label>
                        <select aria-label="Warden" name="warden_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($wardens as $w)
                                <option value="{{ $w->id }}" @selected(old('warden_id', $block->warden_id) == $w->id)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address / Notes</label>
                        <textarea aria-label="Address Notes" name="address_notes" class="form-control" rows="2">{{ old('address_notes', $block->address_notes) }}</textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $block->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('admin.hostel.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
