<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropUnique(['periodo_id', 'user_id']);
        });

        DB::statement('ALTER TABLE nominas ALTER COLUMN user_id DROP NOT NULL');

        Schema::table('nominas', function (Blueprint $table) {
            $table->unique(['periodo_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropUnique(['periodo_id', 'rut']);
        });

        DB::statement('ALTER TABLE nominas ALTER COLUMN user_id SET NOT NULL');

        Schema::table('nominas', function (Blueprint $table) {
            $table->unique(['periodo_id', 'user_id']);
        });
    }
};
