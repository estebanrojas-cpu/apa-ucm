<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            foreach (['s1', 's2'] as $sem) {
                $table->decimal("hrs_real_docencia_{$sem}",       6, 2)->nullable()->after('extra_otras_actividades');
                $table->decimal("hrs_real_investigacion_{$sem}",  6, 2)->nullable()->after("hrs_real_docencia_{$sem}");
                $table->decimal("hrs_real_extension_{$sem}",      6, 2)->nullable()->after("hrs_real_investigacion_{$sem}");
                $table->decimal("hrs_real_administracion_{$sem}", 6, 2)->nullable()->after("hrs_real_extension_{$sem}");
                $table->decimal("hrs_real_otras_{$sem}",          6, 2)->nullable()->after("hrs_real_administracion_{$sem}");
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn([
                'hrs_real_docencia_s1', 'hrs_real_investigacion_s1', 'hrs_real_extension_s1',
                'hrs_real_administracion_s1', 'hrs_real_otras_s1',
                'hrs_real_docencia_s2', 'hrs_real_investigacion_s2', 'hrs_real_extension_s2',
                'hrs_real_administracion_s2', 'hrs_real_otras_s2',
            ]);
        });
    }
};
