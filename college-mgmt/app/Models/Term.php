<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id', 'program_id', 'term_number', 'name',
        'start_date', 'end_date', 'is_current', 'sort_order',
    ];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function batch() { return $this->belongsTo(Batch::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
    public function exams() { return $this->hasMany(Exam::class); }
}
