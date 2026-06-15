<form method="POST" action="{{ route('academics.pmc.timetable-change-requests.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Request Freeze / Revision</div>
    <div class="card-body vstack gap-2">
        <select class="form-select form-select-sm" name="timetable_version_id"><option value="">Latest applicable version</option>@foreach($selectorOptions['timetableVersions'] ?? [] as $version)<option value="{{ $version->id }}">Version {{ $version->version_number }} - {{ $version->program?->code ?? 'Program' }} / {{ $version->status }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="change_type"><option value="revision">Revision</option><option value="freeze">Freeze</option><option value="unfreeze">Unfreeze</option><option value="publish">Publish</option><option value="rollback">Rollback</option></select>
        <textarea class="form-control form-control-sm" name="reason" placeholder="Reason required" required></textarea>
        <button class="btn btn-sm btn-primary">Create Request</button>
    </div>
</form>
