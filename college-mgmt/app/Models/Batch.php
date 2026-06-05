<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Batch extends Model
{
    use HasFactory;
    protected $fillable = [
        'program_id', 'academic_year_id', 'name', 'code',
        'start_date', 'end_date', 'intake_capacity', 'status', 'description',
    ];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function program() { return $this->belongsTo(Program::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function terms() { return $this->hasMany(Term::class)->orderBy('term_number'); }
    public function students() { return $this->hasMany(Student::class); }

    public function getCurrentTermAttribute()
    {
        return $this->terms()->where('is_current', true)->first();
    }
}
