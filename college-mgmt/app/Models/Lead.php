<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'program_id', 'source', 'admission_partner_id',
        'partner_reference', 'status', 'priority', 'score_band',
        'notes', 'last_contacted_at', 'converted_applicant_id', 'converted_at',
        'assigned_to', 'assigned_at', 'sla_due_at', 'next_action', 'team', 'region',
        'owner_user_id', 'current_handler_user_id', 'assigned_by', 'assignment_reason',
        'assignment_mode', 'last_activity_at', 'escalated_to', 'escalated_at',
        'sla_paused_until', 'sla_pause_reason',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'converted_at'      => 'datetime',
        'assigned_at'       => 'datetime',
        'sla_due_at'        => 'datetime',
        'last_activity_at'  => 'datetime',
        'escalated_at'      => 'datetime',
        'sla_paused_until'  => 'datetime',
    ];

    public function program()              { return $this->belongsTo(Program::class); }
    public function convertedApplicant()  { return $this->belongsTo(Applicant::class, 'converted_applicant_id'); }
    public function assignedTo()          { return $this->belongsTo(\App\Models\User::class, 'assigned_to'); }
    public function owner()               { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function currentHandler()      { return $this->belongsTo(User::class, 'current_handler_user_id'); }
    public function assignedBy()          { return $this->belongsTo(User::class, 'assigned_by'); }
    public function escalatedTo()         { return $this->belongsTo(User::class, 'escalated_to'); }
    public function admissionPartner()    { return $this->belongsTo(AdmissionPartner::class); }
    public function assignmentEvents()    { return $this->morphMany(AdmissionAssignmentEvent::class, 'subject')->latest(); }
    public function tags()                { return $this->morphToMany(AdmissionTag::class, 'taggable', 'admission_taggables')->withTimestamps(); }
    public function followUps()           { return $this->hasMany(\App\Models\LeadFollowUp::class); }
    public function communicationLogs()   { return $this->morphMany(AdmissionCommunicationLog::class, 'subject')->latest(); }
    public function callLogs()            { return $this->morphMany(AdmissionCallLog::class, 'subject')->latest(); }
    public function scoreRecords()        { return $this->hasMany(AdmissionLeadScore::class); }
    public function dataQualityFlags()    { return $this->morphMany(AdmissionDataQualityFlag::class, 'subject')->latest(); }
    public function admissionApprovals()  { return $this->morphMany(AdmissionApproval::class, 'approvable')->latest(); }

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
