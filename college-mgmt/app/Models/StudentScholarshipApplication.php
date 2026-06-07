<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentScholarshipApplication extends Model {
    use HasFactory;
    protected $fillable = ['student_id','scholarship_scheme_id','cgpa_at_application','reason','documents_path','status','reviewed_by','review_note','disbursed_at','disbursed_amount'];
    protected $casts = ['disbursed_at'=>'datetime','cgpa_at_application'=>'decimal:2','disbursed_amount'=>'decimal:2'];
    public function student() { return $this->belongsTo(Student::class); }
    public function scheme() { return $this->belongsTo(ScholarshipScheme::class,'scholarship_scheme_id'); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
}
