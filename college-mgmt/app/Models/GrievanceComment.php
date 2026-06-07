<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GrievanceComment extends Model {
    use HasFactory;
    protected $fillable = ['student_grievance_id','user_id','comment'];
    public function grievance() { return $this->belongsTo(StudentGrievance::class,'student_grievance_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
