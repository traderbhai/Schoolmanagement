<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcWorkloadRule extends Model
{
    protected $fillable = ['name', 'program_id', 'term_id', 'normal_weekly_hours', 'overload_threshold', 'underload_threshold', 'max_subjects', 'mentor_capacity', 'rules', 'is_active'];
    protected $casts = ['rules' => 'array', 'is_active' => 'boolean'];
}
