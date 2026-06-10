<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY[
            'admin'::text, 'analista_ccda'::text, 'secretario'::text,
            'miembro_cca'::text, 'jefe_academico'::text, 'academico'::text,
            'vicerrectora'::text
        ]))");

        if (!Schema::hasTable('comentarios_vicerrectora')) {
            Schema::create('comentarios_vicerrectora', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
                $table->text('comentario');
                $table->foreignUuid('creado_por')->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios_vicerrectora');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY[
            'admin'::text, 'analista_ccda'::text, 'secretario'::text,
            'miembro_cca'::text, 'jefe_academico'::text, 'academico'::text
        ]))");
    }
};
