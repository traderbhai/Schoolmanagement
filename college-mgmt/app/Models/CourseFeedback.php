<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseFeedback extends Model
{
    use HasFactory;

    protected $table = 'course_feedback';

    protected $fillable = [
        'student_id', 'subject_id', 'term_id',
        'teaching_rating', 'content_rating', 'overall_rating',
        'comments', 'is_anonymous',
    ];

    protected $casts = ['is_anonymous' => 'boolean'];

    public function student() { return $this->belongsTo(Student::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
