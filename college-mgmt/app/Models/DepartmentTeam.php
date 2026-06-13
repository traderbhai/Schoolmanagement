<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentTeam extends Model
{
    protected $fillable = [
        'department_id', 'parent_id', 'name', 'type', 'scope_rules', 'is_active',
    ];

    protected $casts = [
        'scope_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function members() { return $this->hasMany(DepartmentMember::class); }
}
