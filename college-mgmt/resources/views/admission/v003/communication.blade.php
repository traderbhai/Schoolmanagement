@extends('layouts.admin')
@section('title', 'Admission Communication Hub')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Communication Hub</h1>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.communication.templates.store') }}" class="card">
                @csrf
                <div class="card-header fw-semibold">Template</div>
                <div class="card-body vstack gap-3">
                    <input class="form-control" name="name" placeholder="Name" required>
                    <select class="form-select" name="channel"><option>email</option><option>internal</option><option>sms</option><option>whatsapp</option></select>
                    <input class="form-control" name="purpose" placeholder="Purpose" value="general">
                    <input class="form-control" name="subject" placeholder="Subject">
                    <textarea class="form-control" name="body" rows="5" required>Hello @{{ name }}, your @{{ program }} admission status is @{{ status }}.</textarea>
                    <button class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Templates</div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Channel</th><th>Purpose</th></tr></thead><tbody>
                    @forelse($templates as $template)<tr><td>{{ $template->name }}</td><td>{{ $template->channel }}</td><td>{{ $template->purpose }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3">No templates.</td></tr>@endforelse
                </tbody></table></div>
            </div>
            <form method="POST" action="{{ route('admission.communication.dispatch') }}" class="mb-3">@csrf<button class="btn btn-outline-success">Dispatch Queued Mock Messages</button></form>
            <div class="card">
                <div class="card-header fw-semibold">Recent Messages</div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Channel</th><th>Provider</th><th>Status</th><th>Recipient</th></tr></thead><tbody>
                    @forelse($logs as $log)<tr><td>{{ $log->channel }}</td><td>{{ $log->provider }}</td><td>{{ $log->status }}</td><td>{{ $log->recipient }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No messages yet.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
@endsection
