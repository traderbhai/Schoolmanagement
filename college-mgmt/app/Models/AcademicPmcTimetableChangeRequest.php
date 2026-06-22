<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableChangeRequest extends Model
{
    protected $fillable = ['timetable_version_id', 'pmc_generation_item_id', 'change_type', 'status', 'requested_by', 'decided_by', 'reason', 'decision_note', 'impact_summary'];
    protected $casts = ['impact_summary' => 'array'];

    public function timetableVersion() { return $this->belongsTo(TimetableVersion::class, 'timetable_version_id'); }
    public function pmcGenerationItem() { return $this->belongsTo(AcademicPmcTimetableGenerationItem::class, 'pmc_generation_item_id'); }
}
