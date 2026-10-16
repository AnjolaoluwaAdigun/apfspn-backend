<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_profiles', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->foreignUuid('user_uuid')->unique()->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->string('registration_number', 100);
            $table->unsignedSmallInteger('year_established')->nullable();
            $table->string('business_type', 100)->nullable();
            $table->string('institution_type', 100)->nullable();
            $table->string('department')->nullable();
            $table->string('address', 500);
            $table->string('state_code', 2)->index();
            $table->string('city', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('document_public_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_profiles');
    }
};
