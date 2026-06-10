<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluaciones', 'vigente_hasta')) {
                $table->date('vigente_hasta')->nullable()->after('comentario');
            }
            if (!Schema::hasColumn('evaluaciones', 'sin_calificacion')) {
                $table->boolean('sin_calificacion')->default(false)->after('vigente_hasta');
            }
            if (!Schema::hasColumn('evaluaciones', 'motivo_sc')) {
                $table->text('motivo_sc')->nullable()->after('sin_calificacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn(['vigente_hasta', 'sin_calificacion', 'motivo_sc']);
        });
    }
};
