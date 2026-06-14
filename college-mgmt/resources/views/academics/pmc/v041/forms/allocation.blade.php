<form method="POST" action="{{ route('academics.pmc.course-allocation.bulk-core') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Bulk Core Allocation</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="title" placeholder="Allocation batch title" required>
        <input class="form-control form-control-sm" name="program_id" placeholder="Program ID" required>
        <input class="form-control form-control-sm" name="batch_id" placeholder="Batch ID">
        <input class="form-control form-control-sm" name="term_id" placeholder="Term ID">
        <input class="form-control form-control-sm" name="subject_ids[]" placeholder="Subject ID" required>
        <input class="form-control form-control-sm" name="max_credits" placeholder="Max credits" value="30">
        <button class="btn btn-sm btn-primary">Allocate Core Courses</button>
    </div>
</form>
