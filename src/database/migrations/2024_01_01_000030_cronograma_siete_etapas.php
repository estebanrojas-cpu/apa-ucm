<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cronogramas DROP CONSTRAINT IF EXISTS cronogramas_etapa_check');

        DB::table('cronogramas')
            ->where('etapa', 'evaluacion_secretario')
            ->update(['etapa' => 'validacion_secretario']);

        DB::table('cronogramas')
            ->where('etapa', 'evaluacion_jefatura')
            ->delete();

        DB::statement("ALTER TABLE cronogramas ADD CONSTRAINT cronogramas_etapa_check CHECK (etapa::text = ANY (ARRAY[
            'carga_evidencias'::text,
            'validacion_secretario'::text,
            'evaluacion_cca'::text,
            'consejo_facultad'::text,
            'apelaciones'::text,
            'revision_vicerrectoria'::text,
            'cierre'::text
        ]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cronogramas DROP CONSTRAINT IF EXISTS cronogramas_etapa_check');

        DB::table('cronogramas')
            ->where('etapa', 'validacion_secretario')
            ->update(['etapa' => 'evaluacion_secretario']);

        DB::statement("ALTER TABLE cronogramas ADD CONSTRAINT cronogramas_etapa_check CHECK (etapa::text = ANY (ARRAY[
            'carga_evidencias'::text,
            'evaluacion_secretario'::text,
            'evaluacion_cca'::text,
            'apelaciones'::text,
            'evaluacion_jefatura'::text,
            'cierre'::text
        ]))");
    }
};
