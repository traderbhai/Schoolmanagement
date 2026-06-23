<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Generation Runs</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Strategy</th>
                    <th>Scheduled</th>
                    <th>Conflicts</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $run->title }}</div>
                            <div class="small text-muted">{{ $run->status }}</div>
                            <div class="small text-muted">Teaching slots {{ $run->input_summary['teaching_slots'] ?? $run->input_summary['slots'] ?? '-' }} | Breaks excluded {{ $run->input_summary['break_slots'] ?? 0 }}</div>
                        </td>
                        <td>{{ $run->strategy }}</td>
                        <td>{{ $run->scheduled_count }}/{{ $run->scheduled_count + $run->unscheduled_count }}</td>
                        <td>{{ $run->hard_conflict_count }} hard / {{ $run->soft_warning_count }} soft</td>
                        <td>
                            {{ $run->quality_score }}%
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <form method="POST" action="{{ route('academics.pmc.timetable-generator.validate', $run) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-secondary py-0 px-1">Validate</button>
                                </form>
                                <form method="POST" action="{{ route('academics.pmc.timetable-generator.impact-preview', $run) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-info py-0 px-1">Impact</button>
                                </form>
                                <form method="POST" action="{{ route('academics.pmc.timetable-generator.publish', $run) }}">
                                    @csrf
                                    <input type="hidden" name="decision_reason" value="Published from PMC generator">
                                    <button class="btn btn-xs btn-outline-primary py-0 px-1">Publish</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No generation runs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer py-2">{{ $runs->links() }}</div>
</div>

@isset($sessionDemands)
    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 fw-semibold">Weekly Session Demand</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Scheduled</th>
                        <th>Unscheduled</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessionDemands as $demand)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $demand->courseGroup?->name }}</div>
                                <div class="small text-muted">{{ $demand->courseGroup?->subject?->name }}</div>
                            </td>
                            <td>{{ $demand->session_type }}<div class="small text-muted">{{ $demand->duration_slots }} slot(s)</div></td>
                            <td>{{ $demand->required_sessions_per_week }}</td>
                            <td>{{ $demand->scheduled_sessions }}</td>
                            <td>{{ $demand->unscheduled_sessions }}</td>
                            <td>{{ $demand->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No weekly session demand generated yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $sessionDemands->links() }}</div>
    </div>
@endisset

