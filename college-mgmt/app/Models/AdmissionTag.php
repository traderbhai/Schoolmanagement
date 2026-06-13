<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdmissionTag extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (AdmissionTag $tag) {
            $tag->slug = $tag->slug ?: Str::slug($tag->name);
        });
    }

    public function leads() { return $this->morphedByMany(Lead::class, 'taggable', 'admission_taggables')->withTimestamps(); }
    public function applicants() { return $this->morphedByMany(Applicant::class, 'taggable', 'admission_taggables')->withTimestamps(); }
}
