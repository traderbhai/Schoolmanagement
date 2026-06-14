<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableChangeRequest extends Model
{
    protected $fillable = ['timetable_version_id', 'change_type', 'status', 'requested_by', 'decided_by', 'reason', 'decision_note', 'impact_summary'];
    protected $casts = ['impact_summary' => 'array'];
}
