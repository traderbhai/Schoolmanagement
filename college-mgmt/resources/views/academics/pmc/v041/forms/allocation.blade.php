<form method="POST" action="{{ route('academics.pmc.course-allocation.bulk-core') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Bulk Core Allocation</div>
    <div class="card-body vstack gap-2">
        <input aria-label="Allocation batch title" class="form-control form-control-sm" name="title" placeholder="Allocation batch title" required>
        <select aria-label="Program" class="form-select form-select-sm" name="program_id" required><option value="">Select program</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}">{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select>
        <select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">Any batch</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}">{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select>
        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Any term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <select aria-label="Core subjects to allocate" class="form-select form-select-sm" name="subject_ids[]" multiple required>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select>
        <input aria-label="Max credits" class="form-control form-control-sm" name="max_credits" placeholder="Max credits" value="30">
        <button class="btn btn-sm btn-primary">Allocate Core Courses</button>
    </div>
</form>
<form method="POST" action="{{ route('academics.pmc.elective-allocation.process') }}" class="card shadow-sm mt-3">@csrf
    <div class="card-header py-2 fw-semibold">Process Elective Choices</div>
    <div class="card-body vstack gap-2">
        <input aria-label="Elective allocation title" class="form-control form-control-sm" name="title" placeholder="Elective allocation title" value="PMC Elective Choice Allocation">
        <select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">Any program</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}">{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select>
        <select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">Any batch</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}">{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select>
        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Any term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <select aria-label="Elective subjects to allocate" class="form-select form-select-sm" name="subject_ids[]" multiple>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select>
        <input aria-label="Capacity per elective" class="form-control form-control-sm" name="capacity_per_subject" placeholder="Capacity per elective" value="60">
        <button class="btn btn-sm btn-outline-primary">Allocate Electives</button>
    </div>
</form>
<form method="POST" action="{{ route('academics.pmc.course-allocation-exceptions.store') }}" class="card shadow-sm mt-3">@csrf
    <div class="card-header py-2 fw-semibold">Course Basket Exception</div>
    <div class="card-body vstack gap-2">
        <select aria-label="Student" class="form-select form-select-sm" name="student_id" required><option value="">Select student</option>@foreach($selectorOptions['students'] ?? [] as $student)<option value="{{ $student->id }}">{{ $student->user?->name ?? $student->enrollment_number ?? $student->roll_number ?? $student->student_id ?? 'Unassigned student' }}</option>@endforeach</select>
        <select aria-label="Subject" class="form-select form-select-sm" name="subject_id" required><option value="">Select subject</option>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select>
        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Any term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <select aria-label="Exception Type" class="form-select form-select-sm" name="exception_type">
            <option value="add">add</option>
            <option value="drop">drop</option>
            <option value="repeat">repeat</option>
            <option value="backlog">backlog</option>
            <option value="improvement">improvement</option>
            <option value="audit">audit</option>
            <option value="open_elective">open elective</option>
        </select>
        <input aria-label="Credit delta" class="form-control form-control-sm" name="credit_delta" placeholder="Credit delta" value="3">
        <textarea aria-label="Reason for exception" class="form-control form-control-sm" name="reason" placeholder="Reason for exception" required></textarea>
        <button class="btn btn-sm btn-outline-primary">Request Exception</button>
    </div>
</form>
