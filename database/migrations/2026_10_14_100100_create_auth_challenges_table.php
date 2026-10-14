<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_challenges', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('subject_type');
            $table->uuid('subject_uuid')->nullable();
            $table->char('email_hash', 64);
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('send_count')->default(1);
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'email_hash', 'purpose']);
            $table->index(['subject_type', 'subject_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_challenges');
    }
};
