<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCounsellorPlaybookStep extends Model
{
    protected $fillable = ['playbook_id', 'title', 'body', 'suggested_action', 'sort_order'];

    public function playbook() { return $this->belongsTo(AdmissionCounsellorPlaybook::class, 'playbook_id'); }
}
