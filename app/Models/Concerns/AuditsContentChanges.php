<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait AuditsContentChanges
{
    protected ?array $auditChangeSet = null;

    private static array $auditStampColumns = [];

    public static function bootAuditsContentChanges(): void
    {
        static::creating(function (Model $model) {
            $adminUuid = app(AuditLogService::class)->currentAdminUuid();

            foreach (['created_by_admin_uuid', 'updated_by_admin_uuid'] as $column) {
                if ($adminUuid && $model->hasAuditStampColumn($column)) {
                    $model->setAttribute($column, $adminUuid);
                }
            }
        });

        static::updating(function (Model $model) {
            $service = app(AuditLogService::class);
            $adminUuid = $service->currentAdminUuid();

            if ($adminUuid && $model->hasAuditStampColumn('updated_by_admin_uuid')) {
                $model->setAttribute('updated_by_admin_uuid', $adminUuid);
            }

            $model->auditChangeSet = $service->changeSet($model);
        });

        static::created(fn (Model $model) => app(AuditLogService::class)->record($model->auditAction('created'), $model));

        static::updated(function (Model $model) {
            app(AuditLogService::class)->record($model->auditAction('updated'), $model, $model->auditChangeSet);
            $model->auditChangeSet = null;
        });

        static::deleted(fn (Model $model) => app(AuditLogService::class)->record($model->auditAction('deleted'), $model));
    }

    public function auditAction(string $event): string
    {
        return Str::snake(class_basename($this)).'.'.$event;
    }

    public function hasAuditStampColumn(string $column): bool
    {
        $table = $this->getTable();

        self::$auditStampColumns[$table] ??= array_intersect(
            ['created_by_admin_uuid', 'updated_by_admin_uuid'],
            $this->getConnection()->getSchemaBuilder()->getColumnListing($table),
        );

        return in_array($column, self::$auditStampColumns[$table], true);
    }
}
