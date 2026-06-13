<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionJourney extends Model
{
    protected $fillable = ['name', 'program_id', 'batch_id', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function versions() { return $this->hasMany(AdmissionJourneyVersion::class, 'journey_id'); }
    public function currentVersion() { return $this->hasOne(AdmissionJourneyVersion::class, 'journey_id')->where('is_published', true)->latest('version'); }
}
