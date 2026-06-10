<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compromisos_apa')) {
            Schema::create('compromisos_apa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->decimal('pct_docencia', 5, 2)->default(0);
            $table->decimal('pct_investigacion', 5, 2)->default(0);
            $table->decimal('pct_extension', 5, 2)->default(0);
            $table->decimal('pct_administracion', 5, 2)->default(0);
            $table->decimal('pct_otras', 5, 2)->default(0);
            $table->string('fuente', 10)->default('manual');
            $table->timestamp('confirmado_en')->nullable();
            $table->foreignUuid('modificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('modificado_en')->nullable();
            $table->timestamps();

            $table->unique('nomina_id');
        });

        DB::statement('ALTER TABLE compromisos_apa ADD CONSTRAINT compromisos_apa_pct_suma_100 CHECK (
                pct_docencia + pct_investigacion + pct_extension +
                pct_administracion + pct_otras = 100
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_apa');
    }
};
