@extends('layouts.admin')

@section('title', 'Lead Analytics')
@section('page-title', 'Lead Analytics')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Lead Analytics</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admission.leads.index') }}" class="btn btn-primary">Back to Leads</a>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Leads</h6>
                    <h2 class="text-primary">{{ $totalLeads }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Conversion Rate</h6>
                    <h2 class="text-success">{{ $conversionRate }}%</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted">Avg per Day</h6>
                    <h2 class="text-info">{{ $totalLeads > 0 ? round($totalLeads / 30) : 0 }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- By Source -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Leads by Source</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Source</th>
                                <th scope="col">Total</th>
                                <th scope="col">Converted</th>
                                <th scope="col">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leadsBySource as $item)
                                <tr>
                                    <td><strong>{{ ucfirst(str_replace('_', ' ', $item->source)) }}</strong></td>
                                    <td>{{ $item->total }}</td>
                                    <td>{{ $item->converted }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->conversion_rate >= 20 ? 'success' : ($item->conversion_rate >= 10 ? 'warning' : 'danger') }}">
                                            {{ $item->conversion_rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No lead source data is available yet. Capture or import leads with a source to compare channel performance.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- By Program -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Leads by Program</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Program</th>
                                <th scope="col">Total</th>
                                <th scope="col">Converted</th>
                                <th scope="col">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leadsByProgram as $item)
                                <tr>
                                    <td><strong>{{ $item->name ?? 'Not Specified' }}</strong></td>
                                    <td>{{ $item->total }}</td>
                                    <td>{{ $item->converted }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->conversion_rate >= 20 ? 'success' : ($item->conversion_rate >= 10 ? 'warning' : 'danger') }}">
                                            {{ $item->conversion_rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No program-linked leads are available yet. Link leads to programs to compare program demand and conversion.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 30-Day Trend -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">30-Day Trend</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">New Leads</th>
                                <th scope="col">Conversions</th>
                                <th scope="col">Daily Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leadsOverTime as $item)
                                <tr>
                                    <td><strong>{{ \Carbon\Carbon::parse($item->date)->format('d M Y') }}</strong></td>
                                    <td>{{ $item->total }}</td>
                                    <td>{{ $item->converted }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->total > 0 ? 'primary' : 'secondary' }}">
                                            {{ $item->total > 0 ? round(($item->converted / $item->total) * 100) : 0 }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No lead activity has been captured in the last 30 days. Add new leads or widen the reporting period from the lead list.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
