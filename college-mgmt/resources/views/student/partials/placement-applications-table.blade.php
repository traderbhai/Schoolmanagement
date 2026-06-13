@if($myApplications->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            You have not applied to any placement drives yet.
        </div>
    </div>
@else
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Drive / Company</th>
                            <th>Job Role</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Next Step</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myApplications as $app)
                        @php
                            $statusBadge = [
                                'applied'     => 'bg-info',
                                'shortlisted' => 'bg-primary',
                                'interview'   => 'bg-warning text-dark',
                                'selected'    => 'bg-success',
                                'rejected'    => 'bg-danger',
                                'withdrawn'   => 'bg-secondary',
                            ];
                            $nextStep = [
                                'applied'     => 'Wait for CMC shortlisting update.',
                                'shortlisted' => 'Prepare for the next placement round.',
                                'interview'   => 'Watch for interview schedule and instructions.',
                                'selected'    => 'Wait for offer letter and joining guidance.',
                                'rejected'    => 'Review future drives and keep applying.',
                                'withdrawn'   => 'This application was withdrawn.',
                            ][$app->application_status] ?? 'Track updates from CMC.';
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $app->drive->title ?? 'Placement drive' }}</div>
                                <div class="text-muted" style="font-size:.78rem">{{ $app->drive?->company?->name ?? 'Company pending' }}</div>
                            </td>
                            <td>{{ $app->drive->job_role ?? '-' }}</td>
                            <td>{{ $app->offered_package ? number_format((float) $app->offered_package, 1) . ' LPA' : ($app->drive->package ?? '-') }}</td>
                            <td>
                                <span class="badge {{ $statusBadge[$app->application_status] ?? 'bg-secondary' }}">
                                    {{ ucfirst($app->application_status) }}
                                </span>
                            </td>
                            <td>{{ $app->created_at->format('d M Y') }}</td>
                            <td class="small text-muted">{{ $nextStep }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
