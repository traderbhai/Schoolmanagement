@extends('layouts.admin')

@section('title', 'Admission Accessibility Audit')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Admission Accessibility Audit</h3>
            <div class="text-muted small">Review accessibility readiness for high-use Admission operational pages before release.</div>
        </div>
        <a href="{{ route('admission.route-access-audit.index') }}" class="btn btn-outline-primary btn-sm">Route Access Audit</a>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Audit workflow:</strong> check labels, button names, table labels, focus behavior, and badge meaning for each surface before marking the page release-ready.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Accessibility Checklist</span>
            <span class="small text-muted">{{ count($items) }} checks</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Accessibility audit checklist">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Surface</th>
                        <th scope="col">Status</th>
                        <th scope="col">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['surface'] }}</td>
                            <td><span class="badge bg-success">{{ $item['status'] }}</span></td>
                            <td>{{ $item['note'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No accessibility checklist items are available</div>
                                <div class="small">Add audit checklist rules before using this page as release evidence for Admission surfaces.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
