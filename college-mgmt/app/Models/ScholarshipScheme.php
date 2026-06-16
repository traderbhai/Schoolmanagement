<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipScheme extends Model
{
    protected $fillable = [
        'program_id', 'name', 'scheme_code', 'type',
        'criteria', 'min_cgpa', 'max_family_income', 'requires_document',
        'max_amount', 'available_seats', 'is_active',
    ];

    protected $casts = [
        'max_amount'       => 'decimal:2',
        'min_cgpa'         => 'decimal:2',
        'max_family_income'=> 'decimal:2',
        'requires_document'=> 'boolean',
        'available_seats'  => 'integer',
        'is_active'        => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function applicantScholarships(): HasMany
    {
        return $this->hasMany(ApplicantScholarship::class, 'scheme_id');
    }

    public function studentScholarshipApplications(): HasMany
    {
        return $this->hasMany(StudentScholarshipApplication::class, 'scholarship_scheme_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'merit'       => 'Merit',
            'need_based'  => 'Need Based',
            'government'  => 'Government',
            'aicte'       => 'AICTE',
            'institution' => 'Institution',
            default       => ucfirst($this->type),
        };
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->type) {
            'merit'       => 'badge bg-primary',
            'need_based'  => 'badge bg-warning text-dark',
            'government'  => 'badge bg-success',
            'aicte'       => 'badge bg-info text-dark',
            'institution' => 'badge bg-secondary',
            default       => 'badge bg-secondary',
        };
    }

    public function awardsCount(): int
    {
        return $this->applicantScholarships()->whereIn('status', ['awarded', 'disbursed'])->count()
            + $this->studentScholarshipApplications()->whereIn('status', ['approved', 'disbursed'])->count();
    }

    public function seatsRemaining(): ?int
    {
        if ($this->available_seats === null) return null;
        return max(0, $this->available_seats - $this->awardsCount());
    }
}
