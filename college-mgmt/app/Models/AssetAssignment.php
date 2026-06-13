<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'institute_asset_id', 'assigned_to_user_id', 'assigned_by', 'assigned_on',
        'returned_on', 'status', 'remarks',
    ];

    protected $casts = [
        'assigned_on' => 'date',
        'returned_on' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(InstituteAsset::class, 'institute_asset_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
