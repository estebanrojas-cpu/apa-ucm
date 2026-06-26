<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30);
                $table->timestamps();

                $table->unique(['user_id', 'role']);
            });
        }

        if (DB::table('user_roles')->count() === 0) {
            DB::statement('INSERT INTO user_roles (id, user_id, role, created_at, updated_at)
                SELECT gen_random_uuid(), id, role, NOW(), NOW() FROM users WHERE role IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
