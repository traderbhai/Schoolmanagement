@extends('layouts.admin')
@section('title', 'Add Role Assignment')
@section('page-title', 'Add Role Assignment')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Assign Departmental Role</div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.role-assignments.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">User <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">— Select User —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>
                                    {{ $u->name }} ({{ $u->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role_name" class="form-select @error('role_name') is-invalid @enderror" required>
                            <option value="">— Select Role —</option>
                            @foreach($roles as $key => $desc)
                                <option value="{{ $key }}" @selected(old('role_name') == $key)>
                                    {{ ucwords(str_replace('_', ' ', $key)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div id="role-desc" class="form-text text-muted mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Program <span class="text-muted">(optional — leave blank to apply to all programs)</span></label>
                        <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror">
                            <option value="">— All Programs —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" @selected(old('program_id') == $p->id)>
                                    {{ $p->name }} ({{ $p->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batch <span class="text-muted">(optional — only relevant if program is selected)</span></label>
                        <select name="batch_id" class="form-select @error('batch_id') is-invalid @enderror">
                            <option value="">— All Batches —</option>
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}" @selected(old('batch_id') == $b->id)>
                                    {{ $b->code ?: $b->name ?: 'Unnamed batch' }} ({{ $b->program?->code ?? 'Program pending' }})
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Assign Role</button>
                        <a href="{{ route('admin.role-assignments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const roleDesc = @json($roles);
document.querySelector('[name=role_name]').addEventListener('change', function() {
    document.getElementById('role-desc').textContent = roleDesc[this.value] || '';
});
</script>
@endpush
@endsection
