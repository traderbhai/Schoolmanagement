<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentRequest extends Model {
    use HasFactory;
    protected $fillable = ['student_id','document_type','purpose','additional_info','status','reviewed_by','fulfilled_at','output_path','notes'];
    protected $casts = ['fulfilled_at'=>'datetime'];
    public function student() { return $this->belongsTo(Student::class); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }

    public static function typeLabel(string $type): string {
        return match($type) {
            'bonafide'   => 'Bonafide Certificate',
            'fee_letter' => 'Fee Paid Letter',
            'character'  => 'Character Certificate',
            'migration'  => 'Migration Certificate',
            'noc'        => 'No Objection Certificate',
            'id_card'    => 'Digital ID Card',
            default      => ucfirst(str_replace('_',' ',$type)),
        };
    }
}
