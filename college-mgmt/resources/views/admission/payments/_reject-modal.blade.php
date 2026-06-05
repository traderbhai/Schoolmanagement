{{-- Reject Modal — pass $payment variable --}}
<div class="modal fade" id="rejectModal-{{ $payment->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admission.payments.reject', $payment) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Rejecting payment from
                        <strong>{{ $payment->applicant->user->name ?? 'N/A' }}</strong>
                        ({{ $payment->applicant->application_number }}) for
                        <strong>{{ $payment->installment->name ?? 'N/A' }}</strong> —
                        ₹{{ number_format($payment->amount_paid, 0) }}.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="verification_notes" class="form-control" rows="3"
                            placeholder="Provide reason for rejection (e.g., incorrect transaction reference, proof unclear)..."
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
