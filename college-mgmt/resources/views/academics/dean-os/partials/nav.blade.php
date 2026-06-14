<div class="btn-group btn-group-sm flex-wrap">
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.index')) active @endif" href="{{ route('academics.dean-os.index') }}">Command</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.branch-health')) active @endif" href="{{ route('academics.dean-os.branch-health') }}">Branch Health</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.program-risk')) active @endif" href="{{ route('academics.dean-os.program-risk') }}">Program Risk</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.reviews')) active @endif" href="{{ route('academics.dean-os.reviews') }}">Reviews</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.handoff')) active @endif" href="{{ route('academics.dean-os.handoff') }}">Handoff</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.calendar')) active @endif" href="{{ route('academics.dean-os.calendar') }}">Calendar</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.reports')) active @endif" href="{{ route('academics.dean-os.reports') }}">Reports</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.planning.*')) active @endif" href="{{ route('academics.dean-os.planning.index') }}">Planning</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.approval-cockpit.*')) active @endif" href="{{ route('academics.dean-os.approval-cockpit.index') }}">Approvals</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.student-success.*')) active @endif" href="{{ route('academics.dean-os.student-success.index') }}">Student Success</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.exam-readiness.*')) active @endif" href="{{ route('academics.dean-os.exam-readiness.index') }}">Exam Readiness</a>
    <a class="btn btn-outline-primary @if(request()->routeIs('academics.dean-os.analytics.*')) active @endif" href="{{ route('academics.dean-os.analytics.index') }}">Analytics</a>
</div>
