<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionJourneyVersion extends Model
{
    protected $fillable = [
        'journey_id', 'version', 'stages', 'documents', 'fee_milestones',
        'session_rules', 'offer_rules', 'enrollment_blockers',
        'applicant_instructions', 'is_published', 'published_by', 'published_at',
    ];

    protected $casts = [
        'stages' => 'array', 'documents' => 'array', 'fee_milestones' => 'array',
        'session_rules' => 'array', 'offer_rules' => 'array',
        'enrollment_blockers' => 'array', 'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function journey() { return $this->belongsTo(AdmissionJourney::class, 'journey_id'); }
}
