<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'program_id', 'batch_id', 'admission_partner_id',
        'partner_reference', 'journey_version_id', 'application_number', 'status',
        'personal_data', 'academic_data', 'family_data', 'additional_data',
        'applied_at', 'reviewed_at', 'reviewed_by', 'assigned_to', 'assigned_at',
        'priority', 'sla_due_at', 'next_action', 'notes',
        'owner_user_id', 'current_handler_user_id', 'assigned_by', 'assignment_reason',
        'assignment_mode', 'last_activity_at', 'escalated_to', 'escalated_at',
        'sla_paused_until', 'sla_pause_reason',
        'category', 'sub_category', 'category_certificate_verified', 'pwd_percentage',
        'domicile_state', 'is_state_quota', 'is_pwd', 'pwd_certificate_number',
        'entrance_exam_type', 'entrance_exam_name', 'entrance_exam_roll_number', 'entrance_exam_score',
        'entrance_exam_year',
        'entrance_exam_rank', 'entrance_exam_date',
        'registration_fee_amount', 'registration_fee_paid_at', 'registration_fee_receipt',
    ];

    protected $casts = [
        'personal_data'            => 'array',
        'academic_data'            => 'array',
        'family_data'              => 'array',
        'additional_data'          => 'array',
        'applied_at'               => 'datetime',
        'reviewed_at'              => 'datetime',
        'assigned_at'              => 'datetime',
        'sla_due_at'               => 'datetime',
        'last_activity_at'         => 'datetime',
        'escalated_at'             => 'datetime',
        'sla_paused_until'         => 'datetime',
        'is_pwd'                   => 'boolean',
        'category_certificate_verified' => 'boolean',
        'pwd_percentage'           => 'float',
        'is_state_quota'           => 'boolean',
        'entrance_exam_score'      => 'float',
        'entrance_exam_year'       => 'integer',
        'entrance_exam_rank'       => 'integer',
        'entrance_exam_date'       => 'date',
        'registration_fee_paid_at' => 'datetime',
        'registration_fee_amount'  => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($applicant) {
            if (empty($applicant->application_number)) {
                $applicant->application_number = static::generateApplicationNumber();
            }
        });
    }

    public static function generateApplicationNumber(): string
    {
        $year = date('Y');
        $max = static::max('id') ?? 0;
        return 'APP-' . $year . '-' . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function program()        { return $this->belongsTo(Program::class); }
    public function batch()          { return $this->belongsTo(Batch::class); }
    public function documents()      { return $this->hasMany(ApplicantDocument::class); }
    public function reviewer()       { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function assignedCounsellor() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function owner()          { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function currentHandler() { return $this->belongsTo(User::class, 'current_handler_user_id'); }
    public function assignedBy()     { return $this->belongsTo(User::class, 'assigned_by'); }
    public function escalatedTo()    { return $this->belongsTo(User::class, 'escalated_to'); }
    public function admissionPartner(){ return $this->belongsTo(AdmissionPartner::class); }
    public function journeyVersion() { return $this->belongsTo(AdmissionJourneyVersion::class, 'journey_version_id'); }
    public function assignmentEvents(){ return $this->morphMany(AdmissionAssignmentEvent::class, 'subject')->latest(); }
    public function tags()           { return $this->morphToMany(AdmissionTag::class, 'taggable', 'admission_taggables')->withTimestamps(); }
    public function counsellingLogs(){ return $this->hasMany(CounsellingLog::class); }
    public function communicationLogs(){ return $this->morphMany(AdmissionCommunicationLog::class, 'subject')->latest(); }
    public function callLogs()       { return $this->morphMany(AdmissionCallLog::class, 'subject')->latest(); }
    public function reminders()      { return $this->morphMany(AdmissionReminderSchedule::class, 'subject')->latest('due_at'); }
    public function managerReviews() { return $this->morphMany(AdmissionManagerReview::class, 'reviewable')->latest('due_at'); }
    public function panelAssignments(){ return $this->hasMany(AdmissionAssessmentPanelAssignment::class); }
    public function dataQualityFlags(){ return $this->morphMany(AdmissionDataQualityFlag::class, 'subject')->latest(); }
    public function admissionApprovals(){ return $this->morphMany(AdmissionApproval::class, 'approvable')->latest(); }
    public function counsellingProfile(){ return $this->morphOne(AdmissionCounsellingProfile::class, 'subject'); }
    public function conversationEvents(){ return $this->morphMany(AdmissionConversationEvent::class, 'subject')->latest('occurred_at'); }
    public function teamNotes()      { return $this->hasMany(AdmissionTeamNote::class); }
    public function scores()         { return $this->hasMany(ApplicantScore::class); }
    public function meritListEntry() { return $this->hasOne(MeritListEntry::class); }
    public function payments()            { return $this->hasMany(AdmissionPayment::class); }
    public function enrollmentConfirmation() { return $this->hasOne(EnrollmentConfirmation::class); }
    public function offerLetters()        { return $this->hasMany(OfferLetter::class); }
    public function offerLetter()         { return $this->hasOne(OfferLetter::class); }

    public function isEnrolled(): bool
    {
        return $this->enrollmentConfirmation()->where('status', 'completed')->exists();
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'verified')->sum('amount_paid');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'general' => 'General',
            'obc'     => 'OBC',
            'obc_ncl' => 'OBC (Non-Creamy Layer)',
            'sc'      => 'SC',
            'st'      => 'ST',
            'ews'     => 'EWS',
            'pwd'     => 'PwD',
            'nri'     => 'NRI',
            default   => 'Not specified',
        };
    }

    public function hasRegistrationFeePaid(): bool
    {
        return $this->registration_fee_paid_at !== null;
    }

    public function getOutstandingAmountAttribute(): float
    {
        $installmentTotal = $this->program
            ? AdmissionFeeInstallment::where('program_id', $this->program_id)
                ->where(function ($q) {
                    $q->whereNull('batch_id')->orWhere('batch_id', $this->batch_id);
                })
                ->where('is_active', true)
                ->sum('amount')
            : 0;
        return max(0, (float) $installmentTotal - $this->total_paid);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'draft'        => 'badge bg-secondary',
            'submitted'    => 'badge bg-primary',
            'under_review' => 'badge bg-warning text-dark',
            'shortlisted'  => 'badge bg-info text-dark',
            'selected'     => 'badge bg-success',
            'rejected'     => 'badge bg-danger',
            'withdrawn'    => 'badge bg-dark',
            default        => 'badge bg-secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'        => 'Draft',
            'submitted'    => 'Submitted',
            'under_review' => 'Under Review',
            'shortlisted'  => 'Shortlisted',
            'selected'     => 'Selected',
            'rejected'     => 'Rejected',
            'withdrawn'    => 'Withdrawn',
            default        => ucfirst($this->status),
        };
    }

    public function getFormDataForSection(string $section): array
    {
        $map = [
            'personal'   => 'personal_data',
            'academic'   => 'academic_data',
            'family'     => 'family_data',
            'additional' => 'additional_data',
        ];
        $col = $map[$section] ?? null;
        if ($col && $this->$col) {
            return $this->$col;
        }
        return [];
    }

    public function approvalWorkflows()
    {
        return $this->morphMany(ApprovalWorkflow::class, 'approvable');
    }

    public function scholarships()
    {
        return $this->hasMany(\App\Models\ApplicantScholarship::class);
    }
}
