<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_item_id', 'movement_type', 'quantity', 'performed_by',
        'issued_to_user_id', 'vendor_name', 'reference_number', 'movement_date', 'remarks',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }
}
