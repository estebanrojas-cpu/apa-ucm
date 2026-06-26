<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_cca', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->foreignUuid('facultad_id')->constrained('facultades')->cascadeOnDelete();
            $table->enum('estado', ['borrador', 'confirmada'])->default('borrador');
            $table->foreignUuid('designado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamps();

            $table->unique(['periodo_id', 'facultad_id']);
        });

        Schema::create('comision_integrantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comision_cca_id')->constrained('comisiones_cca')->cascadeOnDelete();
            $table->foreignUuid('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comision_cca_id', 'nomina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_integrantes');
        Schema::dropIfExists('comisiones_cca');
    }
};
