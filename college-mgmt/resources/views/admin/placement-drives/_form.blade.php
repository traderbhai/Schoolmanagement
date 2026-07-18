<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select aria-label="Company" name="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
            <option value="">Select Company</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(old('company_id', $drive->company_id ?? '') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Drive Title <span class="text-danger">*</span></label>
        <input aria-label="Title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', $drive->title ?? '') }}" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Job Role <span class="text-danger">*</span></label>
        <input aria-label="Job Role" type="text" name="job_role" class="form-control @error('job_role') is-invalid @enderror"
            value="{{ old('job_role', $drive->job_role ?? '') }}" required placeholder="e.g. Software Engineer">
        @error('job_role')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Package</label>
        <input aria-label="Package" type="text" name="package" class="form-control @error('package') is-invalid @enderror"
            value="{{ old('package', $drive->package ?? '') }}" placeholder="e.g. 12 LPA">
        @error('package')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select aria-label="Status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach(['upcoming','ongoing','completed','cancelled'] as $s)
                <option value="{{ $s }}" @selected(old('status', $drive->status ?? 'upcoming') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Drive Date</label>
        <input aria-label="Drive Date" type="date" name="drive_date" class="form-control @error('drive_date') is-invalid @enderror"
            value="{{ old('drive_date', isset($drive->drive_date) ? $drive->drive_date->format('Y-m-d') : '') }}">
        @error('drive_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Last Apply Date</label>
        <input aria-label="Last Apply Date" type="date" name="last_apply_date" class="form-control @error('last_apply_date') is-invalid @enderror"
            value="{{ old('last_apply_date', isset($drive->last_apply_date) ? $drive->last_apply_date->format('Y-m-d') : '') }}">
        @error('last_apply_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Location</label>
        <input aria-label="Location" type="text" name="location" class="form-control @error('location') is-invalid @enderror"
            value="{{ old('location', $drive->location ?? '') }}">
        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Min CGPA</label>
        <input aria-label="Min Cgpa" type="number" name="min_cgpa" step="0.01" min="0" max="10" class="form-control @error('min_cgpa') is-invalid @enderror"
            value="{{ old('min_cgpa', $drive->min_cgpa ?? '') }}">
        @error('min_cgpa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Vacancies</label>
        <input aria-label="Vacancies" type="number" name="vacancies" min="1" class="form-control @error('vacancies') is-invalid @enderror"
            value="{{ old('vacancies', $drive->vacancies ?? '') }}">
        @error('vacancies')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">Eligibility Criteria</label>
        <input aria-label="Eligibility" type="text" name="eligibility" class="form-control @error('eligibility') is-invalid @enderror"
            value="{{ old('eligibility', $drive->eligibility ?? '') }}" placeholder="e.g. B.Tech/MCA, 60% aggregate">
        @error('eligibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea aria-label="Description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $drive->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
