<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->foreignUuid('facultad_id')->nullable()->constrained('facultades')->nullOnDelete();
            $table->foreignUuid('generado_por')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 40)->default('calificaciones');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_historial');
    }
};
