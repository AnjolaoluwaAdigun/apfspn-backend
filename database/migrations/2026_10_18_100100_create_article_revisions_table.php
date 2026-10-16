<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('article_uuid')->constrained('articles', 'uuid')->cascadeOnDelete();
            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->foreignUuid('edited_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_uuid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
