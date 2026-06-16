<?php

namespace App\Http\Controllers;

use App\Models\CompromisoApa;
use App\Models\ConfiguracionApa;
use App\Models\Nomina;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompromisoApaController extends Controller
{
    public function showDeclaracion(string $semestre = 'S1'): Response
    {
        if (!in_array($semestre, ['S1', 'S2'])) {
            abort(404);
        }

        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->with('semestres')->latest()->first();

        if (!$periodo) {
            return Inertia::render('Academico/DeclaracionApa', [
                'periodo'       => null,
                'nomina'        => null,
                'semestre'      => $semestre,
                'semestreLabel' => CompromisoApa::labelSemestre($semestre),
                'yaDeclarado'   => false,
                'fechaCierre'   => null,
                'datos'         => null,
                'config'        => $this->configVista(),
            ]);
        }

        $nomina = Nomina::with(['academico', 'compromisos'])
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina) {
            abort(403, 'No está incluido en la nómina del período activo.');
        }

        if (!$nomina->cargaEvidenciasHabilitada()) {
            return redirect()->route('academico.dashboard')
                ->with('error', 'El plazo de carga de evidencias no está vigente.');
        }

        $semestres = $periodo->semestres;
        $semestreData = $semestres->firstWhere('numero', $semestre === 'S1' ? 1 : 2);

        if ($semestre === 'S2') {
            $cierreS1 = $semestres->firstWhere('numero', 1)?->fecha_cierre;
            if (!$cierreS1 || !today()->isAfter($cierreS1)) {
                return redirect()->route('academico.dashboard')
                    ->with('error', 'El II Semestre estará disponible cuando cierre el I Semestre.');
            }
        }

        $compromisoExistente = CompromisoApa::where('nomina_id', $nomina->id)
            ->where('semestre', $semestre)
            ->first();

        $horasContrato = $semestre === 'S1'
            ? (float) ($user->horas_contrato_isem  ?? 0)
            : (float) ($user->horas_contrato_iisem ?? 0);

        return Inertia::render('Academico/DeclaracionApa', [
            'periodo'       => $periodo->only(['id', 'anio', 'nombre']),
            'nomina'        => ['id' => $nomina->id],
            'semestre'      => $semestre,
            'semestreLabel' => CompromisoApa::labelSemestre($semestre),
            'yaDeclarado'   => $compromisoExistente && $compromisoExistente->estaConfirmado(),
            'fechaCierre'   => $semestreData?->fecha_cierre?->format('d/m/Y'),
            // datos: hrs_* para pre-rellenar el form si el compromiso existe pero no está confirmado
            'datos'         => $compromisoExistente && !$compromisoExistente->estaConfirmado() ? [
                'hrs_docencia'       => (float) ($compromisoExistente->hrs_docencia       ?? 0),
                'hrs_investigacion'  => (float) ($compromisoExistente->hrs_investigacion  ?? 0),
                'hrs_extension'      => (float) ($compromisoExistente->hrs_extension      ?? 0),
                'hrs_administracion' => (float) ($compromisoExistente->hrs_administracion ?? 0),
                'hrs_otras'          => (float) ($compromisoExistente->hrs_otras          ?? 0),
            ] : null,
            'config'        => array_merge($this->configVista(), ['horas_contrato' => $horasContrato]),
        ]);
    }

    public function storeDeclaracion(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->with('semestres')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay período activo.');
        }

        $nomina = Nomina::with(['academico', 'compromisos'])
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$nomina->cargaEvidenciasHabilitada()) {
            return redirect()->route('academico.dashboard')
                ->with('error', 'El plazo de carga de evidencias no está vigente.');
        }

        $validated = $request->validate([
            'semestre' => ['required', 'in:S1,S2'],
        ]);

        $semestre = $validated['semestre'];

        $existente = $nomina->compromisos->firstWhere('semestre', $semestre);
        if ($existente?->estaConfirmado()) {
            return back()->with('error', 'Este semestre ya fue confirmado.');
        }

        $horasContrato = $semestre === 'S1'
            ? (float) ($user->horas_contrato_isem  ?? 0)
            : (float) ($user->horas_contrato_iisem ?? 0);

        $data = $this->validarHorasYCalcularPct($request, $horasContrato);

        CompromisoApa::updateOrCreate(
            ['nomina_id' => $nomina->id, 'periodo_id' => $periodo->id, 'semestre' => $semestre],
            array_merge($data, [
                'fuente'         => 'manual',
                'confirmado_en'  => now(),
                'modificado_por' => null,
                'modificado_en'  => null,
            ])
        );

        if ($semestre === 'S1') {
            return redirect()->route('academico.dashboard')
                ->with('success', 'I Semestre confirmado. Podrás declarar el II Semestre cuando cierre el primero.');
        }

        return redirect()->route('academico.evidencias')
            ->with('success', 'II Semestre confirmado. Ya puedes cargar evidencias.');
    }

    /**
     * Valida horas ingresadas y calcula los porcentajes correspondientes.
     * Persiste AMBOS valores (horas crudas + porcentaje calculado) para trazabilidad.
     *
     * @return array<string, float>
     */
    private function validarHorasYCalcularPct(Request $request, float $horasContrato = 0): array
    {
        $decimales = (int) ConfiguracionApa::get('decimales_pct', 2);

        $data = $request->validate([
            'hrs_docencia'       => ['required', 'numeric', 'min:0', 'max:9999'],
            'hrs_investigacion'  => ['required', 'numeric', 'min:0', 'max:9999'],
            'hrs_extension'      => ['required', 'numeric', 'min:0', 'max:9999'],
            'hrs_administracion' => ['required', 'numeric', 'min:0', 'max:9999'],
            // "Otras actividades" va fuera del 100%; lo valida el CCDA, no el académico.
            'hrs_otras'          => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        $totalHrs = (float) $data['hrs_docencia'] + (float) $data['hrs_investigacion']
                  + (float) $data['hrs_extension'] + (float) $data['hrs_administracion'];

        if ($totalHrs <= 0) {
            throw ValidationException::withMessages([
                'hrs_docencia' => 'Debe ingresar horas en al menos un área.',
            ]);
        }

        if ($horasContrato > 0 && abs($totalHrs - $horasContrato) > 0.01) {
            throw ValidationException::withMessages([
                'hrs_docencia' => "El total de horas ({$totalHrs}h) debe ser exactamente igual a las horas de contrato del semestre ({$horasContrato}h).",
            ]);
        }

        $pcts = CompromisoApa::calcularPorcentajesDesdeHoras([
            'docencia'       => (float) $data['hrs_docencia'],
            'investigacion'  => (float) $data['hrs_investigacion'],
            'extension'      => (float) $data['hrs_extension'],
            'administracion' => (float) $data['hrs_administracion'],
        ], $decimales);

        return [
            // Horas crudas (trazabilidad / auditoría)
            'hrs_docencia'       => round((float) $data['hrs_docencia'],       2),
            'hrs_investigacion'  => round((float) $data['hrs_investigacion'],  2),
            'hrs_extension'      => round((float) $data['hrs_extension'],      2),
            'hrs_administracion' => round((float) $data['hrs_administracion'], 2),
            'hrs_otras'          => round((float) ($data['hrs_otras'] ?? 0),   2),
            // Porcentajes calculados (alimentan la fórmula de nota)
            'pct_docencia'       => $pcts['pct_docencia'],
            'pct_investigacion'  => $pcts['pct_investigacion'],
            'pct_extension'      => $pcts['pct_extension'],
            'pct_administracion' => $pcts['pct_administracion'],
            'pct_otras'          => 0,
        ];
    }

    /** @return array{horas_semestre_base: float, decimales_pct: int} */
    private function configVista(): array
    {
        return [
            'horas_semestre_base' => (float) ConfiguracionApa::get('horas_semestre_base', 40),
            'decimales_pct'       => (int)   ConfiguracionApa::get('decimales_pct', 2),
        ];
    }
}
