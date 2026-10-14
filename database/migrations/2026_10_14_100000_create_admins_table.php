<?php

use App\Enums\AdminStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 20)->default(AdminStatus::Active->value)->index();
            $table->boolean('must_reset_password')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->uuid('created_by_admin_uuid')->nullable();
            $table->uuid('updated_by_admin_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
