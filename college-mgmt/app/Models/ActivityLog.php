<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id','action','model_type','model_id','description','ip_address'];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, string $description, $model = null): void
    {
        try {
            self::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model?->id,
                'description' => $description,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // never break the main flow
        }
    }
}
