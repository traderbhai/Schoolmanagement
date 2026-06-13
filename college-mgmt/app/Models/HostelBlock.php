<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelBlock extends Model
{
    protected $fillable = [
        'name', 'gender', 'total_floors', 'warden_id', 'address_notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rooms()
    {
        return $this->hasMany(HostelRoom::class);
    }

    public function warden()
    {
        return $this->belongsTo(User::class, 'warden_id');
    }
}
