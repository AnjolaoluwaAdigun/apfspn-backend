<?php

use App\Enums\EventAudience;
use App\Enums\EventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('title');
            $table->string('slug', 150)->unique();
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->string('venue')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_code', 2)->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->string('virtual_link', 500)->nullable();
            $table->string('audience', 20)->default(EventAudience::Public->value);
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->json('schedule')->nullable();
            $table->string('status', 20)->default(EventStatus::Draft->value);
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('created_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->foreignUuid('updated_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
