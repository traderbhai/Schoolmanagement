<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferLetter extends Model
{
    protected $fillable = [
        'applicant_id', 'program_id', 'batch_id', 'offer_number',
        'status', 'acceptance_deadline', 'accepted_at', 'declined_at',
        'declined_reason', 'issued_at', 'issued_by',
    ];

    protected $casts = [
        'acceptance_deadline' => 'date',
        'accepted_at'         => 'datetime',
        'declined_at'         => 'datetime',
        'issued_at'           => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($offer) {
            if (empty($offer->offer_number)) {
                $offer->offer_number = static::generateOfferNumber();
            }
        });
    }

    public static function generateOfferNumber(): string
    {
        $year = date('Y');
        $max = static::max('id') ?? 0;
        return 'OFF-' . $year . '-' . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
    }

    public function applicant()     { return $this->belongsTo(Applicant::class); }
    public function program()       { return $this->belongsTo(Program::class); }
    public function batch()         { return $this->belongsTo(Batch::class); }
    public function issuedBy()      { return $this->belongsTo(User::class, 'issued_by'); }

    public function isAccepted(): bool  { return $this->status === 'accepted'; }
    public function isDeclined(): bool  { return $this->status === 'declined'; }
    public function isPending(): bool   { return $this->status === 'issued'; }
    public function isExpired(): bool   { return $this->status === 'expired'; }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'issued'   => 'badge bg-info text-dark',
            'accepted' => 'badge bg-success',
            'declined' => 'badge bg-danger',
            'expired'  => 'badge bg-secondary',
            default    => 'badge bg-secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'issued'   => 'Issued',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'expired'  => 'Expired',
            default    => 'Unknown',
        };
    }
}
