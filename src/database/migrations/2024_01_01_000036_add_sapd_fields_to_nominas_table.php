<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->string('numero_personal', 20)->nullable()->after('user_id');
            $table->string('rut', 12)->nullable()->after('numero_personal');
            $table->string('nombre', 255)->nullable()->after('rut');
            $table->string('adscripcion_academica', 100)->nullable()->after('nombre');
            $table->string('unidad_superior', 150)->nullable()->after('adscripcion_academica');
            $table->string('unidad', 150)->nullable()->after('unidad_superior');
            $table->string('nombre_posicion', 150)->nullable()->after('unidad');
            $table->string('tipo_trabajador', 50)->nullable()->after('nombre_posicion');
            $table->date('fecha_inicio_contrato')->nullable()->after('tipo_trabajador');
            $table->unsignedSmallInteger('horas_contrato')->nullable()->after('fecha_inicio_contrato');
            $table->string('categoria', 30)->nullable()->after('horas_contrato');
            $table->date('fecha_categorizacion')->nullable()->after('categoria');
            $table->jsonb('datos_adicionales')->nullable()->after('fecha_categorizacion');
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropColumn([
                'numero_personal', 'rut', 'nombre', 'adscripcion_academica',
                'unidad_superior', 'unidad', 'nombre_posicion', 'tipo_trabajador',
                'fecha_inicio_contrato', 'horas_contrato', 'categoria',
                'fecha_categorizacion', 'datos_adicionales',
            ]);
        });
    }
};
