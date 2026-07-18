@extends('layouts.admin')
@section('title', 'Organizational Hierarchy')
@section('page-title', 'Org Hierarchy Config')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Organizational Hierarchy</h4>
        <p class="text-muted mb-0 small">Configure reporting lines between roles. Superior roles gain dashboard visibility into subordinate portals.</p>
    </div>
</div>

<div class="row g-4">

    {{-- Visual Org Tree --}}
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Current Hierarchy</div>
            <div class="card-body">
                @php
                    $roleLabels = [
                        'director'=>'Director','admin'=>'Admin','dean_academics'=>'Dean (Academics)',
                        'hod'=>'HOD','program_chair'=>'Program Chair / PMC','exam_cell'=>'Exam Cell',
                        'accounts_officer'=>'Accounts Officer','cmc'=>'CMC / Placement',
                        'teacher'=>'Teacher','admission_director'=>'Admission Director',
                        'admission_head'=>'Admission Head','admission_manager'=>'Admission Manager',
                        'jr_admission_manager'=>'Jr. Admission Manager','admission_counsellor'=>'Admission Counsellor',
                        'admission_telecaller'=>'Admission Telecaller','admission_officer'=>'Admission Officer',
                    ];
                    $roleColors = [
                        'director'=>'primary','admin'=>'dark','dean_academics'=>'info',
                        'hod'=>'success','program_chair'=>'primary','exam_cell'=>'warning',
                        'accounts_officer'=>'success','cmc'=>'purple','teacher'=>'secondary',
                        'admission_director'=>'info','admission_head'=>'info','admission_manager'=>'info',
                        'jr_admission_manager'=>'info','admission_counsellor'=>'info',
                        'admission_telecaller'=>'info','admission_officer'=>'info',
                    ];
                @endphp

                @foreach($grouped as $parent => $children)
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-{{ $roleColors[$parent] ?? 'secondary' }} fs-7 px-2 py-1">
                            <i class="bi bi-person-fill me-1"></i>{{ $roleLabels[$parent] ?? ucwords(str_replace('_',' ',$parent)) }}
                        </span>
                        <span class="text-muted small">supervises</span>
                    </div>
                    <div class="ms-3 border-start border-2 ps-3">
                        @foreach($children as $child)
                        <div class="d-flex align-items-center justify-content-between mb-1 py-1 px-2 rounded" style="background:var(--clr-bg-secondary,#f8f9fa)">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-arrow-return-right text-muted small"></i>
                                <span class="small fw-semibold">{{ $roleLabels[$child->child_role] ?? $child->child_role }}</span>
                            </div>
                            <div class="d-flex gap-1">
                                @if($child->can_view_summary)
                                <span class="badge bg-light text-muted border" style="font-size:.65rem">Summary</span>
                                @endif
                                @if($child->can_view_full)
                                <span class="badge bg-success" style="font-size:.65rem">Full Access</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                @if($grouped->isEmpty())
                <p class="text-muted text-center py-3">No reporting lines configured.</p>
                @endif
            </div>
        </div>

        {{-- Add New Line --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-plus-circle me-2 text-success"></i>Add Reporting Line</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.org-hierarchy.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Superior Role</label>
                        <select aria-label="Parent Role" name="parent_role" class="form-select form-select-sm" required>
                            <option value="">Select role...</option>
                            @foreach($allRoles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subordinate Role</label>
                        <select aria-label="Child Role" name="child_role" class="form-select form-select-sm" required>
                            <option value="">Select role...</option>
                            @foreach($allRoles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="can_view_summary" id="cvs" value="1" checked>
                            <label class="form-check-label small" for="cvs">Can view summary</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="can_view_full" id="cvf" value="1">
                            <label class="form-check-label small" for="cvf">Full portal access</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100">
                        <i class="bi bi-plus me-1"></i>Add Line
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Table View --}}
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-table me-2"></i>All Reporting Lines</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Superior Role</th>
                            <th scope="col">Subordinate Role</th>
                            <th scope="col" class="text-center">Summary View</th>
                            <th scope="col" class="text-center">Full Access</th>
                            <th aria-label="Actions" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                        <tr>
                            <td>
                                <span class="badge bg-{{ $roleColors[$line->parent_role] ?? 'secondary' }}">
                                    {{ $roleLabels[$line->parent_role] ?? $line->parent_role }}
                                </span>
                            </td>
                            <td class="fw-semibold">{{ $roleLabels[$line->child_role] ?? $line->child_role }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.org-hierarchy.update', $line) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="can_view_full" value="{{ (int)$line->can_view_full }}">
                                    <button type="submit" name="can_view_summary" value="{{ $line->can_view_summary ? 0 : 1 }}"
                                        class="btn btn-xs border-0 bg-transparent p-0"
                                        aria-label="{{ $line->can_view_summary ? 'Disable' : 'Enable' }} summary dashboard visibility for {{ $roleLabels[$line->parent_role] ?? $line->parent_role }} over {{ $roleLabels[$line->child_role] ?? $line->child_role }}"
                                        onclick="return confirm('Update summary dashboard visibility for this reporting line? This changes what superior roles can see from subordinate portals.')">
                                        <i class="bi bi-toggle-{{ $line->can_view_summary ? 'on text-success' : 'off text-muted' }} fs-5"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.org-hierarchy.update', $line) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="can_view_summary" value="{{ (int)$line->can_view_summary }}">
                                    <button type="submit" name="can_view_full" value="{{ $line->can_view_full ? 0 : 1 }}"
                                        class="btn btn-xs border-0 bg-transparent p-0"
                                        aria-label="{{ $line->can_view_full ? 'Disable' : 'Enable' }} full portal access for {{ $roleLabels[$line->parent_role] ?? $line->parent_role }} over {{ $roleLabels[$line->child_role] ?? $line->child_role }}"
                                        onclick="return confirm('Update full portal access for this reporting line? This changes whether superior roles can open subordinate portal pages.')">
                                        <i class="bi bi-toggle-{{ $line->can_view_full ? 'on text-success' : 'off text-muted' }} fs-5"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.org-hierarchy.destroy', $line) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger border-0 py-0"
                                        aria-label="Remove reporting line between {{ $roleLabels[$line->parent_role] ?? $line->parent_role }} and {{ $roleLabels[$line->child_role] ?? $line->child_role }}"
                                        onclick="return confirm('Remove this reporting line between {{ addslashes($roleLabels[$line->parent_role] ?? $line->parent_role) }} and {{ addslashes($roleLabels[$line->child_role] ?? $line->child_role) }}? This immediately changes dashboard visibility and full portal access for affected roles.')">
                                        <i class="bi bi-trash3 small"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No reporting lines yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- What this means box --}}
        <div class="card border-0 shadow-sm mt-4 border-start border-4 border-info">
            <div class="card-header bg-info bg-opacity-10 fw-semibold text-info-emphasis">
                <i class="bi bi-info-circle me-2"></i>How Hierarchy Works
            </div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li class="mb-1"><strong>Summary View</strong> — Superior sees key metrics from the subordinate's portal on their own dashboard (student counts, pending items, etc.).</li>
                    <li class="mb-1"><strong>Full Portal Access</strong> — Superior can navigate directly to the subordinate portal's pages (e.g., Director can open HOD dashboard, browse faculty roster).</li>
                    <li class="mb-1">Different institutes have different structures. Delete any default line and add your own to match your org chart.</li>
                    <li>Changes take effect immediately — no restart needed.</li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
