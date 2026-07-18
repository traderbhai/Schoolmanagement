@extends('layouts.student')
@section('title', 'Academic Calendar')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Academic Calendar</h4>
        <div class="d-flex gap-2 align-items-center">
            @php
                $prev = $startOfMonth->copy()->subMonth();
                $next = $startOfMonth->copy()->addMonth();
            @endphp
            <a href="{{ route('student.calendar.index', ['month'=>$prev->month, 'year'=>$prev->year]) }}" class="btn btn-outline-secondary btn-sm">‹</a>
            <span class="fw-semibold">{{ $startOfMonth->format('F Y') }}</span>
            <a href="{{ route('student.calendar.index', ['month'=>$next->month, 'year'=>$next->year]) }}" class="btn btn-outline-secondary btn-sm">›</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                                <th scope="col" class="py-2">{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $firstDayOfWeek = $startOfMonth->dayOfWeek;
                                $daysInMonth = $startOfMonth->daysInMonth;
                                $day = 1;
                            @endphp
                            @for($row = 0; $row < 6; $row++)
                            <tr>
                                @for($col = 0; $col < 7; $col++)
                                @php $cellIndex = $row * 7 + $col; @endphp
                                @if($cellIndex < $firstDayOfWeek || $day > $daysInMonth)
                                <td class="bg-light" style="height:60px"></td>
                                @else
                                @php
                                    $dateStr = $startOfMonth->copy()->setDay($day)->format('Y-m-d');
                                    $dayEvents = $eventsByDate[$dateStr] ?? [];
                                    $isToday = $dateStr === now()->format('Y-m-d');
                                @endphp
                                <td class="{{ $isToday ? 'bg-primary-subtle' : '' }}" style="height:60px; vertical-align:top; padding:4px">
                                    <div class="{{ $isToday ? 'fw-bold text-primary' : '' }} mb-1" style="font-size:.85rem">{{ $day }}</div>
                                    @foreach(array_slice($dayEvents, 0, 2) as $event)
                                    @php
                                        $typeColors = ['holiday'=>'danger','exam'=>'warning','event'=>'success','other'=>'secondary'];
                                        $color = $typeColors[$event->type] ?? 'info';
                                    @endphp
                                    <div class="badge bg-{{ $color }}-subtle text-{{ $color }} w-100 text-truncate mb-1" style="font-size:.65rem" title="{{ $event->title }}">{{ Str::limit($event->title,15) }}</div>
                                    @endforeach
                                </td>
                                @php $day++; @endphp
                                @endif
                                @endfor
                            </tr>
                            @if($day > $daysInMonth) @break @endif
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">Events this month</div>
                <div class="card-body p-0" style="max-height:450px;overflow-y:auto">
                    @forelse($events as $event)
                    @php $typeColors = ['holiday'=>'danger','exam'=>'warning','event'=>'success','other'=>'secondary','workshop'=>'info','seminar'=>'purple']; @endphp
                    <div class="px-3 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="fw-semibold small">{{ $event->title }}</span>
                            <span class="badge bg-{{ $typeColors[$event->type] ?? 'secondary' }}-subtle text-{{ $typeColors[$event->type] ?? 'secondary' }}">{{ ucfirst($event->type) }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.78rem">
                            {{ $event->start_date->format('d M') }}{{ $event->end_date && $event->end_date != $event->start_date ? ' – '.$event->end_date->format('d M') : '' }}
                            @if($event->location) · {{ $event->location }} @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted p-3 mb-0">No events this month.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
