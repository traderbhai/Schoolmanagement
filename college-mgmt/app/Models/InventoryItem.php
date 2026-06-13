<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'asset_category_id', 'name', 'sku', 'unit', 'current_stock',
        'reorder_level', 'location', 'status',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->current_stock <= $this->reorder_level;
    }
}
