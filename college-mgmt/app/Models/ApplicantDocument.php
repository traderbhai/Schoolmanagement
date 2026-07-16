<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ApplicantDocument extends Model
{
    protected $fillable = [
        'applicant_id', 'required_document_id', 'file_path', 'original_name',
        'file_size_kb', 'status', 'rejection_reason', 'verified_by', 'verified_at',
        'uploaded_at', 'version',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    public function applicant()         { return $this->belongsTo(Applicant::class); }
    public function requiredDocument()  { return $this->belongsTo(RequiredDocument::class); }
    public function verifier()          { return $this->belongsTo(User::class, 'verified_by'); }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'verified' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span>',
            'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>',
            default    => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>',
        };
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $kb = $this->file_size_kb;
        if ($kb >= 1024) {
            return number_format($kb / 1024, 1) . ' MB';
        }
        return $kb . ' KB';
    }

    public function getFileIconAttribute(): string
    {
        $ext = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        return match($ext) {
            'pdf'  => 'bi-file-pdf',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bi-file-image',
            default => 'bi-file-earmark',
        };
    }

    public function getFileAvailableAttribute(): bool
    {
        return filled($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }
}
