<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; }
  .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 10px; margin-bottom: 14px; }
  .header h1 { margin: 0 0 4px; font-size: 18px; color: #1a56db; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1a56db; color: #fff; padding: 7px 8px; font-size: 10px; text-align: center; }
  td { padding: 5px 6px; border: 1px solid #d1d9e6; font-size: 9.5px; vertical-align: top; }
  .slot-name { font-weight: bold; text-align: center; }
  .slot-time { font-size: 8.5px; color: #555; text-align: center; }
  .break-row td { background: #fef9c3; text-align: center; font-style: italic; color: #854d0e; }
  .cell-subj { font-weight: bold; color: #1a56db; }
  .cell-tchr { color: #374151; }
  .cell-room { font-size: 8.5px; color: #666; }
  .footer { text-align: center; font-size: 9px; color: #888; border-top: 1px solid #dde4f0; padding-top: 8px; margin-top: 12px; }
</style>
</head>
<body>

<div class="header">
  <h1>College Management System</h1>
  <p>Weekly Timetable — {{ $semester->name }}</p>
</div>

<table>
  <thead>
    <tr>
      <th style="width:12%">Slot</th>
      @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d)
      <th>{{ $d }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
  @foreach($slots as $slot)
    @if($slot->is_break)
    <tr class="break-row">
      <td class="slot-name">{{ $slot->name }}</td>
      <td colspan="6">{{ $slot->start_time }} – {{ $slot->end_time }}</td>
    </tr>
    @else
    <tr>
      <td>
        <div class="slot-name">{{ $slot->name }}</div>
        <div class="slot-time">{{ $slot->start_time }}-{{ $slot->end_time }}</div>
      </td>
      @for($day=1;$day<=6;$day++)
      <td>
        @if(isset($grid[$day][$slot->id]))
          @foreach($grid[$day][$slot->id] as $entry)
            <div style="margin-bottom:5px;">
              <div class="cell-subj">
                {{ $entry->subject?->name ?? 'Subject' }}
                @if(($entry->duration_slots ?? 1) > 1)
                  ({{ $entry->duration_slots }} slots)
                @endif
              </div>
              @if($entry->course_group)
                <div class="cell-room">{{ $entry->course_group->name }}</div>
              @endif
              @if($entry->is_continuation ?? false)
                <div class="cell-room">Continued session</div>
              @endif
              <div class="cell-tchr">{{ $entry->teacher?->user?->name ?? '' }}</div>
              <div class="cell-room">{{ $entry->classroom?->room_number ?? 'Room TBA' }}</div>
            </div>
          @endforeach
        @endif
      </td>
      @endfor
    </tr>
    @endif
  @endforeach
  </tbody>
</table>

<div class="footer">
  Generated on {{ now()->format('d M Y, h:i A') }}
</div>

</body>
</html>
