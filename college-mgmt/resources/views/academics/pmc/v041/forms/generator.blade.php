<form method="POST" action="{{ route('academics.pmc.timetable-generator.generate') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Run Constraint Generator</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="title" placeholder="Run title" value="PMC Generated Timetable">
        <select class="form-select form-select-sm" name="strategy">@foreach(['balanced','student_compact','faculty_balanced','adjunct_priority','room_optimized'] as $strategy)<option value="{{ $strategy }}">{{ str($strategy)->headline() }}</option>@endforeach</select>
        <input class="form-control form-control-sm" name="program_id" placeholder="Program ID">
        <input class="form-control form-control-sm" name="batch_id" placeholder="Batch ID">
        <input class="form-control form-control-sm" name="term_id" placeholder="Term ID">
        <button class="btn btn-sm btn-primary">Generate Draft</button>
    </div>
</form>
