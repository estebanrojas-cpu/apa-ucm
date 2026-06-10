<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_calificaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->decimal('nota', 3, 2)->nullable();
            $table->string('concepto', 30)->nullable();
            $table->text('observacion')->nullable();
            $table->text('resumen')->nullable();
            $table->string('proceso', 100)->nullable();
            $table->string('informe_path', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['nomina_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_calificaciones');
    }
};
