<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    protected static bool $auditingDisabled = false;

    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event): void {
                if (static::$auditingDisabled || ! auth()->check()) {
                    return;
                }

                $oldValues = match ($event) {
                    'created' => null,
                    'deleted' => $model->getOriginal(),
                    default => $model->getChanges() ? array_intersect_key($model->getOriginal(), $model->getChanges()) : null,
                };

                $newValues = match ($event) {
                    'deleted' => null,
                    'created' => $model->getAttributes(),
                    default => $model->getChanges() ?: null,
                };

                if ($event === 'updated' && ! $newValues) {
                    return;
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'impersonated_user_id' => session('impersonating'),
                    'action' => $event,
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

    public static function disableAuditing(): void
    {
        static::$auditingDisabled = true;
    }

    public static function enableAuditing(): void
    {
        static::$auditingDisabled = false;
    }

    public static function withoutAuditing(callable $callback): mixed
    {
        static::disableAuditing();

        try {
            return $callback();
        } finally {
            static::enableAuditing();
        }
    }
}
