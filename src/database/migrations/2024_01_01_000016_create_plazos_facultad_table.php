<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazos_facultad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->foreignUuid('facultad_id')->constrained('facultades')->cascadeOnDelete();
            $table->date('fecha_limite');
            $table->foreignUuid('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['periodo_id', 'facultad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazos_facultad');
    }
};
