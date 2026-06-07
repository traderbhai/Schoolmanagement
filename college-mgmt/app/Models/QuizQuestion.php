<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_id', 'question_text', 'type', 'marks', 'order'];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function options() { return $this->hasMany(QuizOption::class, 'quiz_question_id')->orderBy('order'); }
    public function correctOption() { return $this->hasOne(QuizOption::class, 'quiz_question_id')->where('is_correct', true); }
}
