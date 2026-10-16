<?php

use App\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('user_uuid')->unique()->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('member_type', 20)->index();
            $table->string('state_code', 2);
            $table->string('member_id', 20)->nullable()->unique();
            $table->string('status', 20)->default(MembershipStatus::PendingApproval->value)->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
