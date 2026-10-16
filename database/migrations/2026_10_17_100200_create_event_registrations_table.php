<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('event_uuid')->constrained('events', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['event_uuid', 'user_uuid']);
            $table->index(['event_uuid', 'status']);
            $table->index(['event_uuid', 'waitlist_position']);
            $table->index(['user_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
