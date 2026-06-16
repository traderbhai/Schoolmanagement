<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionSavedViewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedViewController extends Controller
{
    public function index(AdmissionSavedViewService $service, Request $request)
    {
        $surfaces = $this->surfaces();
        $selectedSurface = $request->query('surface', 'assessment_control_room');

        return view('admission.v0037.saved-views', [
            'views' => $service->forSurface($selectedSurface, $request->user()),
            'surfaces' => $surfaces,
            'selectedSurface' => $selectedSurface,
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function store(Request $request, AdmissionSavedViewService $service)
    {
        $data = $request->validate([
            'surface' => ['required', 'string', Rule::in(array_keys($this->surfaces()))],
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'filters.status' => ['nullable', 'string', 'max:80'],
            'filters.priority' => ['nullable', 'string', 'max:80'],
            'filters.owner_scope' => ['nullable', 'string', 'max:80'],
            'filters.date_range' => ['nullable', 'string', 'max:80'],
            'filters.sort' => ['nullable', 'string', 'max:80'],
            'filters_json' => ['nullable', 'json'],
        ]);

        $jsonFilters = json_decode($data['filters_json'] ?? '{}', true) ?: [];
        $structuredFilters = collect($data['filters'] ?? [])
            ->filter(fn ($value) => filled($value))
            ->all();

        $service->save($data['surface'], $data['name'], array_merge($jsonFilters, $structuredFilters), $request->user());

        return back()->with('success', 'Saved view stored.');
    }

    private function surfaces(): array
    {
        return [
            'assessment_control_room' => 'Assessment Control Room',
            'counsellor_desk' => 'Counsellor Desk',
            'calling_desk' => 'Calling Desk',
            'command_center' => 'Command Center',
            'communication_safety' => 'Communication Safety',
            'offer_seat_control' => 'Offer And Seat Control',
            'automation_logs' => 'Automation Logs',
            'schedule_conflicts' => 'Schedule Conflicts',
            'counsellor_performance' => 'Counsellor Performance',
            'handoff_queue' => 'Admission Handoff Queue',
            'assessment_day' => 'Assessment Day Desk',
        ];
    }

    private function filterOptions(): array
    {
        return [
            'status' => [
                '' => 'Any status',
                'pending' => 'Pending',
                'open' => 'Open',
                'overdue' => 'Overdue',
                'blocked' => 'Blocked',
                'ready' => 'Ready',
                'completed' => 'Completed',
            ],
            'priority' => [
                '' => 'Any priority',
                'critical' => 'Critical',
                'high' => 'High',
                'normal' => 'Normal',
                'low' => 'Low',
            ],
            'owner_scope' => [
                '' => 'Any owner scope',
                'mine' => 'My records',
                'team' => 'My team',
                'department' => 'Full department',
                'unassigned' => 'Unassigned',
            ],
            'date_range' => [
                '' => 'Any date',
                'today' => 'Today',
                'this_week' => 'This week',
                'next_7_days' => 'Next 7 days',
                'overdue' => 'Overdue',
            ],
            'sort' => [
                'due_soon' => 'Due soon',
                'severity_desc' => 'Severity high to low',
                'latest' => 'Latest activity',
                'oldest' => 'Oldest first',
            ],
        ];
    }
}
