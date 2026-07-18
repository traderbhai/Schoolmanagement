<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="rejectForm" action="">
            @csrf
            <input type="hidden" name="document_id" id="rejectDocId">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-x-circle me-2"></i>Reject Document
                    </h5>
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border mb-3 py-2">
                        <div class="small text-muted">Document</div>
                        <div class="fw-semibold" id="rejectDocName"></div>
                        <div class="small text-muted mt-1">Applicant</div>
                        <div class="fw-semibold" id="rejectApplicant"></div>
                    </div>
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label fw-semibold">
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="4"
                                  placeholder="Explain why this document is being rejected (e.g., blurry image, wrong document, expired certificate)..."
                                  required></textarea>
                        <div class="form-text">This message will be shown to the applicant so they can re-upload.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Confirm Rejection
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
