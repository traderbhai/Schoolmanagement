<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcSubstitutionRecommendation extends Model
{
    protected $fillable = ['timetable_entry_id', 'pmc_generation_item_id', 'course_group_id', 'original_teacher_id', 'substitute_teacher_id', 'substitution_date', 'status', 'score', 'reasons', 'conflict_checks'];
    protected $casts = ['substitution_date' => 'date', 'reasons' => 'array', 'conflict_checks' => 'array'];
    public function pmcGenerationItem() { return $this->belongsTo(AcademicPmcTimetableGenerationItem::class, 'pmc_generation_item_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function originalTeacher() { return $this->belongsTo(Teacher::class, 'original_teacher_id'); }
    public function substituteTeacher() { return $this->belongsTo(Teacher::class, 'substitute_teacher_id'); }
}
