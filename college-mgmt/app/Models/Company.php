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
}
