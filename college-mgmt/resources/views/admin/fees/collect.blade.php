@extends('layouts.admin')
@section('title', 'Collect Fee Payment')
@section('page-title', 'Collect Fee Payment')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Management</a></li>
    <li class="breadcrumb-item active">Collect Payment</li>
@endsection
@section('content')

<div style="max-width:760px">

{{-- Student Info Card (shown after student selected) --}}
@if($selectedStudent)
<div class="card mb-3 border-primary">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div>
                <div class="fw-bold">{{ $selectedStudent->user->name }}</div>
                <div class="text-muted small">{{ $selectedStudent->enrollment_number }}</div>
            </div>
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary">{{ $selectedStudent->course->name ?? '—' }}</span>
            </div>
            <div class="ms-auto text-end">
                <div class="text-muted small">Balance Due</div>
                <div class="fw-bold fs-5 {{ $balanceDue > 0 ? 'text-danger' : 'text-success' }}">
                    ₹{{ number_format($balanceDue, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-cash me-2 text-success"></i>Record Fee Payment</span>
        <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.payment') }}" id="collectForm">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" id="studentSelect" class="form-select @error('student_id') is-invalid @enderror" required
                        onchange="window.location='{{ route('admin.fees.collect') }}?student_id='+this.value">
                        <option value="">Select student…</option>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" @selected(old('student_id', $selectedStudent?->id) == $s->id)>
                                {{ $s->user->name }} ({{ $s->enrollment_number }})
                            </option>
                        @endforeach
                    </select>
                    @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fee Structure <span class="text-danger">*</span></label>
                    <select name="fee_structure_id" class="form-select @error('fee_structure_id') is-invalid @enderror" required>
                        <option value="">Select fee type…</option>
                        @foreach($structures as $f)
                            <option value="{{ $f->id }}" @selected(old('fee_structure_id') == $f->id)>
                                {{ $f->course->code ?? $f->course->name ?? '?' }} – {{ $f->fee_type }} (₹{{ number_format($f->amount,2) }})
                            </option>
                        @endforeach
                    </select>
                    @error('fee_structure_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Amount Paid (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount_paid" class="form-control @error('amount_paid') is-invalid @enderror"
                        value="{{ old('amount_paid', $balanceDue ?: '') }}" min="0" step="0.01" required>
                    <div class="form-text">Actual amount received.</div>
                    @error('amount_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                        value="{{ old('payment_date', date('Y-m-d')) }}" required>
                    @error('payment_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Payment Method as radio buttons with icons --}}
            <div class="mb-3">
                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-2" id="paymentMethodGroup">
                    @php
                        $methods = [
                            'cash'   => ['icon' => 'bi-cash',            'label' => 'Cash'],
                            'cheque' => ['icon' => 'bi-file-earmark',    'label' => 'Cheque'],
                            'online' => ['icon' => 'bi-globe',           'label' => 'Online'],
                            'neft'   => ['icon' => 'bi-bank',            'label' => 'NEFT'],
                            'rtgs'   => ['icon' => 'bi-arrow-left-right','label' => 'RTGS'],
                            'upi'    => ['icon' => 'bi-phone',           'label' => 'UPI'],
                        ];
                        $selectedMethod = old('payment_method', 'cash');
                    @endphp
                    @foreach($methods as $val => $m)
                    <div>
                        <input type="radio" class="btn-check" name="payment_method" id="pm_{{ $val }}"
                            value="{{ $val }}" @checked($selectedMethod === $val) required>
                        <label class="btn btn-outline-primary" for="pm_{{ $val }}" style="min-width:90px;font-size:.85rem">
                            <i class="bi {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}
                        </label>
                    </div>
                    @endforeach
                </div>
                @error('payment_method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- Transaction Reference (shown when not cash) --}}
            <div class="mb-3" id="transactionRefRow" style="{{ $selectedMethod === 'cash' ? 'display:none' : '' }}">
                <label class="form-label">Transaction Reference</label>
                <input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id') }}"
                    placeholder="Cheque number / UTR / Transaction ID">
                <div class="form-text">Reference number for non-cash payments.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Any notes about this payment…">{{ old('remarks') }}</textarea>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
                <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@if($selectedStudent)
{{-- Fee Structure Breakdown --}}
<div class="card mt-3">
    <div class="card-header fw-semibold" style="font-size:.9rem">
        <i class="bi bi-list-ul me-1 text-primary"></i>Fee Structure Breakdown for {{ $selectedStudent->user->name }}
    </div>
    <div class="card-body p-0">
        @php
            $currentYear = \App\Models\AcademicYear::where('is_current', true)->first();
            $studentFees = \App\Models\FeeStructure::where('course_id', $selectedStudent->course_id)
                ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                ->get();
            $studentPayments = \App\Models\FeePayment::where('student_id', $selectedStudent->id)->where('status','paid')->get();
        @endphp
        @if($studentFees->isEmpty())
        <div class="p-3 text-muted small">No fee structures found for this student's course.</div>
        @else
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fee Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
            @foreach($studentFees as $fs)
                @php
                    $paid = $studentPayments->where('fee_structure_id', $fs->id)->sum('amount_paid');
                    $bal = max(0, $fs->amount - $paid);
                @endphp
                <tr>
                    <td>{{ $fs->fee_type }}</td>
                    <td class="text-end">₹{{ number_format($fs->amount, 2) }}</td>
                    <td class="text-end text-success">₹{{ number_format($paid, 2) }}</td>
                    <td class="text-end fw-semibold {{ $bal > 0 ? 'text-danger' : 'text-success' }}">
                        ₹{{ number_format($bal, 2) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td class="fw-bold">Total</td>
                    <td class="text-end fw-bold">₹{{ number_format($studentFees->sum('amount'), 2) }}</td>
                    <td class="text-end fw-bold text-success">₹{{ number_format($studentPayments->sum('amount_paid'), 2) }}</td>
                    <td class="text-end fw-bold text-danger">₹{{ number_format($balanceDue, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>
@endif

</div>

@push('scripts')
<script>
(function() {
    function toggleTransRef() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        const row = document.getElementById('transactionRefRow');
        if (!row) return;
        row.style.display = selected && selected.value === 'cash' ? 'none' : '';
    }
    document.querySelectorAll('input[name="payment_method"]').forEach(el => {
        el.addEventListener('change', toggleTransRef);
    });
    toggleTransRef();
})();
</script>
@endpush
@endsection
