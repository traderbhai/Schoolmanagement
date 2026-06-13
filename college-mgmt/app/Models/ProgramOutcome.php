<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProgramOutcome extends Model {
    use HasFactory;
    protected $fillable = ['program_id','code','description','category','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function program()     { return $this->belongsTo(Program::class); }
    public function coMappings()  { return $this->hasMany(CoPoMapping::class); }
    public function attainments() { return $this->hasMany(PoAttainment::class); }
    public function courseOutcomes() {
        return $this->belongsToMany(CourseOutcome::class, 'co_po_mappings', 'program_outcome_id', 'course_outcome_id')
            ->withPivot('correlation_level')->withTimestamps();
    }
}
