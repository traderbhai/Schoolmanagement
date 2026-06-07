<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AcademicEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'type', 'start_date', 'end_date',
        'location', 'program_id', 'term_id', 'created_by', 'is_public',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_public' => 'boolean',
    ];

    public function program() { return $this->belongsTo(Program::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
