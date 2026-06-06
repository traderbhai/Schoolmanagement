<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantScholarship extends Model
{
    protected $fillable = [
        'applicant_id', 'scheme_id', 'awarded_amount',
        'status', 'awarded_by', 'awarded_at',
        'disbursed_at', 'disbursement_ref', 'notes',
    ];

    protected $casts = [
        'awarded_amount' => 'decimal:2',
        'awarded_at'     => 'datetime',
        'disbursed_at'   => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ScholarshipScheme::class, 'scheme_id');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'awarded'    => 'badge bg-success',
            'disbursed'  => 'badge bg-primary',
            'cancelled'  => 'badge bg-danger',
            default      => 'badge bg-secondary',
        };
    }
}
