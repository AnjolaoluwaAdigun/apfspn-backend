<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_speakers', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('event_uuid')->constrained('events', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_uuid', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_speakers');
    }
};
