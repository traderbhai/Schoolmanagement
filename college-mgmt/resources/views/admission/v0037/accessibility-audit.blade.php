@extends('layouts.admin')
@section('title', 'Admission Accessibility Audit')
@section('content')
<div class="container-fluid py-3"><h3 class="fw-bold mb-1">Admission Accessibility Audit</h3><div class="text-muted small mb-3">Review checklist for v0.036/v0.037 operational pages.</div><div class="card border-0 shadow-sm"><table class="table table-sm mb-0" aria-label="Accessibility audit checklist"><thead><tr><th>Surface</th><th>Status</th><th>Note</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item['surface'] }}</td><td><span class="badge bg-success">{{ $item['status'] }}</span></td><td>{{ $item['note'] }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
