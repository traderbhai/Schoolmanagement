<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'program_id', 'source', 'status',
        'notes', 'last_contacted_at', 'converted_applicant_id', 'converted_at',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'converted_at'      => 'datetime',
    ];

    public function program()              { return $this->belongsTo(Program::class); }
    public function convertedApplicant()  { return $this->belongsTo(Applicant::class, 'converted_applicant_id'); }

    public function isConverted(): bool   { return $this->status === 'converted'; }
    public function isContacted(): bool   { return in_array($this->status, ['contacted', 'interested', 'converted']); }

    public function markContacted(): void
    {
        if ($this->status === 'new') {
            $this->update(['status' => 'contacted', 'last_contacted_at' => now()]);
        }
    }

    public function markInterested(): void
    {
        $this->update(['status' => 'interested', 'last_contacted_at' => now()]);
    }

    public function markNotInterested(): void
    {
        $this->update(['status' => 'not_interested', 'last_contacted_at' => now()]);
    }

    public function convertToApplicant(Applicant $applicant): void
    {
        $this->update([
            'status'                  => 'converted',
            'converted_applicant_id'  => $applicant->id,
            'converted_at'            => now(),
        ]);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'new'             => 'badge bg-info text-dark',
            'contacted'       => 'badge bg-secondary',
            'interested'      => 'badge bg-warning text-dark',
            'not_interested'  => 'badge bg-danger',
            'converted'       => 'badge bg-success',
            default           => 'badge bg-secondary',
        };
    }

    public function getSourceLabelAttribute(): string
    {
        return match($this->source) {
            'web_form'       => 'Web Form',
            'referral'       => 'Referral',
            'advertisement'  => 'Advertisement',
            'social_media'   => 'Social Media',
            'event'          => 'Event',
            'agent'          => 'Agent',
            'other'          => 'Other',
            default          => $this->source,
        };
    }
}
