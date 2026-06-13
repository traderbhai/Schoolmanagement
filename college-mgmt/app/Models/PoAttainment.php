<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PoAttainment extends Model {
    use HasFactory;
    protected $fillable = ['program_outcome_id','program_id','term_id','attainment_value','target_value','target_met'];
    protected $casts = ['target_met' => 'boolean', 'attainment_value' => 'decimal:2', 'target_value' => 'decimal:2'];
    public function programOutcome() { return $this->belongsTo(ProgramOutcome::class); }
    public function program()        { return $this->belongsTo(Program::class); }
    public function term()           { return $this->belongsTo(Term::class); }
}
