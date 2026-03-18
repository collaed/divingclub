<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                if (!auth()->check()) return;

                $action = match($event) {
                    'created' => 'created',
                    'updated' => 'updated',
                    'deleted' => 'deleted',
                };

                $oldValues = $event === 'created' ? null : ($event === 'deleted' ? $model->getOriginal() : ($model->getChanges() ? array_intersect_key($model->getOriginal(), $model->getChanges()) : null));
                $newValues = $event === 'deleted' ? null : ($event === 'created' ? $model->getAttributes() : ($model->getChanges() ?: null));

                if ($event === 'updated' && !$newValues) return;

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'impersonated_user_id' => session('impersonating'),
                    'action' => $action,
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });
        }
    }
}
