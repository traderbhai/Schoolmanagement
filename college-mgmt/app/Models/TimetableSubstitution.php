<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSubstitution extends Model {
    protected $fillable = [
        'timetable_entry_id','pmc_generation_item_id','date','substitute_teacher_id',
        'action','reason','created_by','notified_at',
    ];

    protected $casts = ['date' => 'date', 'notified_at' => 'datetime'];

    public function entry(): BelongsTo      { return $this->belongsTo(TimetableEntry::class, 'timetable_entry_id'); }
    public function pmcGenerationItem(): BelongsTo { return $this->belongsTo(AcademicPmcTimetableGenerationItem::class, 'pmc_generation_item_id'); }
    public function substitute(): BelongsTo { return $this->belongsTo(Teacher::class, 'substitute_teacher_id'); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
}
