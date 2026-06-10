<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgReportingLine extends Model
{
    protected $fillable = ['parent_role', 'child_role', 'can_view_summary', 'can_view_full', 'sort_order'];

    protected $casts = ['can_view_summary' => 'boolean', 'can_view_full' => 'boolean'];

    public static function getChildRoles(string $parentRole): \Illuminate\Support\Collection
    {
        return static::where('parent_role', $parentRole)->orderBy('sort_order')->get();
    }

    public static function canView(string $parentRole, string $childRole, bool $fullAccess = false): bool
    {
        $line = static::where('parent_role', $parentRole)->where('child_role', $childRole)->first();
        if (!$line) return false;
        return $fullAccess ? $line->can_view_full : $line->can_view_summary;
    }

    /** All roles that can view $role's data (direct parents only) */
    public static function getParentRoles(string $childRole): \Illuminate\Support\Collection
    {
        return static::where('child_role', $childRole)->pluck('parent_role');
    }
}
