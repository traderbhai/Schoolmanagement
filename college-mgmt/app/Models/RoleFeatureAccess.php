<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleFeatureAccess extends Model
{
    protected $table = 'role_feature_access';

    protected $fillable = [
        'role_id',
        'feature_code',
        'access_level',
    ];

    // Access levels: view, create, edit, approve, delete
    public const ACCESS_LEVELS = ['view', 'create', 'edit', 'approve', 'delete'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function canAccess($roleId, $featureCode, $requiredLevel = 'view'): bool
    {
        $accessRecord = self::where('role_id', $roleId)
            ->where('feature_code', $featureCode)
            ->first();

        if (!$accessRecord) {
            return false;
        }

        $levels = self::ACCESS_LEVELS;
        $requiredIndex = array_search($requiredLevel, $levels);
        $grantedIndex = array_search($accessRecord->access_level, $levels);

        return $grantedIndex >= $requiredIndex;
    }
}
