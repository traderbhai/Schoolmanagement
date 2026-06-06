<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'applicant_id', 'admission_payment_id', 'requested_amount', 'approved_amount',
        'status', 'reason', 'reason_detail', 'rejection_reason',
        'bank_name', 'account_number', 'ifsc_code', 'account_holder_name',
        'utr_number', 'processed_at', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'requested_amount' => 'float',
        'approved_amount'  => 'float',
        'processed_at'     => 'datetime',
        'reviewed_at'      => 'datetime',
    ];

    public function applicant()   { return $this->belongsTo(Applicant::class); }
    public function payment()     { return $this->belongsTo(AdmissionPayment::class, 'admission_payment_id'); }
    public function reviewedBy()  { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getStatusBadgeAttribute(): string {
        return match($this->status) {
            'pending'   => 'badge bg-warning text-dark',
            'approved'  => 'badge bg-success',
            'rejected'  => 'badge bg-danger',
            'processed' => 'badge bg-primary',
            default     => 'badge bg-secondary',
        };
    }
    public function getReasonLabelAttribute(): string {
        return match($this->reason) {
            'withdrawal'     => 'Candidate Withdrawal',
            'rejection'      => 'Application Rejected',
            'excess_payment' => 'Excess Payment',
            'other'          => 'Other',
            default          => ucfirst($this->reason),
        };
    }
}
