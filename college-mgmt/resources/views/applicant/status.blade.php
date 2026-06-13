@extends('layouts.applicant')
@section('title', 'Application Status')
@section('page-title', 'Status Tracker')

@section('content')
<div class="container-fluid p-4">

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body text-center p-5">
                    <h4 class="fw-bold mb-1">{{ $applicant->application_number }}</h4>
                    <p class="text-muted mb-3">{{ $applicant->program->name }}{{ $applicant->batch ? ' — ' . $applicant->batch->name : '' }}</p>
                    <span class="{{ $applicant->status_badge }} fs-5 px-4 py-2">{{ $applicant->status_label }}</span>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Application Timeline</h5></div>
                <div class="card-body p-4">
                    @php
                        $steps = [
                            ['key' => 'draft',        'label' => 'Application Started',    'icon' => 'bi-file-earmark-plus'],
                            ['key' => 'submitted',    'label' => 'Application Submitted',  'icon' => 'bi-send-fill'],
                            ['key' => 'under_review', 'label' => 'Under Review',           'icon' => 'bi-search'],
                            ['key' => 'shortlisted',  'label' => 'Shortlisted',            'icon' => 'bi-star-fill'],
                            ['key' => 'selected',     'label' => 'Selected',               'icon' => 'bi-trophy-fill'],
                        ];
                        $statusOrder = ['draft' => 0, 'submitted' => 1, 'under_review' => 2, 'shortlisted' => 3, 'selected' => 4, 'rejected' => 2, 'withdrawn' => 1];
                        $currentOrder = $statusOrder[$applicant->status] ?? 0;
                    @endphp

                    @if(in_array($applicant->status, ['rejected', 'withdrawn']))
                    <div class="alert alert-{{ $applicant->status === 'rejected' ? 'danger' : 'dark' }} mb-4">
                        <i class="bi bi-{{ $applicant->status === 'rejected' ? 'x-circle' : 'dash-circle' }}-fill me-2"></i>
                        Your application was <strong>{{ $applicant->status_label }}</strong>.
                        @if($applicant->reviewed_at)
                            on {{ $applicant->reviewed_at->format('d M Y') }}
                        @endif
                    </div>
                    @endif

                    <div class="position-relative">
                        @foreach($steps as $i => $step)
                            @php
                                $stepOrder = $statusOrder[$step['key']] ?? $i;
                                $isCompleted = $currentOrder > $stepOrder;
                                $isCurrent = $currentOrder === $stepOrder && !in_array($applicant->status, ['rejected','withdrawn']);
                                $isPending = $currentOrder < $stepOrder;
                            @endphp
                            <div class="d-flex align-items-start mb-{{ $i < count($steps) - 1 ? '4' : '0' }} position-relative">
                                <div class="me-3 text-center" style="min-width: 40px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto
                                        {{ $isCompleted ? 'bg-success text-white' : ($isCurrent ? 'bg-primary text-white' : 'bg-light text-muted border') }}"
                                        style="width: 40px; height: 40px;">
                                        <i class="bi {{ $isCompleted ? 'bi-check-lg' : $step['icon'] }}"></i>
                                    </div>
                                    @if($i < count($steps) - 1)
                                        <div class="border-start border-2 {{ $isCompleted ? 'border-success' : 'border-light' }} mx-auto" style="height: 30px; width: 2px; margin-top: 4px;"></div>
                                    @endif
                                </div>
                                <div class="pt-1">
                                    <p class="fw-semibold mb-0 {{ $isPending ? 'text-muted' : '' }}">{{ $step['label'] }}</p>
                                    @if($isCurrent)
                                        <span class="badge bg-primary-subtle text-primary small">Current Status</span>
                                    @elseif($isCompleted)
                                        <span class="text-success small"><i class="bi bi-check me-1"></i>Completed</span>
                                    @else
                                        <span class="text-muted small">Pending</span>
                                    @endif

                                    @if($step['key'] === 'submitted' && $applicant->applied_at)
                                        <p class="text-muted small mb-0 mt-1">{{ $applicant->applied_at?->format('d M Y, h:i A') ?? '—' }}</p>
                                    @elseif($step['key'] === 'shortlisted' && $applicant->reviewed_at && in_array($applicant->status, ['shortlisted', 'selected']))
                                        <p class="text-muted small mb-0 mt-1">{{ $applicant->reviewed_at?->format('d M Y') ?? '—' }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
