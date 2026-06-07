<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_attempt_id', 'quiz_question_id', 'quiz_option_id', 'answer_text', 'is_correct'];
    protected $casts = ['is_correct' => 'boolean'];

    public function attempt() { return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id'); }
    public function question() { return $this->belongsTo(QuizQuestion::class, 'quiz_question_id'); }
    public function option() { return $this->belongsTo(QuizOption::class, 'quiz_option_id'); }
}
