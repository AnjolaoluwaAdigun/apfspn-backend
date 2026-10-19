<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_activities', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('subject_type')->nullable();
            $table->uuid('subject_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_uuid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_activities');
    }
};
