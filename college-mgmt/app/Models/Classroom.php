<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = ['name', 'room_number', 'capacity', 'type', 'building', 'floor', 'has_projector', 'has_lab', 'is_active'];
    protected $casts = ['has_projector' => 'boolean', 'has_lab' => 'boolean', 'is_active' => 'boolean'];

    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
    public function exams() { return $this->hasMany(Exam::class); }
}
