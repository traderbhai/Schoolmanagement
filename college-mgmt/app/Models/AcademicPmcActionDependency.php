<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcActionDependency extends Model
{
    protected $fillable = ['work_item_id', 'depends_on_work_item_id', 'dependency_type', 'status', 'reason', 'created_by', 'resolved_at', 'metadata'];
    protected $casts = ['resolved_at' => 'datetime', 'metadata' => 'array'];

    public function workItem() { return $this->belongsTo(AcademicPmcWorkItem::class, 'work_item_id'); }
    public function dependsOn() { return $this->belongsTo(AcademicPmcWorkItem::class, 'depends_on_work_item_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
