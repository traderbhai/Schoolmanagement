<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstituteAsset extends Model
{
    protected $fillable = [
        'asset_category_id', 'asset_tag', 'name', 'serial_number', 'vendor_name',
        'purchase_date', 'purchase_cost', 'location', 'condition', 'status', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class)->where('status', 'active');
    }
}
