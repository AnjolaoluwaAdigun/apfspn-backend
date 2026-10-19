<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'];
        $pivotPermission = $columnNames['permission_pivot_key'];
        $morphKey = $columnNames['model_morph_key'];

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotPermission, $morphKey) {
            $table->uuid($pivotPermission);
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_uuid_model_type_index');

            $table->foreign($pivotPermission)->references('uuid')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $pivotRole, $morphKey) {
            $table->uuid($pivotRole);
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_uuid_model_type_index');

            $table->foreign($pivotRole)->references('uuid')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotRole, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->uuid($pivotPermission);
            $table->uuid($pivotRole);

            $table->foreign($pivotPermission)->references('uuid')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->foreign($pivotRole)->references('uuid')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_uuid_role_uuid_primary');
        });

        Schema::create('seeded_role_permissions', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('role_name', 125);
            $table->string('permission_name', 125);
            $table->string('guard_name', 125);
            $table->timestamp('applied_at');

            $table->unique(['role_name', 'permission_name', 'guard_name'], 'seeded_role_permissions_unique');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists('seeded_role_permissions');
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
