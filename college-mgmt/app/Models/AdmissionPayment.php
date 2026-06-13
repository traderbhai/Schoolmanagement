<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdmissionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'admission_fee_installment_id', 'amount_paid', 'payment_date',
        'payment_mode', 'transaction_reference', 'bank_name', 'payment_proof_path',
        'status', 'verification_notes', 'verified_by', 'verified_at', 'submitted_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at'  => 'datetime',
        'amount_paid'  => 'decimal:2',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function installment()
    {
        return $this->belongsTo(AdmissionFeeInstallment::class, 'admission_fee_installment_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'pending'  => 'badge bg-warning text-dark',
            'verified' => 'badge bg-success',
            'rejected' => 'badge bg-danger',
            default    => 'badge bg-secondary',
        };
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return match($this->payment_mode) {
            'neft'   => 'NEFT',
            'rtgs'   => 'RTGS',
            'imps'   => 'IMPS',
            'upi'    => 'UPI',
            'dd'     => 'Demand Draft',
            'cash'   => 'Cash',
            'cheque' => 'Cheque',
            default  => strtoupper($this->payment_mode),
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        $amount = (float) $this->amount_paid;
        // Indian number formatting: Rs. 1,40,000
        $formatted = '';
        $intPart = (int) $amount;
        $decPart = round($amount - $intPart, 2);
        $str = (string) $intPart;
        if (strlen($str) > 3) {
            $last3 = substr($str, -3);
            $rest = substr($str, 0, -3);
            $rest = preg_replace('/(\d)(?=(\d{2})+(?!\d))/', '$1,', $rest);
            $formatted = 'Rs. ' . $rest . ',' . $last3;
        } else {
            $formatted = 'Rs. ' . $str;
        }
        if ($decPart > 0) {
            $formatted .= '.' . str_pad((int) round($decPart * 100), 2, '0', STR_PAD_LEFT);
        }
        return $formatted;
    }
}
