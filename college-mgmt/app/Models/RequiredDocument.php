<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RequiredDocument extends Model
{
    protected $fillable = ['program_id', 'name', 'description', 'is_mandatory', 'accepted_formats', 'max_size_kb', 'sort_order', 'is_active'];
    protected $casts = ['is_mandatory' => 'boolean', 'is_active' => 'boolean'];
    public function program() { return $this->belongsTo(Program::class); }

    public static function defaults(): array
    {
        return [
            ['name' => 'Recent Passport Size Photograph', 'description' => 'Clear photograph, white background, taken within 3 months', 'is_mandatory' => true, 'accepted_formats' => 'jpg,png', 'sort_order' => 1],
            ['name' => '10th Marksheet', 'description' => 'Marksheet / Certificate from recognized board', 'is_mandatory' => true, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 2],
            ['name' => '12th Marksheet', 'description' => 'Marksheet / Certificate from recognized board', 'is_mandatory' => true, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 3],
            ['name' => 'Graduation Marksheet (all semesters)', 'description' => 'All semester marksheets of graduation', 'is_mandatory' => true, 'accepted_formats' => 'pdf', 'sort_order' => 4],
            ['name' => 'Graduation Degree / Provisional Certificate', 'description' => 'Degree certificate or provisional if awaited', 'is_mandatory' => true, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 5],
            ['name' => 'Aadhaar Card', 'description' => 'Government issued ID proof', 'is_mandatory' => true, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 6],
            ['name' => 'CAT / MAT / XAT Scorecard', 'description' => 'Entrance examination scorecard (if applicable)', 'is_mandatory' => false, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 7],
            ['name' => 'Work Experience Certificate', 'description' => 'Experience letter from previous employer (if applicable)', 'is_mandatory' => false, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 8],
            ['name' => 'Category Certificate (SC/ST/OBC/EWS)', 'description' => 'Required only for reserved category candidates', 'is_mandatory' => false, 'accepted_formats' => 'pdf,jpg,png', 'sort_order' => 9],
        ];
    }
}
