<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoPoMapping extends Model {
    use HasFactory;
    protected $fillable = ['course_outcome_id','program_outcome_id','program_specific_outcome_id','correlation_level'];
    public function courseOutcome()          { return $this->belongsTo(CourseOutcome::class); }
    public function programOutcome()         { return $this->belongsTo(ProgramOutcome::class); }
    public function programSpecificOutcome() { return $this->belongsTo(ProgramSpecificOutcome::class); }
}
