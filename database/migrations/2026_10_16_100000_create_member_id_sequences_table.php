<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_id_sequences', function (Blueprint $table) {
            $table->uuidPrimary();
            $table->string('state_code', 2)->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('member_id_sequences')->insertOrIgnore(array_map(fn (string $code) => [
            'uuid' => (string) Str::uuid7(),
            'state_code' => $code,
            'last_sequence' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys(config('apfspn.states'))));
    }

    public function down(): void
    {
        Schema::dropIfExists('member_id_sequences');
    }
};
