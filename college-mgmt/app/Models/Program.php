<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
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
