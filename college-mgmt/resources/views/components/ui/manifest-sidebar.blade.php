@props([
    'role',
    'brandText' => 'EduManage',
    'brandSub' => 'Portal',
    'brandIcon' => 'bi-grid-1x2-fill',
    'showBrand' => true,
    'showFooter' => true,
])

@php
    use App\Support\FrontendNavigation;
    use Illuminate\Support\Facades\Route;

    $manifest = FrontendNavigation::manifest();
    $roleConfig = $manifest[$role] ?? null;
    $landingRoute = $roleConfig['landing'] ?? null;
    $groups = $roleConfig['groups'] ?? [];
    $user = auth()->user();
    $student = $user?->student;
    $firstAdmissionProgram = \App\Models\Program::query()
        ->where('is_active', true)
        ->orderBy('id')
        ->value('id');
    $iconMap = [
        'dashboard' => 'bi-speedometer2',
        'command center' => 'bi-command',
        'workbench' => 'bi-kanban',
        'calling desk' => 'bi-telephone-outbound',
        'counsellor desk' => 'bi-person-workspace',
        'quick search' => 'bi-search',
        'department hierarchy' => 'bi-person-workspace',
        'department governance' => 'bi-sliders',
        'checklist' => 'bi-list-check',
        'application' => 'bi-file-earmark-text',
        'documents' => 'bi-folder2-open',
        'document queue' => 'bi-folder-check',
        'fees' => 'bi-cash-coin',
        'fee status' => 'bi-cash-coin',
        'payments' => 'bi-cash-coin',
        'payment queue' => 'bi-cash-coin',
        'submit payment' => 'bi-upload',
        'my timetable' => 'bi-calendar3',
        'mark attendance' => 'bi-check2-square',
        'enter marks' => 'bi-pencil-square',
        'study materials' => 'bi-folder2-open',
        'announcements' => 'bi-megaphone',
        'my students' => 'bi-people',
        'my mentees' => 'bi-person-hearts',
        'my feedback' => 'bi-star-half',
        'leave' => 'bi-calendar-x',
        'attendance' => 'bi-check2-square',
        'results' => 'bi-award',
        'admit cards' => 'bi-card-checklist',
        'subject registration' => 'bi-journal-text',
        'academic calendar' => 'bi-calendar-event',
        'leave applications' => 'bi-person-dash',
        'official transcript' => 'bi-file-earmark-text',
        'exam registration' => 'bi-clipboard-check',
        'marks appeals' => 'bi-megaphone',
        'my courses' => 'bi-book',
        'assignments' => 'bi-pencil-square',
        'quizzes' => 'bi-patch-question',
        'placements' => 'bi-briefcase',
        'scholarships' => 'bi-award',
        'my resume' => 'bi-person-vcard',
        'career events' => 'bi-calendar-event',
        'my internships' => 'bi-building',
        'alumni network' => 'bi-people',
        'status tracker' => 'bi-clipboard2-check',
        'assessment control' => 'bi-display',
        'assessment scheduling' => 'bi-calendar2-check',
        'committee board' => 'bi-clipboard2-check',
        'merit list' => 'bi-list-ol',
        'offer letters' => 'bi-envelope-open',
        'enrollments' => 'bi-person-check-fill',
        'seat control' => 'bi-ui-checks-grid',
        'handoff' => 'bi-arrow-left-right',
        'sessions' => 'bi-calendar-event',
        'all leads' => 'bi-funnel',
        'import leads' => 'bi-upload',
        'follow-up calendar' => 'bi-calendar3',
        'lead analytics' => 'bi-graph-up-arrow',
        'admission reports' => 'bi-bar-chart-line',
        'bulk communication' => 'bi-megaphone',
        'communication safety' => 'bi-shield-check',
        'consent & safety' => 'bi-shield-check',
        'integration health' => 'bi-activity',
        'refunds' => 'bi-arrow-counterclockwise',
        'my children' => 'bi-people',
        'notices' => 'bi-megaphone',
        'library' => 'bi-book-half',
        'grievances' => 'bi-chat-square-text',
        'my mentor' => 'bi-person-badge',
        'course feedback' => 'bi-star',
        'attendance condonation' => 'bi-shield-check',
        'document requests' => 'bi-file-earmark-text',
        'transport' => 'bi-bus-front',
        'outpass request' => 'bi-door-open',
        'hostel complaints' => 'bi-tools',
        'academic summary' => 'bi-card-text',
        'promotion status' => 'bi-arrow-up-circle',
        'my profile' => 'bi-person-circle',
        'notifications' => 'bi-bell',
        'fee collections' => 'bi-cash-coin',
        'admission payments' => 'bi-credit-card',
        'outstanding' => 'bi-exclamation-circle',
        'reconciliation' => 'bi-arrow-left-right',
        'reports' => 'bi-bar-chart-line',
        'placement drives' => 'bi-briefcase',
        'companies' => 'bi-building',
        'career events' => 'bi-calendar-event',
        'placement stats' => 'bi-bar-chart-line',
        'internships' => 'bi-laptop',
        'alumni database' => 'bi-people-fill',
        'analytics' => 'bi-pie-chart',
        'legacy dashboard' => 'bi-speedometer2',
        'academics command' => 'bi-display',
        'academics governance' => 'bi-diagram-3-fill',
        'hierarchy' => 'bi-diagram-2',
        'permission matrix' => 'bi-shield-lock',
        'planning' => 'bi-calendar-check',
        'reviews' => 'bi-journal-check',
        'approval cockpit' => 'bi-ui-checks',
        'faculty roster' => 'bi-people',
        'dept performance' => 'bi-graph-up',
        'student grievances' => 'bi-chat-square-text',
        'institutional kpi' => 'bi-speedometer2',
        'pmc operating' => 'bi-kanban',
        'pmc command' => 'bi-speedometer2',
        'pmc workspace' => 'bi-display',
        'semester readiness' => 'bi-clipboard2-check',
        'curriculum' => 'bi-journal-bookmark',
        'curriculum governance' => 'bi-journal-check',
        'course allocation' => 'bi-list-check',
        'section builder' => 'bi-diagram-3',
        'timetable planner' => 'bi-grid-3x3-gap',
        'timetable builder' => 'bi-grid-3x3',
        'at-risk students' => 'bi-exclamation-triangle',
        'leave approvals' => 'bi-calendar-x',
        'condonations' => 'bi-shield-check',
        'student success' => 'bi-person-check',
        'faculty workload' => 'bi-person-badge',
        'faculty allocation' => 'bi-person-lines-fill',
        'approval cockpit' => 'bi-ui-checks',
        'subject performance' => 'bi-bar-chart-line',
        'coe operating' => 'bi-clipboard2-data',
        'coe os' => 'bi-clipboard2-data',
        'exam cell dashboard' => 'bi-speedometer2',
        'coe workspace' => 'bi-display',
        'iqac operating' => 'bi-shield-check',
        'iqac os' => 'bi-shield-check',
        'iqac workspace' => 'bi-display',
        'obe readiness' => 'bi-clipboard2-check',
        'attainment' => 'bi-graph-up',
        'feedback quality' => 'bi-star-half',
        'audit compliance' => 'bi-clipboard-check',
        'program leadership' => 'bi-mortarboard',
        'program workspace' => 'bi-display',
        'portfolio' => 'bi-briefcase',
        'student monitoring' => 'bi-exclamation-triangle',
        'quality signals' => 'bi-activity',
        'program reports' => 'bi-bar-chart-line',
        'course delivery' => 'bi-journal-check',
        'academics overview' => 'bi-mortarboard-fill',
        'programs' => 'bi-mortarboard',
        'curriculum changes' => 'bi-journal-text',
        'obe framework' => 'bi-diagram-3',
        'exams' => 'bi-file-earmark-text',
        'transcripts' => 'bi-file-earmark-text',
        'hall tickets' => 'bi-ticket-perforated',
        'all exams' => 'bi-file-earmark-text',
        'schedule exam' => 'bi-plus-circle',
        'marks appeals' => 'bi-envelope-exclamation',
        'legacy transcripts' => 'bi-file-earmark-text',
        'hall ticket admin' => 'bi-ticket-perforated',
        'anomaly log' => 'bi-exclamation-triangle',
        'approvals' => 'bi-check2-circle',
        'dean reports' => 'bi-file-earmark-bar-graph',
        'program risk' => 'bi-grid-3x3-gap',
        'institution analytics' => 'bi-graph-up-arrow',
        'aicte report' => 'bi-file-earmark-bar-graph',
        'policy audit' => 'bi-shield-lock',
        'account settings' => 'bi-gear',
    ];

    $conditionVisible = function (array $item) use ($student, $user, $firstAdmissionProgram): bool {
        if (($item['condition'] ?? null) === 'student_has_issued_transcript') {
            return $student
                ? \App\Models\AcademicTranscript::where('student_id', $student->id)
                    ->where('status', 'issued')
                    ->whereNotNull('semester_data')
                    ->exists()
                : false;
        }

        if (($item['condition'] ?? null) === 'admission_governance') {
            return $user
                ? app(\App\Services\DepartmentHierarchyService::class)->canAccessDepartmentGovernance($user, 'ADM')
                : false;
        }

        if (($item['condition'] ?? null) === 'admission_handoff_access') {
            return $user
                ? app(\App\Services\AdmissionAccessPolicyService::class)->can($user, 'read.handoff')
                : false;
        }

        if (($item['condition'] ?? null) === 'admission_first_program') {
            return (bool) $firstAdmissionProgram;
        }

        return true;
    };
