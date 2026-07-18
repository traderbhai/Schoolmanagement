<form method="POST" action="{{ route('academics.pmc.timetable-change-requests.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Request Freeze / Revision</div>
    <div class="card-body vstack gap-2">
        <select aria-label="Timetable Version" class="form-select form-select-sm" name="timetable_version_id"><option value="">Latest applicable version</option>@foreach($selectorOptions['timetableVersions'] ?? [] as $version)<option value="{{ $version->id }}">Version {{ $version->version_number }} - {{ $version->program?->code ?? 'Program' }} / {{ $version->status }}</option>@endforeach</select>
        <select aria-label="Pmc Generation Item" class="form-select form-select-sm" name="pmc_generation_item_id"><option value="">Whole version / no specific session</option>@foreach($selectorOptions['officialTimetableItems'] ?? [] as $item)<option value="{{ $item->id }}">{{ $item->courseGroup?->name ?? 'Group pending' }} - {{ $item->subject?->code ?? $item->courseGroup?->subject?->code ?? 'Subject' }} - {{ $item->teacher?->user?->name ?? 'Faculty pending' }} - {{ $item->slot?->name ?? 'Slot pending' }}</option>@endforeach</select>
        <select aria-label="Change Type" class="form-select form-select-sm" name="change_type"><option value="revision">Revision</option><option value="freeze">Freeze</option><option value="unfreeze">Unfreeze</option><option value="publish">Publish</option><option value="rollback">Rollback</option></select>
        <textarea aria-label="Reason required" class="form-control form-control-sm" name="reason" placeholder="Reason required" required></textarea>
        <button class="btn btn-sm btn-primary">Create Request</button>
    </div>
</form>
