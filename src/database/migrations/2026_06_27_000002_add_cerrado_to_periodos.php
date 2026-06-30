<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->timestamp('cerrado_en')->nullable()->after('fecha_cierre');
            $table->foreignUuid('cerrado_por')->nullable()->after('cerrado_en')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrado_por');
            $table->dropColumn('cerrado_en');
        });
    }
};
