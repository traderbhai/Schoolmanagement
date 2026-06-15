<div class="d-flex flex-wrap gap-1 justify-content-end">
    @foreach([
        ['OS', 'academics.pmc.timetable-os.index'],
        ['Allocation', 'academics.pmc.course-allocation.index'],
        ['Groups', 'academics.pmc.course-groups.index'],
        ['Faculty', 'academics.pmc.section-faculty-allocation.index'],
        ['Locks', 'academics.pmc.locked-slots.index'],
        ['Generate', 'academics.pmc.timetable-generator.index'],
        ['Planner', 'academics.pmc.timetable-planner.index'],
        ['Freeze', 'academics.pmc.timetable-versions-v041.index'],
        ['Substitute', 'academics.pmc.substitution-intelligence.index'],
        ['Reconcile', 'academics.pmc.data-reconciliation.index'],
        ['Reports', 'academics.pmc.timetable-reports.index'],
    ] as [$label, $route])
        <a class="btn btn-sm {{ request()->routeIs($route) ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route($route) }}">{{ $label }}</a>
    @endforeach
</div>
