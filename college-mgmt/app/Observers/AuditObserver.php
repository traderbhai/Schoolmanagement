<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that logs create/update/delete events for any model.
 * Register in AppServiceProvider for models that need audit trails.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        if (!auth()->check()) return;
        AuditLog::log('created', $model, $this->relevant($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        if (!auth()->check()) return;
        $dirty = $model->getDirty();
        if (empty($dirty)) return;

        $changes = [];
        foreach ($dirty as $key => $new) {
            $old = $model->getOriginal($key);
            if ($old !== $new) {
                $changes[$key] = ['from' => $old, 'to' => $new];
            }
        }
        if (!empty($changes)) {
            AuditLog::log('updated', $model, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        if (!auth()->check()) return;
        AuditLog::log('deleted', $model, ['id' => $model->getKey()]);
    }

    private function relevant(array $attrs): array
    {
        $skip = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        return array_diff_key($attrs, array_flip($skip));
    }
}
