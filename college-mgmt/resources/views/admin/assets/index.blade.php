@extends('layouts.admin')

@section('title', 'Asset Register')
@section('page-title', 'Asset Register')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Assets</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Assets</div><div class="fs-3 fw-bold">{{ $stats['total'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Available</div><div class="fs-3 fw-bold text-success">{{ $stats['available'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Assigned</div><div class="fs-3 fw-bold text-primary">{{ $stats['assigned'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Maintenance</div><div class="fs-3 fw-bold text-warning">{{ $stats['maintenance'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Stock Items</div><div class="fs-3 fw-bold">{{ $stats['stock_items'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Low Stock</div><div class="fs-3 fw-bold text-danger">{{ $stats['low_stock'] }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Create Consumable Stock Item</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assets.stock-items.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="asset_category_id" class="form-select">
                    <option value="">- Category -</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Item</label><input name="name" class="form-control" placeholder="Printer Paper" required></div>
            <div class="col-md-2"><label class="form-label">SKU</label><input name="sku" class="form-control" placeholder="STAT-PAPER-A4" required></div>
            <div class="col-md-1"><label class="form-label">Unit</label><input name="unit" class="form-control" value="pcs" required></div>
            <div class="col-md-1"><label class="form-label">Stock</label><input name="current_stock" type="number" min="0" value="0" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Reorder Level</label><input name="reorder_level" type="number" min="0" value="0" class="form-control" required></div>
            <div class="col-md-1"><label class="form-label">Location</label><input name="location" class="form-control" placeholder="Store"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Create</button></div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Create Category</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.assets.categories.store') }}" class="vstack gap-3">
                    @csrf
                    <input name="name" class="form-control" placeholder="Category name" required>
                    <input name="code" class="form-control" placeholder="Code, e.g. IT" required>
                    <button class="btn btn-primary">Save Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Add Asset</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.assets.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3">
                        <select name="asset_category_id" class="form-select">
                            <option value="">- Category -</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input name="asset_tag" class="form-control" placeholder="Asset tag" required></div>
                    <div class="col-md-3"><input name="name" class="form-control" placeholder="Asset name" required></div>
                    <div class="col-md-3"><input name="serial_number" class="form-control" placeholder="Serial number"></div>
                    <div class="col-md-3"><input name="vendor_name" class="form-control" placeholder="Vendor"></div>
                    <div class="col-md-3"><input name="purchase_date" type="date" class="form-control"></div>
                    <div class="col-md-3"><input name="purchase_cost" type="number" step="0.01" min="0" class="form-control" placeholder="Cost"></div>
                    <div class="col-md-3"><input name="location" class="form-control" placeholder="Location"></div>
                    <div class="col-md-3">
                        <select name="condition" class="form-select" required>
                            <option value="new">New</option>
                            <option value="good" selected>Good</option>
                            <option value="needs_repair">Needs Repair</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="col-md-7"><input name="notes" class="form-control" placeholder="Notes"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add Asset</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Tag, name, serial, location">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['available' => 'Available', 'assigned' => 'Assigned', 'maintenance' => 'Maintenance', 'retired' => 'Retired'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('admin.assets.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $asset->name }}</div>
                                <div class="small text-muted"><code>{{ $asset->asset_tag }}</code> {{ $asset->serial_number ? '/ '.$asset->serial_number : '' }}</div>
                            </td>
                            <td>{{ $asset->category->name ?? '-' }}</td>
                            <td>{{ $asset->location ?? '-' }}</td>
                            <td>
                                @if($asset->status === 'available')
                                    <span class="badge bg-success">Available</span>
                                @elseif($asset->status === 'assigned')
                                    <span class="badge bg-primary">Assigned</span>
                                @elseif($asset->status === 'maintenance')
                                    <span class="badge bg-warning text-dark">Maintenance</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($asset->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $asset->currentAssignment->assignedTo->name ?? '-' }}</td>
                            <td class="text-end">Rs. {{ number_format($asset->purchase_cost, 2) }}</td>
                            <td class="text-end">
                                @if($asset->status === 'available')
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignAsset{{ $asset->id }}">Assign</button>
                                @else
                                    <span class="text-muted small">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No assets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($assets->hasPages())
        <div class="card-footer bg-transparent">{{ $assets->links() }}</div>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Active Assignments</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Asset</th>
                        <th>Assigned To</th>
                        <th>Assigned On</th>
                        <th class="text-end">Return</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->asset->name ?? '-' }} <span class="text-muted small">({{ $assignment->asset->asset_tag ?? '-' }})</span></td>
                            <td>{{ $assignment->assignedTo->name ?? '-' }}</td>
                            <td>{{ $assignment->assigned_on?->format('d M Y') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.assets.assignments.return', $assignment) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="returned_on" value="{{ now()->toDateString() }}">
                                    <input type="hidden" name="condition" value="good">
                                    <button class="btn btn-sm btn-outline-success">Return Good</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No active asset assignments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent fw-semibold">Consumable Stock</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Reorder Level</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryItems as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item->name }}</div>
                                <div class="small text-muted"><code>{{ $item->sku }}</code> / {{ $item->unit }}</div>
                            </td>
                            <td>{{ $item->category->name ?? '-' }}</td>
                            <td>{{ $item->location ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ $item->current_stock }}</td>
                            <td class="text-end">{{ $item->reorder_level }}</td>
                            <td>
                                @if($item->status !== 'active')
                                    <span class="badge bg-secondary">Inactive</span>
                                @elseif($item->is_low_stock)
                                    <span class="badge bg-danger">Low Stock</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item->status === 'active')
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#receiveStock{{ $item->id }}">Receive</button>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#issueStock{{ $item->id }}">Issue</button>
                                @else
                                    <span class="text-muted small">No stock movement</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No consumable stock items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent fw-semibold">Recent Stock Movements</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th>Reference</th>
                        <th>Issued To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->movement_date?->format('d M Y') }}</td>
                            <td>{{ $movement->item->name ?? '-' }}</td>
                            <td>{{ ucfirst($movement->movement_type) }}</td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td>{{ $movement->reference_number ?? $movement->vendor_name ?? '-' }}</td>
                            <td>{{ $movement->issuedTo->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No stock movements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($assets as $asset)
    @if($asset->status === 'available')
    <div class="modal fade" id="assignAsset{{ $asset->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.assets.assign', $asset) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Assign {{ $asset->asset_tag }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select name="assigned_to_user_id" class="form-select" required>
                                <option value="">- Select User -</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned On</label>
                            <input type="date" name="assigned_on" value="{{ now()->toDateString() }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" rows="3" class="form-control" placeholder="Handover notes, accessories, or expected return terms"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Assign Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach

@foreach($inventoryItems as $item)
<div class="modal fade" id="receiveStock{{ $item->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.assets.stock-items.receive', $item) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Receive {{ $item->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Quantity</label><input name="quantity" type="number" min="1" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Vendor</label><input name="vendor_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Reference</label><input name="reference_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Date</label><input name="movement_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><textarea name="remarks" rows="3" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Receive Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="issueStock{{ $item->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.assets.stock-items.issue', $item) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Issue {{ $item->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">Current stock: {{ $item->current_stock }} {{ $item->unit }}</div>
                    <div class="mb-3"><label class="form-label">Quantity</label><input name="quantity" type="number" min="1" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Issue To</label>
                        <select name="issued_to_user_id" class="form-select">
                            <option value="">- Select User -</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Reference</label><input name="reference_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Date</label><input name="movement_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><textarea name="remarks" rows="3" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Issue Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
