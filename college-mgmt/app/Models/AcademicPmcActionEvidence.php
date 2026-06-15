<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcActionEvidence extends Model
{
    protected $fillable = ['work_item_id', 'uploaded_by', 'title', 'evidence_type', 'evidence_note', 'file_path', 'verification_status', 'verified_by', 'verified_at', 'verification_note', 'metadata'];
    protected $casts = ['verified_at' => 'datetime', 'metadata' => 'array'];

    public function workItem() { return $this->belongsTo(AcademicPmcWorkItem::class, 'work_item_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
