<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Program extends Model
{
    use HasFactory;
    protected $fillable = [
        'department_id', 'name', 'code', 'abbreviation', 'system_type',
        'duration_years', 'total_terms', 'description', 'default_intake_capacity', 'is_active',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function specializations() { return $this->hasMany(Specialization::class); }
    public function batches() { return $this->hasMany(Batch::class); }
    public function subjects() { return $this->hasMany(Subject::class); }
    public function students() { return $this->hasMany(Student::class); }
    public function feeStructures() { return $this->hasMany(FeeStructure::class); }
    public function admissionFormConfig() { return $this->hasOne(AdmissionFormConfig::class); }
    public function requiredDocuments() { return $this->hasMany(RequiredDocument::class)->orderBy('sort_order'); }
    public function selectionProcessSteps() { return $this->hasMany(SelectionProcessStep::class)->orderBy('step_order'); }
    public function admissionFeeInstallments() { return $this->hasMany(AdmissionFeeInstallment::class)->orderBy('installment_number'); }

    public function getSystemTypeLabelAttribute(): string
    {
        return match($this->system_type) {
            'semester' => 'Semester',
            'trimester' => 'Trimester',
            'annual' => 'Annual',
            'quarter' => 'Quarter',
            default => ucfirst($this->system_type),
        };
    }

    public function getTermTypeLabelAttribute(): string
    {
        return match($this->system_type) {
            'semester' => 'Semester',
            'trimester' => 'Trimester',
            'annual' => 'Year',
            'quarter' => 'Quarter',
            default => 'Term',
        };
    }
}