<div class="card shadow-sm">
    <div class="card-header py-2 fw-semibold">Generated Items</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Session</th>
                    <th>Faculty</th>
                    <th>Room</th>
                    <th>Day/Slot</th>
                    <th>Solver Reason</th>
                    <th>Manual Move</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>
                            <a class="fw-semibold" href="{{ route('academics.pmc.canonical-sessions.show', $item) }}">{{ $item->courseGroup?->name ?? 'Session #' . $item->id }}</a>
                            <div class="small text-muted">Canonical #{{ $item->id }}</div>
                        </td>
                        <td>
                            {{ $item->session_type }} #{{ $item->session_index }}
                            <div class="small text-muted">{{ $item->duration_slots }} slot(s) | {{ $item->operational_timetable_entry_id ? 'synced' : 'draft' }}</div>
                        </td>
                        <td>{{ $item->teacher?->user?->name }}</td>
                        <td>{{ $item->classroom?->name }}</td>
                        <td>{{ $item->day_of_week }}/{{ $item->slot?->name }}</td>
                        <td>
                            <div class="small">Score {{ $item->metadata['placement_score'] ?? $item->confidence ?? '-' }}</div>
                            <div class="small text-muted">{{ collect($item->metadata['placement_reasons'] ?? [])->take(2)->implode(', ') ?: 'No solver reasons recorded' }}</div>
                            @if($item->status === 'unscheduled' && !empty($item->metadata['unscheduled_diagnostics']))
                                @php($diagnostics = $item->metadata['unscheduled_diagnostics'])
                                <div class="small text-danger fw-semibold">Blocked: {{ str_replace('_', ' ', $diagnostics['primary_blocker'] ?? 'no feasible candidate') }}</div>
                                @if(!empty($diagnostics['recommended_actions'][0]))
                                    <div class="small text-muted">{{ $diagnostics['recommended_actions'][0] }}</div>
                                @endif
                                @if(!empty($diagnostics['sampled_blocked_candidates']))
                                    <div class="small text-muted">
                                        Samples:
                                        {{ collect($diagnostics['sampled_blocked_candidates'])->take(2)->map(fn($candidate) => 'D'.($candidate['day'] ?? '-').'/S'.($candidate['slot_id'] ?? '-').' '.($candidate['reason'] ?? 'blocked'))->implode(' | ') }}
                                    </div>
                                @endif
                            @endif
                            @if(!empty($item->metadata['placement_alternatives']))
                                <div class="small text-muted">Alt: {{ collect($item->metadata['placement_alternatives'])->take(2)->map(fn($alt) => 'D'.($alt['day'] ?? '-').'/'.($alt['slot_name'] ?? 'slot').' '.$alt['score'])->implode(' | ') }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach(collect($item->metadata['placement_alternatives'])->take(2) as $altIndex => $alt)
                                        <form method="POST" action="{{ route('academics.pmc.timetable-generator-items.apply-alternative', $item) }}">
                                            @csrf
                                            <input type="hidden" name="alternative_index" value="{{ $altIndex }}">
                                            <input type="hidden" name="decision_note" value="Applied solver alternative from generator table">
                                            <button class="btn btn-xs btn-outline-primary py-0 px-1">Apply D{{ $alt['day'] ?? '-' }}/{{ $alt['slot_name'] ?? 'slot' }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td style="min-width: 360px">
                            @if($item->status === 'scheduled')
                                <form method="POST" action="{{ route('academics.pmc.timetable-generator-items.move', $item) }}" class="d-flex flex-wrap gap-1 align-items-center">
                                    @csrf
                                    <select class="form-select form-select-sm py-0" name="day_of_week" style="width: 72px" aria-label="Move day">
                                        @foreach(range(1, 7) as $day)
                                            <option value="{{ $day }}" @selected((int) $item->day_of_week === $day)>D{{ $day }}</option>
                                        @endforeach
                                    </select>
                                    <select class="form-select form-select-sm py-0" name="timetable_slot_id" style="width: 130px" aria-label="Move slot">
                                        @foreach(($selectorOptions['slots'] ?? collect()) as $slot)
                                            <option value="{{ $slot->id }}" @selected((int) $item->timetable_slot_id === (int) $slot->id)>{{ $slot->name }}</option>
                                        @endforeach
                                    </select>
                                    <select class="form-select form-select-sm py-0" name="classroom_id" style="width: 140px" aria-label="Move room">
                                        @foreach(($selectorOptions['classrooms'] ?? collect()) as $room)
                                            <option value="{{ $room->id }}" @selected((int) $item->classroom_id === (int) $room->id)>{{ $room->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="decision_note" value="Manual move from generator table">
                                    <button class="btn btn-xs btn-outline-secondary py-0 px-1">Move</button>
                                </form>
                                <div class="small text-muted mt-1">Validates conflicts before saving; Dean/Admin override remains backend-controlled.</div>
                            @else
                                <span class="text-muted small">Schedule item first</span>
                            @endif
                        </td>
                        <td>{{ $item->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer py-2">{{ $items->links() }}</div>
</div>

@isset($impactPreview)
    <div class="card shadow-sm mt-3">
        <div class="card-header py-2 fw-semibold">Pre-Publish Impact Preview</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Impact</th>
                        <th>Affected</th>
                        <th>Severity</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($impactPreview as $impact)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $impact->title }}</div>
                                <div class="small text-muted">{{ str($impact->impact_type)->headline() }}</div>
                            </td>
                            <td>{{ $impact->affected_count }}</td>
                            <td>{{ $impact->metadata['severity'] ?? '-' }}</td>
                            <td class="small text-muted">Run #{{ $impact->metadata['generation_run_id'] ?? '-' }} | {{ $impact->metadata['refreshed_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No impact preview yet. Use Impact on a generation run before publish or revision.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $impactPreview->links() }}</div>
    </div>
@endisset

@isset($publishChecks)
    <div class="card shadow-sm mt-3">
        <div class="card-header py-2 fw-semibold">Publish Checks</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Required Role</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($publishChecks as $check)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $check->title }}</div>
                                <div class="small text-muted">{{ $check->description }}</div>
                            </td>
                            <td>{{ $check->status }}</td>
                            <td>{{ $check->required_role }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $publishChecks->links() }}</div>
    </div>
@endisset
