<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY[
            'super_admin'::text, 'admin'::text, 'analista_ccda'::text, 'secretario'::text,
            'miembro_cca'::text, 'jefe_academico'::text, 'director_departamento'::text,
            'decano'::text, 'academico'::text, 'vicerrectora'::text
        ]))");

        Schema::create('asignaciones_cargo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->foreignUuid('facultad_id')->constrained('facultades')->cascadeOnDelete();
            $table->foreignUuid('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->string('slot', 40);
            $table->string('cargo', 40);
            $table->foreignUuid('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodo_id', 'facultad_id', 'slot']);
            $table->index(['periodo_id', 'nomina_id']);
        });

        Schema::create('historial_cargos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->foreignUuid('facultad_id')->nullable()->constrained('facultades')->nullOnDelete();
            $table->string('cargo', 40);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_cargos');
        Schema::dropIfExists('asignaciones_cargo');

        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY[
            'admin'::text, 'analista_ccda'::text, 'secretario'::text, 'miembro_cca'::text,
            'jefe_academico'::text, 'academico'::text, 'vicerrectora'::text
        ]))");
    }
};
