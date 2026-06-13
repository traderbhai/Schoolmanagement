<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SelectionProcessStep extends Model
{
    protected $fillable = ['program_id', 'name', 'type', 'step_order', 'max_score', 'weightage', 'instructions', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }
    public function scoringParameters() { return $this->hasMany(ScoringParameter::class)->orderBy('sort_order'); }
    public function sessions() { return $this->hasMany(SelectionSession::class); }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'gd' => 'Group Discussion',
            'pi' => 'Personal Interview',
            'case_analysis' => 'Case Analysis',
            'wat' => 'Written Ability Test',
            'written_test' => 'Written Test',
            'aptitude' => 'Aptitude Test',
            'presentation' => 'Presentation',
            'portfolio_review' => 'Portfolio Review',
            'screening_call' => 'Screening Call',
            default => ucfirst($this->type),
        };
    }
}
