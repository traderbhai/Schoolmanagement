<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseOutcome extends Model {
    use HasFactory;
    protected $fillable = ['subject_id','code','description','bloom_level','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function subject()       { return $this->belongsTo(Subject::class); }
    public function coPoMappings()  { return $this->hasMany(CoPoMapping::class); }
    public function attainments()   { return $this->hasMany(CoAttainment::class); }
    public function programOutcomes() {
        return $this->belongsToMany(ProgramOutcome::class, 'co_po_mappings', 'course_outcome_id', 'program_outcome_id')
            ->withPivot('correlation_level')->withTimestamps();
    }
}
