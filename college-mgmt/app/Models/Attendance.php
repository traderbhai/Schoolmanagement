<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = ['student_id', 'timetable_entry_id', 'pmc_generation_item_id', 'date', 'status', 'remarks', 'marked_by'];
    protected $casts = ['date' => 'date'];

    public function student() { return $this->belongsTo(Student::class); }
    public function timetableEntry() { return $this->belongsTo(TimetableEntry::class); }
    public function pmcGenerationItem() { return $this->belongsTo(AcademicPmcTimetableGenerationItem::class, 'pmc_generation_item_id'); }
}
