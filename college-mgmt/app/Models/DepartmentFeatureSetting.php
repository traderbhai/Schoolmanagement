<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentFeatureSetting extends Model
{
    protected $fillable = [
        'department_id', 'feature_key', 'feature_name', 'is_enabled',
        'config', 'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
