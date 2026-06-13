<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionObjectionType extends Model
{
    protected $fillable = ['name', 'category', 'recommended_response', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function events()
    {
        return $this->hasMany(AdmissionObjectionEvent::class, 'objection_type_id');
    }
}
