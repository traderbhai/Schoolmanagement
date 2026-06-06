@extends('layouts.admin')
@section('title', 'Feature Access Matrix')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Feature Access Matrix</h4>
            <span class="text-muted small">Cross-reference of roles vs. features — view / create / edit / approve / delete</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.hierarchy') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-diagram-3 me-1"></i> Hierarchy
            </a>
            <a href="{{ route('admin.roles.permissions.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-key me-1"></i> Permissions
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Legend --}}
    <div class="d-flex gap-3 mb-3 flex-wrap">
        <span class="small text-muted fw-semibold">Access levels:</span>
        @foreach(['view'=>'primary','create'=>'success','edit'=>'warning','approve'=>'info','delete'=>'danger'] as $level => $color)
        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle px-2">{{ $level }}</span>
        @endforeach
        <span class="badge bg-light text-muted border px-2">— none</span>
    </div>

    @php
        $categories = collect($features)->groupBy('category');
        $roleNames = $roles->pluck('name');
    @endphp

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0" style="font-size:.8rem">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="min-width:180px">Feature</th>
                            @foreach($roles as $role)
                            <th class="text-center" style="min-width:100px">
                                <div>{{ ucwords(str_replace('_', ' ', $role->name)) }}</div>
                                <a href="{{ route('admin.roles.feature-access.edit', $role) }}"
                                   class="btn btn-xs btn-outline-secondary py-0 px-1 mt-1" style="font-size:.65rem">
                                    Edit
                                </a>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category => $catFeatures)
                        <tr class="table-secondary">
                            <td colspan="{{ $roles->count() + 1 }}" class="ps-3 fw-semibold text-uppercase"
                                style="font-size:.65rem;letter-spacing:.07em">
                                {{ $category }}
                            </td>
                        </tr>
                        @foreach($catFeatures as $feature)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-medium">{{ $feature['label'] }}</div>
                                <div class="text-muted" style="font-size:.7rem">{{ $feature['code'] }}</div>
                            </td>
                            @foreach($roles as $role)
                            @php
                                $access = $accessMatrix[$role->id][$feature['code']] ?? null;
                                $colorMap = [
                                    'view'    => 'primary',
                                    'create'  => 'success',
                                    'edit'    => 'warning',
                                    'approve' => 'info',
                                    'delete'  => 'danger',
                                ];
                                $color = $access ? ($colorMap[$access] ?? 'secondary') : null;
                            @endphp
                            <td class="text-center">
                                @if($access)
                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle px-2">
                                    {{ $access }}
                                </span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Click <strong>Edit</strong> under any role column to modify its feature access levels.
        Access levels are cumulative: <em>delete</em> implies all lesser levels.
    </div>
</div>
@endsection
