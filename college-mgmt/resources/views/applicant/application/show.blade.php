@extends('layouts.applicant')
@section('title', 'My Application')
@section('page-title', 'My Application')

@section('content')
<div class="container-fluid p-4">

    @php
        $currentStep = (int) $currentStep;
        $totalSteps = count($sections);
        $progress = $totalSteps > 0 ? (($currentStep + 1) / $totalSteps) * 100 : 0;
        $isLocked = $applicant->status !== 'draft';
    @endphp

    {{-- Status banner if locked --}}
    @if($isLocked)
    <div class="alert alert-info mb-4">
        <i class="bi bi-lock-fill me-2"></i>
        Your application is <strong>{{ $applicant->status_label }}</strong> and cannot be edited.
        <a href="{{ route('applicant.status') }}" class="alert-link ms-2">View Status -></a>
    </div>
    @endif

    {{-- Progress bar --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small">Application Progress</span>
                <span class="text-muted small">Step {{ $currentStep + 1 }} of {{ $totalSteps }}</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar" style="width: {{ $progress }}%"></div>
            </div>

            {{-- Step tabs --}}
            <div class="d-flex gap-2 mt-3 flex-wrap">
                @foreach($sections as $i => $section)
                    @php
                        $sectionKeys = ['personal', 'academic', 'family', 'additional'];
                        $sKey = $sectionKeys[$i] ?? 'additional';
                        $done = !empty($applicant->getFormDataForSection($sKey));
                    @endphp
                    <a href="{{ route('applicant.application.show', ['step' => $i]) }}"
                       class="btn btn-sm {{ $i === $currentStep ? 'btn-primary' : ($done ? 'btn-success' : 'btn-outline-secondary') }}">
                        @if($done && $i !== $currentStep)<i class="bi bi-check me-1"></i>@endif
                        {{ $section['section'] ?? "Step " . ($i+1) }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Current Section Form --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square me-2"></i>
                {{ $sections[$currentStep]['section'] ?? "Section " . ($currentStep + 1) }}
            </h5>
            @php $sectionKeys = ['personal', 'academic', 'family', 'additional']; @endphp
            @if(!empty($applicant->getFormDataForSection($sectionKeys[$currentStep] ?? 'additional')))
                <span class="badge bg-success"><i class="bi bi-check me-1"></i>Saved</span>
            @else
                <span class="badge bg-secondary">Not filled</span>
            @endif
        </div>
        <div class="card-body p-4">
            @include('applicant.application.section', [
                'section' => $sections[$currentStep] ?? [],
                'stepIndex' => $currentStep,
                'sections' => $sections,
            ])
        </div>
    </div>

    {{-- Submit Button (only on last step) --}}
    @if(!$isLocked && $currentStep === $totalSteps - 1)
    <div class="card border-success">
        <div class="card-body text-center p-4">
            <h5 class="fw-bold mb-2">Ready to Submit?</h5>
            <p class="text-muted mb-3">Once submitted, you won't be able to edit your application. Make sure all sections are filled correctly.</p>
            <form method="POST" action="{{ route('applicant.application.submit') }}"
                  onsubmit="return confirm('Submit your admission application now? Confirm all sections, documents, program choices, and contact details are final because editing is locked after submission.')">
                @csrf
                <button type="submit" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-send-fill me-2"></i>Submit Application
                </button>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
