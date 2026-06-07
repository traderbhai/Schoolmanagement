<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentResume extends Model {
    use HasFactory;
    protected $fillable = ['student_id','headline','objective','skills','languages','projects','certifications','experience','linkedin_url','github_url','portfolio_url','is_complete'];
    protected $casts = ['skills'=>'array','languages'=>'array','projects'=>'array','certifications'=>'array','experience'=>'array','is_complete'=>'boolean'];
    public function student() { return $this->belongsTo(Student::class); }
}
