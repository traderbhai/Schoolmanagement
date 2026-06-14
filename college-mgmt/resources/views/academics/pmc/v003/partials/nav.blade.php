<div class="btn-group btn-group-sm flex-wrap">
    <a href="{{ route('academics.pmc.command') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.command')) active @endif">Command</a>
    <a href="{{ route('academics.pmc.workbench') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.workbench')) active @endif">Workbench</a>
    <a href="{{ route('academics.pmc.curriculum-governance') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.curriculum-governance')) active @endif">Curriculum</a>
    <a href="{{ route('academics.pmc.faculty-workload') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.faculty-workload')) active @endif">Faculty</a>
    <a href="{{ route('academics.pmc.timetable-control') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.timetable-control')) active @endif">Timetable</a>
    <a href="{{ route('academics.pmc.student-success') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.student-success')) active @endif">Students</a>
    <a href="{{ route('academics.pmc.reviews') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.reviews')) active @endif">Reviews</a>
    <a href="{{ route('academics.pmc.reports') }}" class="btn btn-outline-primary @if(request()->routeIs('academics.pmc.reports')) active @endif">Reports</a>
</div>
