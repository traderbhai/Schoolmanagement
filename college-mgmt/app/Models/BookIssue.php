<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BookIssue extends Model {
    use HasFactory;
    protected $fillable = ['book_copy_id','student_id','teacher_id','issued_by','issued_at','due_date','returned_at','return_accepted_by','fine_amount','fine_paid','fine_paid_at','fine_collected_by','status'];
    protected $casts = ['fine_paid' => 'boolean', 'issued_at' => 'datetime', 'returned_at' => 'datetime', 'due_date' => 'date', 'fine_paid_at' => 'datetime'];

    public function bookCopy() { return $this->belongsTo(BookCopy::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function issuedBy() { return $this->belongsTo(User::class, 'issued_by'); }
    public function returnAcceptedBy() { return $this->belongsTo(User::class, 'return_accepted_by'); }
    public function fineCollector() { return $this->belongsTo(User::class, 'fine_collected_by'); }

    public function getIsOverdueAttribute(): bool {
        return is_null($this->returned_at) && $this->due_date < now()->toDateString();
    }
    public function getDaysOverdueAttribute(): int {
        if (!$this->is_overdue) return 0;
        return (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay());
    }
}
