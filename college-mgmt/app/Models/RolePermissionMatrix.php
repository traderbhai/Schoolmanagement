<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissionMatrix extends Model
{
    protected $table = 'role_permission_matrices';

    protected $fillable = [
        'role_id',
        'permission_id',
        'program_id',
        'program_specific',
    ];

    protected $casts = [
        'program_specific' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
