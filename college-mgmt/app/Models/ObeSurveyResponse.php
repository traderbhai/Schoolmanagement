<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ObeSurveyResponse extends Model {
    use HasFactory;
    protected $fillable = ['obe_survey_id','student_id','course_outcome_id','rating'];
    public function survey()        { return $this->belongsTo(ObeSurvey::class, 'obe_survey_id'); }
    public function student()       { return $this->belongsTo(Student::class); }
    public function courseOutcome() { return $this->belongsTo(CourseOutcome::class); }
}
