@extends('layouts.admin')
@section('title', 'Teacher Availability')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Teacher Availability</h4>
    <a href="{{ route('chair.timetable.builder') }}" class="btn btn-outline-secondary btn-sm">← Builder</a>
  </div>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <form method="GET" class="mb-4">
    <div class="row g-2">
      <div class="col-md-4">
        <select aria-label="Teacher" name="teacher_id" class="form-select" onchange="this.form.submit()">
          @foreach($teachers as $t)
            <option value="{{ $t->id }}" @selected($selectedTeacher?->id == $t->id)>{{ $t->user->name ?? $t->id }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </form>

  @if($selectedTeacher && $currentTerm)
    <form method="POST" action="{{ route('chair.timetable.availability.save') }}">
      @csrf
      <input type="hidden" name="teacher_id" value="{{ $selectedTeacher->id }}">
      <input type="hidden" name="term_id" value="{{ $currentTerm->id }}">

      <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between">
          <span>{{ $selectedTeacher->user->name ?? '' }} — {{ $currentTerm->name }}</span>
          <div class="d-flex gap-3 small align-items-center">
            <span><span class="badge bg-success">●</span> Available</span>
            <span><span class="badge bg-warning text-dark">●</span> Preferred</span>
            <span><span class="badge bg-danger">●</span> Unavailable</span>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered text-center mb-0">
              <thead class="table-dark">
                <tr>
                  <th scope="col">Day</th>
                  @foreach($slots as $slot)
                    <th scope="col" class="{{ $slot->is_break ? 'table-secondary' : '' }}">
                      {{ $slot->name }}<br>
                      <small class="fw-normal opacity-75">{{ substr($slot->start_time,0,5) }}</small>
                    </th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach($days as $dayNum => $dayName)
                  <tr>
                    <td class="fw-semibold bg-light">{{ $dayName }}</td>
                    @foreach($slots as $slot)
                      @php $key = $dayNum.'-'.$slot->id; $avail = $availability[$key]->availability ?? 'available'; @endphp
                      <td class="{{ $slot->is_break ? 'bg-light' : '' }}">
                        @if($slot->is_break)
                          <small class="text-muted">—</small>
                        @else
                          <select aria-label="Availability for {{ $dayName }} {{ $slot->name }}" name="availability[{{ $dayNum }}-{{ $slot->id }}]"
                            class="form-select form-select-sm border-0 bg-transparent avail-select"
                            data-val="{{ $avail }}" onchange="colorSelect(this)">
                            <option value="available"   @selected($avail==='available')>Available</option>
                            <option value="preferred"   @selected($avail==='preferred')>Preferred</option>
                            <option value="unavailable" @selected($avail==='unavailable')>Unavailable</option>
                          </select>
                        @endif
                      </td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary">Save Availability</button>
        </div>
      </div>
    </form>
  @else
    <div class="text-center text-muted py-5">Select a teacher to manage availability.</div>
  @endif
</div>
@endsection

@push('scripts')
<script>
function colorSelect(sel) {
  sel.classList.remove('text-success','text-warning','text-danger','fw-semibold');
  const v = sel.value;
  if (v==='preferred')   { sel.classList.add('text-warning','fw-semibold'); }
  if (v==='unavailable') { sel.classList.add('text-danger','fw-semibold'); }
}
document.querySelectorAll('.avail-select').forEach(colorSelect);
</script>
@endpush
