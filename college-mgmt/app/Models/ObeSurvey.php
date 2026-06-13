<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ObeSurvey extends Model {
    use HasFactory;
    protected $fillable = ['subject_id','term_id','title','is_published','closes_at'];
    protected $casts = ['is_published' => 'boolean', 'closes_at' => 'date'];
    public function subject()   { return $this->belongsTo(Subject::class); }
    public function term()      { return $this->belongsTo(Term::class); }
    public function responses() { return $this->hasMany(ObeSurveyResponse::class); }
}
