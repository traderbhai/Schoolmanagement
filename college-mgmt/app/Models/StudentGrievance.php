<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrievance extends Model {
    protected $fillable = ['student_id','program_id','category','title','description',
        'status','priority','assigned_to','resolved_by','resolution_notes','resolved_at'];
    protected $casts = ['resolved_at'=>'datetime'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class,'resolved_by'); }
    public function comments() { return $this->hasMany(GrievanceComment::class,"student_grievance_id")->with("user")->orderBy("created_at"); }
    public function isOpen(): bool { return in_array($this->status,['open','under_review','escalated']); }
    public function isOverdue(): bool { return $this->isOpen() && $this->created_at->lt(now()->subDays(7)); }
    public function getStatusBadgeAttribute(): string {
        $color = match($this->status) {
            'open' => 'warning',
            'under_review' => 'info',
            'escalated' => 'danger',
            'resolved' => 'success',
            'closed' => 'secondary',
            default => 'secondary',
        };
        $label = ucwords(str_replace('_', ' ', $this->status));
        return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
    }
    public function getPriorityBadgeAttribute(): string {
        $color = match($this->priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'normal' => 'primary',
            'low' => 'secondary',
            default => 'secondary',
        };
        return '<span class="badge bg-' . $color . '">' . e(ucfirst($this->priority)) . '</span>';
    }
}
