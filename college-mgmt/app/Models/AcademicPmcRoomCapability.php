<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcRoomCapability extends Model
{
    protected $fillable = ['classroom_id', 'capability_type', 'capability_key', 'capability_value', 'is_active', 'metadata'];
    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];

    public function classroom() { return $this->belongsTo(Classroom::class); }
}
