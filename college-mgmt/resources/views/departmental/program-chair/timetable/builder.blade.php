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
          v{{ $version->version_number }} — {{ ucfirst($version->status) }}
        </span>
      @endif
      <a href="{{ route('chair.timetable.substitutions') }}" class="btn btn-outline-secondary btn-sm ms-auto">Substitutions</a>
      <a href="{{ route('chair.timetable.availability') }}" class="btn btn-outline-secondary btn-sm">Availability</a>
    </div>
  </form>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Timetable Builder @if($selectedProgram)— <span class="text-muted fs-6">{{ $selectedProgram->name }}</span>@endif</h4>
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
              <small class="fw-normal opacity-75">{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</small>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($days as $dayNum => $dayName)
          <tr>
            <td class="fw-semibold bg-light">{{ $dayName }}</td>
            @foreach($slots as $slot)
              @php $entryKey = $dayNum.'-'.$slot->id; $entry = $entries[$entryKey] ?? null; @endphp
              <td class="{{ $slot->is_break ? 'bg-light' : '' }}">
                @if($slot->is_break)
                  <small class="text-muted">Break</small>
                @else
                  <button class="btn btn-sm {{ $entry ? 'btn-primary' : 'btn-outline-secondary' }} w-100"
                    data-day="{{ $dayNum }}" data-slot="{{ $slot->id }}"
                    data-entry="{{ $entry ? json_encode(['subject_id'=>$entry->subject_id,'teacher_id'=>$entry->teacher_id,'classroom_id'=>$entry->classroom_id]) : '' }}"
                    onclick="openSlotModal(this)">
                    @if($entry)
                      <div class="fw-semibold small">{{ $entry->subject->code ?? $entry->subject->name ?? '?' }}</div>
                      <div class="opacity-75 small">{{ $entry->teacher->user->name ?? '—' }}</div>
                      <div class="opacity-50 small">{{ $entry->classroom->name ?? '' }}</div>
                    @else
                      <span class="text-muted small">+ Assign</span>
                    @endif
                  </button>
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
        <h5 class="modal-title">Assign Slot — <span id="modal-label"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="conflict-alert" class="alert alert-danger d-none small mb-3"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Subject</label>
          <select id="m-subject" class="form-select" onchange="checkConflict()">
            <option value="">— Clear slot —</option>
            @foreach($programSubjects as $ps)
              <option value="{{ $ps->subject_id }}">{{ $ps->subject->name ?? $ps->subject_id }} ({{ $ps->subject->code ?? '' }})</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Teacher</label>
          <select id="m-teacher" class="form-select" onchange="checkConflict(); showAvailability()">
            <option value="">— None —</option>
            @foreach($teachers as $t)
              <option value="{{ $t->id }}">{{ $t->user->name ?? $t->id }}</option>
            @endforeach
          </select>
          <div id="avail-hint" class="form-text"></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Classroom</label>
          <select id="m-classroom" class="form-select" onchange="checkConflict()">
            <option value="">— None —</option>
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

let cDay, cSlot;
const modal = new bootstrap.Modal(document.getElementById('slotModal'));

function openSlotModal(btn) {
  cDay = btn.dataset.day; cSlot = btn.dataset.slot;
  document.getElementById('modal-label').textContent = (DAYS[cDay]||cDay);
  const e = btn.dataset.entry ? JSON.parse(btn.dataset.entry) : null;
  document.getElementById('m-subject').value   = e?.subject_id   || '';
  document.getElementById('m-teacher').value   = e?.teacher_id   || '';
  document.getElementById('m-classroom').value = e?.classroom_id || '';
  document.getElementById('conflict-alert').classList.add('d-none');
  document.getElementById('save-btn').disabled = false;
  showAvailability();
  modal.show();
}

function showAvailability() {
  const tid = document.getElementById('m-teacher').value;
  const key = tid + '-' + cDay + '-' + cSlot;
  const hint = document.getElementById('avail-hint');
  if (!tid) { hint.textContent = ''; return; }
  if (AVAIL[key] === 'unavailable') hint.innerHTML = '<span class="text-danger">⚠ Teacher marked unavailable for this slot</span>';
  else if (AVAIL[key] === 'preferred') hint.innerHTML = '<span class="text-success">★ Preferred slot for this teacher</span>';
  else hint.textContent = '';
}

let timer;
function checkConflict() {
  clearTimeout(timer);
  if (!document.getElementById('m-subject').value) return;
  timer = setTimeout(async () => {
    const res = await fetch(CHECK_URL, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      body: JSON.stringify({
        teacher_id: document.getElementById('m-teacher').value||null,
        classroom_id: document.getElementById('m-classroom').value||null,
        batch_id: BATCH_ID, day_of_week: cDay, timetable_slot_id: cSlot, term_id: TERM_ID
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
  const res = await fetch(SAVE_URL, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({
      program_id:PROGRAM_ID, term_id:TERM_ID, batch_id:BATCH_ID||undefined,
      day_of_week:cDay, timetable_slot_id:cSlot,
      subject_id:  clear?null:(document.getElementById('m-subject').value||null),
      teacher_id:  clear?null:(document.getElementById('m-teacher').value||null),
      classroom_id:clear?null:(document.getElementById('m-classroom').value||null),
    })
  });
  if (res.ok) { modal.hide(); location.reload(); }
  else {
    const d = await res.json();
    const el = document.getElementById('conflict-alert');
    el.textContent = (d.conflicts||['Save failed']).join(' | ');
    el.classList.remove('d-none');
  }
}
</script>
@endpush