@endphp

@if($roleConfig)
    @if($showBrand && $landingRoute && Route::has($landingRoute))
        <a class="sidebar-brand" href="{{ route($landingRoute) }}">
            <span class="brand-icon"><i class="bi {{ $brandIcon }}"></i></span>
            <span>
                <span class="brand-text d-block">{{ $brandText }}</span>
                <span class="brand-sub d-block">{{ $brandSub }}</span>
            </span>
        </a>
    @endif

    <div class="mt-2 pb-4 flex-grow-1">
        @foreach($groups as $group => $items)
            <div class="section-label">{{ $group }}</div>

            @foreach($items as $item)
                @continue(! Route::has($item['route']))
                @continue(! $conditionVisible($item))
                @php
                    $labelKey = strtolower($item['label']);
                    $icon = $item['icon'] ?? ($iconMap[$labelKey] ?? 'bi-circle');
                    $activePatterns = (array) ($item['active'] ?? $item['route']);
                    $active = collect($activePatterns)->contains(fn (string $pattern) => request()->routeIs($pattern));
                    $routeParams = $item['params'] ?? [];
                    if (($item['paramsFrom'] ?? null) === 'first_admission_program') {
                        $routeParams = $firstAdmissionProgram ? [$firstAdmissionProgram] : [];
                    }
                @endphp
                <a href="{{ route($item['route'], $routeParams) }}" class="nav-link @if($active) active @endif">
                    <i class="bi {{ $icon }}"></i> {{ $item['label'] }}
                </a>
            @endforeach

            @if(! $loop->last)
                <div class="sidebar-divider"></div>
            @endif
        @endforeach
    </div>

    @if($showFooter)
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </button>
            </form>
        </div>
    @endif
@endif
