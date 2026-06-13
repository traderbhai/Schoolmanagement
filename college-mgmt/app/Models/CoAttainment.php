<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoAttainment extends Model {
    use HasFactory;
    protected $fillable = [
        'course_outcome_id','subject_id','term_id','batch_id',
        'direct_attainment','indirect_attainment','final_attainment',
        'target_attainment','target_met','students_assessed','students_attained'
    ];
    protected $casts = [
        'target_met' => 'boolean',
        'direct_attainment' => 'decimal:2',
        'indirect_attainment' => 'decimal:2',
        'final_attainment' => 'decimal:2',
        'target_attainment' => 'decimal:2',
    ];
    public function courseOutcome() { return $this->belongsTo(CourseOutcome::class); }
    public function subject()       { return $this->belongsTo(Subject::class); }
    public function term()          { return $this->belongsTo(Term::class); }
    public function batch()         { return $this->belongsTo(Batch::class); }
}
