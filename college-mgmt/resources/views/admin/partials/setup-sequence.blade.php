<div class="alert alert-primary border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Admin setup sequence</div>
            <div class="small text-muted">Use this order when configuring a new institute, academic year, or intake.</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.academic-years.index') }}">1. Academic year</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.departments.index') }}">2. Departments</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.programs.index') }}">3. Programs</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.batches.index') }}">4. Batches</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.semesters.index') }}">5. Terms</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.role-assignments.index') }}">6. Users and roles</a>
            <a class="badge text-bg-light text-decoration-none border" href="{{ route('admin.roles.permissions.index') }}">7. Permissions</a>
        </div>
    </div>
</div>
