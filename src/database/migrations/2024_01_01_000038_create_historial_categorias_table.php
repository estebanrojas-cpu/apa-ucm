<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->string('categoria', 30);
            $table->date('fecha_categorizacion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['nomina_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_categorias');
    }
};
