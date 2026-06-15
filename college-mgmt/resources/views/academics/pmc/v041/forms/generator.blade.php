<form method="POST" action="{{ route('academics.pmc.timetable-generator.generate') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Run Constraint Generator</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="title" placeholder="Run title" value="PMC Generated Timetable">
        <select class="form-select form-select-sm" name="strategy">@foreach(['balanced','student_compact','faculty_balanced','adjunct_priority','room_optimized'] as $strategy)<option value="{{ $strategy }}">{{ str($strategy)->headline() }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="program_id"><option value="">Any program</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}">{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="batch_id"><option value="">Any batch</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}">{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="term_id"><option value="">Any term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <button class="btn btn-sm btn-primary">Generate Draft</button>
    </div>
</form>
