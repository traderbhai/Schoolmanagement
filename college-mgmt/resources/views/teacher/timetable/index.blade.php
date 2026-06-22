@extends('layouts.teacher')

@section('title', 'My Timetable')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="bi bi-calendar3 me-2 text-primary"></i>
            My Timetable
            @if($currentTerm)
                <span class="text-muted fs-5 fw-normal ms-2">- {{ $currentTerm->name }}</span>
            @endif
        </h4>
    </div>

    @if(!empty($profileMissing))
        <div class="alert alert-warning">
            <strong>Teacher profile not linked.</strong>
            Your timetable cannot be loaded until administration links your login to a teacher profile.
        </div>
    @elseif($entries->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">
            <div class="fw-semibold">No published teaching timetable is assigned to you yet.</div>
            <div class="small mb-0">PMC or the academic office must publish your subject allocation in the official timetable before classes appear here. Draft timetable entries and unpublished timetable versions stay hidden from teacher self-service.</div>
        </div>
    @endif

    @if($todaySubstitutions->isNotEmpty())
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-arrow-left-right me-1"></i> Substitutions Today:</strong>
            <ul class="mb-0 mt-1">
                @foreach($todaySubstitutions as $sub)
                    <li>
                        Today: <strong>{{ $sub['subject'] ?? 'Subject not linked' }}</strong>
                        @if(!empty($sub['group']))
                            <span class="text-muted small">({{ $sub['group'] }})</span>
                        @endif
                        - {{ $sub['action'] ?? 'Updated' }}
                        <span class="badge bg-secondary ms-1">{{ $sub['source'] ?? 'Timetable' }}</span>
                        @if(!empty($sub['reason']))
                            <span class="text-muted small">({{ $sub['reason'] }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="min-width:90px;">Day</th>
                            @foreach($slots as $slot)
                                <th class="text-center {{ $slot->is_break ? 'bg-secondary bg-opacity-75' : '' }}" style="min-width:120px;">
                                    <div class="fw-semibold small">{{ $slot->name }}</div>
                                    <div class="fw-normal" style="font-size:0.72rem;">
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}
                                        -
                                        {{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($days as $dayNum => $dayName)
                            <tr>
                                <td class="fw-semibold text-nowrap text-center">{{ $dayName }}</td>
                                @foreach($slots as $slot)
                                    @php
                                        $key   = $dayNum . '-' . $slot->id;
                                        $entry = $entries->get($key);
                                    @endphp

                                    @if($slot->is_break)
                                        <td class="text-center bg-light text-muted small">
                                            <i class="bi bi-cup-hot"></i> Break
                                        </td>
                                    @elseif($entry)
                                        <td class="text-center py-2">
                                            <div class="fw-bold text-primary" style="font-size:0.8rem;">
                                                {{ $entry->subject?->code ?? $entry->courseGroup?->subject?->code ?? 'Subject code pending' }}
                                            </div>
                                            <div class="small">{{ $entry->subject?->name ?? $entry->courseGroup?->subject?->name ?? 'Subject not linked' }}</div>
                                            <div class="text-muted" style="font-size:0.72rem;">
                                                {{ $entry->courseGroup?->name ?? $entry->batch?->name ?? 'Batch not linked' }}
                                            </div>
                                            <div class="text-muted" style="font-size:0.72rem;">
                                                <i class="bi bi-door-open"></i>
                                                {{ $entry->classroom->name ?? 'Room not assigned' }}
                                            </div>
                                        </td>
                                    @else
                                        <td class="text-center bg-light text-muted small">Free</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Weekly load summary --}}
    @php
        $breakSlotIds   = $slots->where('is_break', true)->pluck('id')->map(fn($id) => (string)$id)->toArray();
        $nonBreakCount  = $entries->filter(function($entry, $key) use ($breakSlotIds) {
            $parts  = explode('-', $key, 2);
            $slotId = $parts[1] ?? '';
            return !in_array($slotId, $breakSlotIds);
        })->count();

        $distinctSubjects = $entries->pluck('subject.name')->unique()->filter()->count();

        // Compute total teaching minutes
        $nonBreakSlots = $slots->where('is_break', false);
        $avgSlotMins = $nonBreakSlots->map(function($slot) {
            return \Carbon\Carbon::parse($slot->start_time)->diffInMinutes(\Carbon\Carbon::parse($slot->end_time));
        })->avg() ?? 0;
        $totalMins = (int) round($nonBreakCount * $avgSlotMins);
        $hrs  = intdiv($totalMins, 60);
        $mins = $totalMins % 60;
    @endphp

    <div class="row mt-3 g-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="fs-3 text-primary"><i class="bi bi-layers"></i></div>
                    <div>
                        <div class="fw-bold fs-5">{{ $nonBreakCount }}</div>
                        <div class="text-muted small">Teaching Slots / Week</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="fs-3 text-success"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="fw-bold fs-5">{{ $hrs }}h {{ $mins }}m</div>
                        <div class="text-muted small">Teaching Hours / Week</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-warning bg-opacity-10">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="fs-3 text-warning"><i class="bi bi-book"></i></div>
                    <div>
                        <div class="fw-bold fs-5">{{ $distinctSubjects }}</div>
                        <div class="text-muted small">Distinct Subjects</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
