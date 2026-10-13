<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->uuid('admin_uuid')->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_uuid')->nullable();
            $table->index(['auditable_type', 'auditable_uuid']);
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_body')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
