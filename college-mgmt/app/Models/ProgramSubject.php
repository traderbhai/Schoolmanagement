<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProgramSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id', 'subject_id', 'term_id', 'type', 'elective_group',
        'credits', 'max_elective_choices', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
