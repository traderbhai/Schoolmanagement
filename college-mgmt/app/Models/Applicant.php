<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'program_id', 'batch_id', 'application_number', 'status',
        'personal_data', 'academic_data', 'family_data', 'additional_data',
        'applied_at', 'reviewed_at', 'reviewed_by', 'notes',
    ];

    protected $casts = [
        'personal_data'   => 'array',
        'academic_data'   => 'array',
        'family_data'     => 'array',
        'additional_data' => 'array',
        'applied_at'      => 'datetime',
        'reviewed_at'     => 'datetime',
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
    public function counsellingLogs(){ return $this->hasMany(CounsellingLog::class); }
    public function teamNotes()      { return $this->hasMany(AdmissionTeamNote::class); }

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
}
