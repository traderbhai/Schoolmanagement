<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScoringParameter extends Model
{
    protected $fillable = ['selection_process_step_id', 'name', 'max_score', 'description', 'sort_order'];
    public function step() { return $this->belongsTo(SelectionProcessStep::class, 'selection_process_step_id'); }
}
