@extends('layouts.admin')
@section('title','Companies')
@section('page-title','Company Database')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="GET" class="d-flex gap-2">
    <input type="search" name="search" class="form-control form-control-sm" placeholder="Search companies..." value="{{ request('search') }}" style="width:220px;">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
  </form>
  <a href="{{ route('cmc.companies.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Company</a>
</div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">Name</th><th>Industry</th><th>Contact Person</th><th>Contact Email</th><th>Drives</th><th>Active</th><th class="text-end pe-3">Action</th></tr>
      </thead>
      <tbody>
        @forelse($companies as $c)
        <tr>
          <td class="ps-3 fw-medium">{{ $c->name }}</td>
          <td class="small text-muted">{{ $c->industry ?? '—' }}</td>
          <td class="small">{{ $c->contact_person ?? '—' }}</td>
          <td class="small">{{ $c->contact_email ?? '—' }}</td>
          <td>{{ $c->drives_count }}</td>
          <td><span class="badge bg-{{ $c->is_active ? 'success' : 'secondary' }}">{{ $c->is_active ? 'Yes' : 'No' }}</span></td>
          <td class="text-end pe-3">
            <a href="{{ route('cmc.companies.edit', $c) }}" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No companies found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $companies->withQueryString()->links() }}
@endsection
