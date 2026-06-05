<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SelectionProcessStep extends Model
{
    protected $fillable = ['program_id', 'name', 'type', 'step_order', 'max_score', 'weightage', 'instructions', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }
    public function scoringParameters() { return $this->hasMany(ScoringParameter::class)->orderBy('sort_order'); }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'gd' => 'Group Discussion',
            'pi' => 'Personal Interview',
            'wat' => 'Written Ability Test',
            'written_test' => 'Written Test',
            'aptitude' => 'Aptitude Test',
            'presentation' => 'Presentation',
            default => ucfirst($this->type),
        };
    }
}
