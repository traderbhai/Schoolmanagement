@extends('layouts.admin')
@section('title', 'CMC Dashboard')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Placement / CMC</h4>
            <span class="text-muted small">Placement drives, internships, career services</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('cmc.drives.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Drive
            </a>
            <a href="{{ route('cmc.events.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar-plus me-1"></i> New Event
            </a>
            <a href="{{ route('cmc.analytics') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bar-chart me-1"></i> Analytics
            </a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">CMC operating sequence</div>
                <div class="small text-muted">Use this dashboard to run placement operations from drive setup to student applications, events, analytics, and employer follow-up.</div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Check active drives</span>
                <span class="badge text-bg-light">2. Create drive/event</span>
                <span class="badge text-bg-light">3. Track applications</span>
                <span class="badge text-bg-light">4. Review placement rate</span>
                <span class="badge text-bg-light">5. Open analytics</span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">CMC Priority</div>
                <h5 class="fw-bold mb-1">{{ $cmcPriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $cmcPriority['body'] }}</p>
            </div>
            <a href="{{ $cmcPriority['route'] }}" class="btn btn-sm {{ $cmcPriority['level'] === 'danger' ? 'btn-danger' : ($cmcPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
                <i class="bi bi-arrow-right-circle me-1"></i>{{ $cmcPriority['action'] }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-ui.kpi-card href="{{ route('cmc.drives', ['status' => 'active']) }}" tone="blue" icon="bi-briefcase-fill" :value="$activeDrives" label="Active Drives" trend="Open active drives" :trend-icon="$activeDrives > 0 ? 'bi-arrow-up' : 'bi-arrow-right'" :trend-tone="$activeDrives > 0 ? 'up' : null" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.kpi-card href="{{ route('cmc.placements') }}" tone="green" icon="bi-trophy-fill" :value="$totalPlacements" label="Total Placed" trend="Open selected placements" trend-icon="bi-arrow-up" trend-tone="up" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.kpi-card href="{{ route('cmc.analytics') }}" tone="cyan" icon="bi-people-fill" :value="$totalStudents" label="Total Students" trend="Open placement analytics" trend-icon="bi-mortarboard" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.kpi-card href="{{ route('cmc.analytics') }}" :tone="$placementRate >= 70 ? 'green' : ($placementRate >= 40 ? 'amber' : 'red')" icon="bi-percent" value="{{ $placementRate }}%" label="Placement Rate" trend="Open analytics" :trend-icon="$placementRate >= 70 ? 'bi-arrow-up' : 'bi-arrow-down'" :trend-tone="$placementRate >= 70 ? 'up' : 'down'" />
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0">Recent Placement Drives</h6>
                    <a href="{{ route('cmc.drives') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Company</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentDrives as $drive)
                                @php
                                    $statusColors = ['upcoming'=>'success','ongoing'=>'primary','completed'=>'info','cancelled'=>'secondary'];
                                    $status = $drive->status ?? 'upcoming';
                                @endphp
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $drive->company->name ?? $drive->title ?? '-' }}</td>
                                    <td class="small text-muted">{{ $drive->job_role ?? 'Placement' }}</td>
                                    <td><span class="badge bg-{{ $statusColors[$status] ?? 'secondary' }}-subtle text-{{ $statusColors[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span></td>
                                    <td class="small text-muted">{{ $drive->drive_date?->format('d M Y') ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No drives yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-calendar-event me-2 text-primary"></i>Upcoming Career Events
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                    @forelse($upcomingEvents ?? [] as $event)
                        <li class="list-group-item py-2 px-3">
                            <div class="fw-semibold small">{{ $event->title }}</div>
                            <div class="text-muted" style="font-size:.75rem">
                                <i class="bi bi-calendar3 me-1"></i>{{ $event->event_date ? $event->event_date->format('d M Y') : '-' }}
                                @if($event->event_type ?? null)
                                    - <span class="badge bg-primary-subtle text-primary">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-3 small">No upcoming events.</li>
                    @endforelse
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="{{ route('cmc.drives.create') }}" class="btn btn-sm btn-primary text-start">
                        <i class="bi bi-briefcase me-2"></i>New Placement Drive
                    </a>
                    <a href="{{ route('cmc.events.create') }}" class="btn btn-sm btn-outline-primary text-start">
                        <i class="bi bi-calendar-plus me-2"></i>New Career Event
                    </a>
                    <a href="{{ route('cmc.companies') }}" class="btn btn-sm btn-outline-cyan text-start">
                        <i class="bi bi-building me-2"></i>Manage Companies
                    </a>
                    <a href="{{ route('cmc.analytics') }}" class="btn btn-sm btn-outline-secondary text-start">
                        <i class="bi bi-bar-chart-line me-2"></i>Placement Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
