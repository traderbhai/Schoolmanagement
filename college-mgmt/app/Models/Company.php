<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name','industry','website','contact_person','contact_email','contact_phone','description','logo_url','is_active'];

    public function drives()
    {
        return $this->hasMany(PlacementDrive::class);
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    public function hasOperationalHistory(): bool
    {
        return $this->drives()->exists() || $this->internships()->exists();
    }

    public function hasActivePlacementDrives(): bool
    {
        return $this->drives()->whereIn('status', ['upcoming', 'ongoing'])->exists();
    }
}
