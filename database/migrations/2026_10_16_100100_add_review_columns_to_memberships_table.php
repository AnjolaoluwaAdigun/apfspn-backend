<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUuid('rejected_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignUuid('suspended_by_admin_uuid')->nullable()->constrained('admins', 'uuid')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropConstrainedForeignId('rejected_by_admin_uuid');
            $table->dropConstrainedForeignId('suspended_by_admin_uuid');
            $table->dropColumn(['rejected_at', 'suspended_at', 'suspension_reason', 'reactivated_at']);
        });
    }
};
