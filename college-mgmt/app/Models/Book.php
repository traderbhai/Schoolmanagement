<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model {
    use HasFactory;
    protected $fillable = ['isbn','title','author','publisher','edition','year_of_publication','category','subject_tags','language','total_copies','available_copies','location','cover_image','description','is_active'];
    protected $casts = ['subject_tags' => 'array', 'is_active' => 'boolean'];

    public function copies() { return $this->hasMany(BookCopy::class); }
    public function availableCopies() { return $this->hasMany(BookCopy::class)->where('is_available', true); }
    public function issues() { return $this->hasManyThrough(BookIssue::class, BookCopy::class); }
    public function reservations() { return $this->hasMany(LibraryReservation::class); }
}
