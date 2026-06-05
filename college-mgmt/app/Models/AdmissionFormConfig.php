<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdmissionFormConfig extends Model
{
    protected $fillable = ['program_id', 'form_sections', 'is_active'];
    protected $casts = ['form_sections' => 'array'];
    public function program() { return $this->belongsTo(Program::class); }

    public static function getDefaultSections(): array
    {
        return [
            [
                'section' => 'Personal Information',
                'fields' => [
                    ['key' => 'father_name', 'label' => "Father's Name", 'type' => 'text', 'required' => true],
                    ['key' => 'mother_name', 'label' => "Mother's Name", 'type' => 'text', 'required' => false],
                    ['key' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true],
                    ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Other'], 'required' => true],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['General', 'OBC', 'SC', 'ST', 'EWS'], 'required' => true],
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text', 'required' => false],
                    ['key' => 'phone', 'label' => 'Mobile Number', 'type' => 'text', 'required' => true],
                    ['key' => 'alternate_phone', 'label' => 'Alternate Phone', 'type' => 'text', 'required' => false],
                    ['key' => 'address', 'label' => 'Permanent Address', 'type' => 'textarea', 'required' => true],
                    ['key' => 'city', 'label' => 'City', 'type' => 'text', 'required' => false],
                    ['key' => 'state', 'label' => 'State', 'type' => 'text', 'required' => false],
                    ['key' => 'pincode', 'label' => 'PIN Code', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'section' => 'Academic Background',
                'fields' => [
                    ['key' => 'tenth_school', 'label' => '10th School / Board', 'type' => 'text', 'required' => false],
                    ['key' => 'tenth_percentage', 'label' => '10th Percentage/CGPA', 'type' => 'text', 'required' => false],
                    ['key' => 'tenth_year', 'label' => '10th Passing Year', 'type' => 'text', 'required' => false],
                    ['key' => 'twelfth_school', 'label' => '12th School / Board', 'type' => 'text', 'required' => false],
                    ['key' => 'twelfth_percentage', 'label' => '12th Percentage/CGPA', 'type' => 'text', 'required' => false],
                    ['key' => 'twelfth_year', 'label' => '12th Passing Year', 'type' => 'text', 'required' => false],
                    ['key' => 'graduation_college', 'label' => 'Graduation College / University', 'type' => 'text', 'required' => true],
                    ['key' => 'graduation_degree', 'label' => 'Graduation Degree', 'type' => 'text', 'required' => true],
                    ['key' => 'graduation_percentage', 'label' => 'Graduation Percentage/CGPA', 'type' => 'text', 'required' => true],
                    ['key' => 'graduation_year', 'label' => 'Graduation Year', 'type' => 'text', 'required' => true],
                    ['key' => 'work_experience', 'label' => 'Work Experience (months)', 'type' => 'number', 'required' => false],
                    ['key' => 'entrance_exam', 'label' => 'Entrance Exam (CAT/MAT/XAT etc)', 'type' => 'text', 'required' => false],
                    ['key' => 'entrance_score', 'label' => 'Entrance Exam Score / Percentile', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'section' => 'Family Information',
                'fields' => [
                    ['key' => 'father_occupation', 'label' => "Father's Occupation", 'type' => 'text', 'required' => false],
                    ['key' => 'father_income', 'label' => "Father's Annual Income", 'type' => 'text', 'required' => false],
                    ['key' => 'mother_occupation', 'label' => "Mother's Occupation", 'type' => 'text', 'required' => false],
                    ['key' => 'guardian_name', 'label' => 'Guardian Name (if different)', 'type' => 'text', 'required' => false],
                    ['key' => 'guardian_phone', 'label' => 'Guardian Phone', 'type' => 'text', 'required' => false],
                    ['key' => 'guardian_relation', 'label' => 'Guardian Relation', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'section' => 'Additional Information',
                'fields' => [
                    ['key' => 'how_did_you_hear', 'label' => 'How did you hear about us?', 'type' => 'select', 'options' => ['Friend/Family', 'Social Media', 'Google', 'Education Fair', 'Agent/Consultant', 'Other'], 'required' => false],
                    ['key' => 'extracurricular', 'label' => 'Extra-curricular Activities', 'type' => 'textarea', 'required' => false],
                    ['key' => 'achievements', 'label' => 'Awards / Achievements', 'type' => 'textarea', 'required' => false],
                    ['key' => 'why_this_program', 'label' => 'Why do you want to join this program?', 'type' => 'textarea', 'required' => false],
                ],
            ],
        ];
    }
}
