<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProgramSpecificOutcome extends Model {
    use HasFactory;
    protected $fillable = ['program_id','code','description','is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function program() { return $this->belongsTo(Program::class); }
}
