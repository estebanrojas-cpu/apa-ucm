<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            if (!Schema::hasColumn('nominas', 'facultad_id')) {
                $table->foreignUuid('facultad_id')->nullable()->after('user_id')->constrained('facultades')->nullOnDelete();
            }
            if (!Schema::hasColumn('nominas', 'categoria')) {
                $table->string('categoria', 20)->nullable()->after('facultad_id');
            }
            if (!Schema::hasColumn('nominas', 'horas_contrato')) {
                $table->unsignedSmallInteger('horas_contrato')->nullable()->after('categoria');
            }
            if (!Schema::hasColumn('nominas', 'pct_docencia')) {
                $table->decimal('pct_docencia', 5, 2)->nullable()->after('horas_contrato');
                $table->decimal('pct_investigacion', 5, 2)->nullable()->after('pct_docencia');
                $table->decimal('pct_extension', 5, 2)->nullable()->after('pct_investigacion');
                $table->decimal('pct_administracion', 5, 2)->nullable()->after('pct_extension');
                $table->decimal('pct_otras', 5, 2)->nullable()->after('pct_administracion');
            }
            if (!Schema::hasColumn('nominas', 'datos_adicionales')) {
                $table->json('datos_adicionales')->nullable()->after('pct_otras');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facultad_id');
            $table->dropColumn([
                'categoria', 'horas_contrato',
                'pct_docencia', 'pct_investigacion', 'pct_extension',
                'pct_administracion', 'pct_otras', 'datos_adicionales',
            ]);
        });
    }
};
