<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropColumn([
                'pct_docencia', 'pct_investigacion', 'pct_extension',
                'pct_administracion', 'pct_otras',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->decimal('pct_docencia', 5, 2)->nullable()->after('horas_contrato');
            $table->decimal('pct_investigacion', 5, 2)->nullable()->after('pct_docencia');
            $table->decimal('pct_extension', 5, 2)->nullable()->after('pct_investigacion');
            $table->decimal('pct_administracion', 5, 2)->nullable()->after('pct_extension');
            $table->decimal('pct_otras', 5, 2)->nullable()->after('pct_administracion');
        });
    }
};
