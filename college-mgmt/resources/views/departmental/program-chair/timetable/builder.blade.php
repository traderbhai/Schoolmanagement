@extends('layouts.admin')
@section('title', 'Timetable Builder')

@section('content')
<div class="container-fluid py-4">
  <form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
      <select name="program_id" class="form-select" onchange="this.form.submit()">
        @foreach($programs as $p)
          <option value="{{ $p->id }}" @selected($selectedProgram?->id == $p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <select name="term_id" class="form-select" onchange="this.form.submit()">
        @foreach($terms as $t)
          <option value="{{ $t->id }}" @selected($selectedTerm?->id == $t->id)>{{ $t->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Batches</option>
        @foreach($batches as $b)
          <option value="{{ $b->id }}" @selected($selectedBatch?->id == $b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2 align-items-center">
      @if($version)
        <span class="badge bg-{{ $version->status === 'published' ? 'success' : ($version->status === 'draft' ? 'warning text-dark' : 'secondary') }}">
          v{{ $version->version_number }} - {{ ucfirst($version->status) }}
        </span>
      @endif
      <a href="{{ route('chair.timetable.substitutions') }}" class="btn btn-outline-secondary btn-sm ms-auto">Substitutions</a>
      <a href="{{ route('chair.timetable.availability') }}" class="btn btn-outline-secondary btn-sm">Availability</a>
    </div>
    <div class="col-md-2">
      <select name="teacher_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Faculty</option>
        @foreach($teachers ?? collect() as $teacher)
          <option value="{{ $teacher->id }}" @selected(($builderFilters['teacher_id'] ?? null) == $teacher->id)>{{ $teacher->user->name ?? $teacher->id }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Rooms</option>
        @foreach($classrooms ?? collect() as $classroom)
          <option value="{{ $classroom->id }}" @selected(($builderFilters['classroom_id'] ?? null) == $classroom->id)>{{ $classroom->name ?? $classroom->room_number ?? $classroom->id }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <select name="course_group_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Sections / Groups</option>
        @foreach($courseGroups ?? collect() as $group)
          <option value="{{ $group->id }}" @selected(($builderFilters['course_group_id'] ?? null) == $group->id)>{{ $group->name }} - {{ str_replace('_', ' ', $group->group_type) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="session_type" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Types</option>
        @foreach(['lecture' => 'Lecture', 'tutorial' => 'Tutorial', 'lab' => 'Lab / Practical', 'seminar' => 'Seminar'] as $value => $label)
          <option value="{{ $value }}" @selected(($builderFilters['session_type'] ?? null) === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="timetable_status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        @foreach(['scheduled' => 'Scheduled', 'published' => 'Published', 'locked' => 'Locked'] as $value => $label)
          <option value="{{ $value }}" @selected(($builderFilters['timetable_status'] ?? null) === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </form>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Timetable Builder @if($selectedProgram)- <span class="text-muted fs-6">{{ $selectedProgram->name }}</span>@endif</h4>
    <form method="POST" action="{{ route('chair.timetable.publish') }}">
      @csrf
      <input type="hidden" name="program_id" value="{{ $selectedProgram?->id }}">
      <input type="hidden" name="term_id" value="{{ $selectedTerm?->id }}">
      <input type="hidden" name="batch_id" value="{{ $selectedBatch?->id }}">
      <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Run conflict audit and publish?')">Publish Timetable</button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle" style="min-width:900px">
      <thead class="table-dark">
        <tr>
          <th style="width:100px">Day / Slot</th>
          @foreach($slots as $slot)
            <th class="{{ $slot->is_break ? 'table-secondary' : '' }}">
              {{ $slot->name }}<br>
              <small class="fw-normal opacity-75">{{ substr($slot->start_time,0,5) }}-{{ substr($slot->end_time,0,5) }}</small>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($days as $dayNum => $dayName)
          <tr>
            <td class="fw-semibold bg-light">{{ $dayName }}</td>
            @foreach($slots as $slot)
              @php
                $entryKey = $dayNum.'-'.$slot->id;
                $entry = $entries[$entryKey] ?? null;
                $canonicalCellEntries = $canonicalEntries->get($entryKey, collect());
              @endphp
              <td class="{{ $slot->is_break ? 'bg-light' : '' }}">
                @if($slot->is_break)
                  <small class="text-muted">Break</small>
                @else
                  <div class="d-grid gap-1">
                    @foreach($canonicalCellEntries as $canonicalEntry)
                      @php
                        $canonicalSubject = $canonicalEntry->subject ?: $canonicalEntry->courseGroup?->subject;
                        $canonicalBatch = $canonicalEntry->batch ?: $canonicalEntry->courseGroup?->batch;
                        $canonicalLabel = $canonicalEntry->official_status === 'published'
                            ? 'Official published'
                            : (($canonicalEntry->is_locked || $canonicalEntry->timetable_version_id) ? 'Revision required' : 'Draft canonical');
                      @endphp
                      <button class="btn btn-sm btn-primary w-100 text-start"
                        data-day="{{ $dayNum }}" data-slot="{{ $slot->id }}"
                        data-entry="{{ json_encode([
                          'subject_id' => $canonicalSubject?->id,
                          'teacher_id' => $canonicalEntry->teacher_id,
                          'classroom_id' => $canonicalEntry->classroom_id,
                          'course_group_id' => $canonicalEntry->course_group_id,
                          'session_type' => $canonicalEntry->session_type,
                          'duration_slots' => $canonicalEntry->duration_slots,
                          'batch_id' => $canonicalEntry->batch_id ?: $canonicalEntry->courseGroup?->batch_id,
                        ]) }}"
                        onclick="openSlotModal(this)">
                        <div class="fw-semibold small">{{ $canonicalSubject?->code ?? $canonicalSubject?->name ?? '?' }}</div>
                        <div class="opacity-75 small">{{ $canonicalEntry->courseGroup?->name ?? $canonicalBatch?->name ?? 'Group pending' }}</div>
                        <div class="small"><span class="badge bg-light text-dark border">{{ $canonicalLabel }}</span></div>
                        <div class="opacity-75 small">{{ $canonicalEntry->teacher->user->name ?? '-' }}</div>
                        <div class="opacity-50 small">{{ $canonicalEntry->classroom->name ?? '' }}</div>
                      </button>
                    @endforeach

                    @if($canonicalCellEntries->isEmpty() && $entry)
                      <button class="btn btn-sm btn-primary w-100"
                        data-day="{{ $dayNum }}" data-slot="{{ $slot->id }}"
                        data-entry="{{ json_encode(['subject_id'=>$entry->subject_id,'teacher_id'=>$entry->teacher_id,'classroom_id'=>$entry->classroom_id,'batch_id'=>$entry->batch_id]) }}"
                        onclick="openSlotModal(this)">
                        <div class="fw-semibold small">{{ $entry->subject->code ?? $entry->subject->name ?? '?' }}</div>
                        <div class="opacity-75 small">{{ $entry->teacher->user->name ?? '-' }}</div>
                        <div class="opacity-50 small">{{ $entry->classroom->name ?? '' }}</div>
                      </button>
                    @endif

                    <button class="btn btn-sm btn-outline-secondary w-100"
                      data-day="{{ $dayNum }}" data-slot="{{ $slot->id }}"
                      data-entry=""
                      onclick="openSlotModal(this)">
                      <span class="text-muted small">+ Assign</span>
                    </button>
                  </div>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="slotModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Slot - <span id="modal-label"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="conflict-alert" class="alert alert-danger d-none small mb-3"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Subject</label>
          <select id="m-subject" class="form-select" onchange="filterCourseGroups(); checkConflict()">
            <option value="">Clear slot</option>
            @foreach($subjectOptions as $subject)
              <option value="{{ $subject->id }}">{{ $subject->name ?? $subject->id }} ({{ $subject->code ?? '' }})</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Course Group / Section</label>
          <select id="m-course-group" class="form-select" onchange="selectCourseGroup(); checkConflict()">
            <option value="">Legacy full batch / no group</option>
            @foreach($courseGroups as $group)
              <option value="{{ $group->id }}" data-subject="{{ $group->subject_id }}" data-batch="{{ $group->batch_id }}" data-type="{{ $group->group_type }}">
                {{ $group->name }} - {{ str_replace('_', ' ', $group->group_type) }} @if($group->batch)({{ $group->batch->name }})@endif
              </option>
            @endforeach
          </select>
          <div class="form-text">Use a group for canonical section, elective, lab, or tutorial sessions.</div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-7">
            <label class="form-label fw-semibold">Session Type</label>
            <select id="m-session-type" class="form-select" onchange="checkConflict()">
              <option value="lecture">Lecture</option>
              <option value="tutorial">Tutorial</option>
              <option value="lab">Lab / Practical</option>
              <option value="seminar">Seminar</option>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold">Duration</label>
            <input id="m-duration" type="number" min="1" max="6" value="1" class="form-control" onchange="checkConflict()">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Teacher</label>
          <select id="m-teacher" class="form-select" onchange="checkConflict(); showAvailability()">
            <option value="">None</option>
            @foreach($teachers as $t)
              <option value="{{ $t->id }}">{{ $t->user->name ?? $t->id }}</option>
            @endforeach
          </select>
          <div id="avail-hint" class="form-text"></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Classroom</label>
          <select id="m-classroom" class="form-select" onchange="checkConflict()">
            <option value="">None</option>
            @foreach($classrooms as $c)
              <option value="{{ $c->id }}">{{ $c->name }} (cap {{ $c->capacity }}{{ $c->has_lab ? ', Lab' : '' }})</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger btn-sm me-auto" onclick="saveSlot(true)">Clear Slot</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="save-btn" onclick="saveSlot(false)">Save</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const SAVE_URL  = '{{ route("chair.timetable.save-slot") }}';
const CHECK_URL = '{{ route("chair.timetable.check-conflict") }}';
const CSRF = '{{ csrf_token() }}';
const PROGRAM_ID = {{ $selectedProgram?->id ?? 'null' }};
const TERM_ID    = {{ $selectedTerm?->id ?? 'null' }};
const BATCH_ID   = {{ $selectedBatch?->id ?? 'null' }};
const AVAIL = @json($availability->mapWithKeys(fn($a,$k) => [$k => $a->availability]));
const DAYS = {1:'Monday',2:'Tuesday',3:'Wednesday',4:'Thursday',5:'Friday',6:'Saturday'};
const COURSE_GROUPS = @json($courseGroupsForBuilder);

let cDay, cSlot;
const modal = new bootstrap.Modal(document.getElementById('slotModal'));

function openSlotModal(btn) {
  cDay = btn.dataset.day; cSlot = btn.dataset.slot;
  document.getElementById('modal-label').textContent = (DAYS[cDay]||cDay);
  const e = btn.dataset.entry ? JSON.parse(btn.dataset.entry) : null;
  document.getElementById('m-subject').value = e?.subject_id || '';
  document.getElementById('m-course-group').value = e?.course_group_id || '';
  document.getElementById('m-session-type').value = e?.session_type || defaultSessionTypeForGroup(e?.course_group_id);
  document.getElementById('m-duration').value = e?.duration_slots || defaultDurationForGroup(e?.course_group_id);
  document.getElementById('m-teacher').value = e?.teacher_id || '';
  document.getElementById('m-classroom').value = e?.classroom_id || '';
  document.getElementById('conflict-alert').classList.add('d-none');
  document.getElementById('save-btn').disabled = false;
  filterCourseGroups();
  showAvailability();
  modal.show();
}

function selectedGroup() {
  const gid = Number(document.getElementById('m-course-group').value || 0);
  return COURSE_GROUPS.find(group => Number(group.id) === gid) || null;
}

function defaultSessionTypeForGroup(groupId) {
  const group = COURSE_GROUPS.find(item => Number(item.id) === Number(groupId));
  return group?.group_type === 'lab_group' ? 'lab' : 'lecture';
}

function defaultDurationForGroup(groupId) {
  const group = COURSE_GROUPS.find(item => Number(item.id) === Number(groupId));
  return group?.group_type === 'lab_group' ? 2 : 1;
}

function filterCourseGroups() {
  const subjectId = document.getElementById('m-subject').value;
  document.querySelectorAll('#m-course-group option').forEach(option => {
    if (!option.value) {
      option.hidden = false;
      return;
    }
    option.hidden = !!subjectId && option.dataset.subject !== subjectId;
  });
}

function selectCourseGroup() {
  const group = selectedGroup();
  if (!group) return;
  document.getElementById('m-subject').value = group.subject_id || '';
  document.getElementById('m-session-type').value = defaultSessionTypeForGroup(group.id);
  document.getElementById('m-duration').value = defaultDurationForGroup(group.id);
  filterCourseGroups();
}

function showAvailability() {
  const tid = document.getElementById('m-teacher').value;
  const key = tid + '-' + cDay + '-' + cSlot;
  const hint = document.getElementById('avail-hint');
  if (!tid) { hint.textContent = ''; return; }
  if (AVAIL[key] === 'unavailable') hint.innerHTML = '<span class="text-danger">Teacher marked unavailable for this slot</span>';
  else if (AVAIL[key] === 'preferred') hint.innerHTML = '<span class="text-success">Preferred slot for this teacher</span>';
  else hint.textContent = '';
}

let timer;
function checkConflict() {
  clearTimeout(timer);
  if (!document.getElementById('m-subject').value) return;
  timer = setTimeout(async () => {
    const group = selectedGroup();
    const res = await fetch(CHECK_URL, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      body: JSON.stringify({
        teacher_id: document.getElementById('m-teacher').value||null,
        classroom_id: document.getElementById('m-classroom').value||null,
        batch_id: group?.batch_id || BATCH_ID,
        day_of_week: cDay,
        timetable_slot_id: cSlot,
        term_id: TERM_ID,
        course_group_id: group?.id || null,
        duration_slots: Number(document.getElementById('m-duration').value || 1),
      })
    });
    const d = await res.json();
    const el = document.getElementById('conflict-alert');
    if (d.conflicts?.length) {
      el.textContent = d.conflicts.join(' | ');
      el.classList.remove('d-none');
      document.getElementById('save-btn').disabled = true;
    } else {
      el.classList.add('d-none');
      document.getElementById('save-btn').disabled = false;
    }
  }, 400);
}

async function saveSlot(clear) {
  const group = selectedGroup();
  const res = await fetch(SAVE_URL, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({
      program_id: PROGRAM_ID,
      term_id: TERM_ID,
      batch_id: group?.batch_id || BATCH_ID || undefined,
      day_of_week: cDay,
      timetable_slot_id: cSlot,
      subject_id: clear ? null : (document.getElementById('m-subject').value || null),
      teacher_id: clear ? null : (document.getElementById('m-teacher').value || null),
      classroom_id: clear ? null : (document.getElementById('m-classroom').value || null),
      course_group_id: group?.id || null,
      session_type: document.getElementById('m-session-type').value || null,
      duration_slots: Number(document.getElementById('m-duration').value || 1),
    })
  });
  if (res.ok) { modal.hide(); location.reload(); }
  else {
    const d = await res.json();
    const el = document.getElementById('conflict-alert');
    el.textContent = (d.conflicts || [d.message || 'Save failed']).join(' | ');
    el.classList.remove('d-none');
  }
}
</script>
@endpush
