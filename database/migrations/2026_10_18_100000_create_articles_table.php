<?php

use App\Enums\ArticleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('title');
            $table->string('slug', 150)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('cover_image_url')->nullable();
            $table->string('category', 20)->index();
            $table->json('tags')->nullable();
            $table->string('status', 20)->default(ArticleStatus::Draft->value);
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignUuid('author_admin_uuid')->nullable()->index()->constrained('admins', 'uuid')->nullOnDelete();
            $table->foreignUuid('reviewed_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->foreignUuid('created_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->foreignUuid('updated_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
