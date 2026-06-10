<?php

namespace App\Http\Controllers;

use App\Models\CompromisoApa;
use App\Models\Nomina;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompromisoApaController extends Controller
{
    public function showDeclaracion(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return Inertia::render('Academico/DeclaracionApa', [
                'periodo'    => null,
                'nomina'     => null,
                'compromiso' => null,
            ]);
        }

        $nomina = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina) {
            abort(403, 'No está incluido en la nómina del período activo.');
        }

        if (!$nomina->cargaEvidenciasHabilitada()) {
            return redirect()->route('academico.dashboard')
                ->with('error', 'El plazo de carga de evidencias no está vigente. La declaración APA solo aplica durante esa etapa.');
        }

        $compromiso = CompromisoApa::where('nomina_id', $nomina->id)->first();

        if ($compromiso?->estaConfirmado()) {
            return redirect()->route('academico.evidencias');
        }

        return Inertia::render('Academico/DeclaracionApa', [
            'periodo'    => $periodo->only(['id', 'anio', 'nombre']),
            'nomina'     => ['id' => $nomina->id],
            'compromiso' => $compromiso ? [
                'pct_docencia'       => (float) $compromiso->pct_docencia,
                'pct_investigacion'  => (float) $compromiso->pct_investigacion,
                'pct_extension'      => (float) $compromiso->pct_extension,
                'pct_administracion' => (float) $compromiso->pct_administracion,
                'pct_otras'          => (float) $compromiso->pct_otras,
            ] : null,
        ]);
    }

    public function storeDeclaracion(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay período activo.');
        }

        $nomina = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$nomina->cargaEvidenciasHabilitada()) {
            return redirect()->route('academico.dashboard')
                ->with('error', 'El plazo de carga de evidencias no está vigente.');
        }

        if (CompromisoApa::where('nomina_id', $nomina->id)->whereNotNull('confirmado_en')->exists()) {
            return redirect()->route('academico.evidencias')
                ->with('error', 'Su distribución APA ya fue confirmada.');
        }

        $data = $this->validarPorcentajes($request);

        CompromisoApa::updateOrCreate(
            ['nomina_id' => $nomina->id],
            array_merge($data, [
                'periodo_id'    => $periodo->id,
                'fuente'        => 'manual',
                'confirmado_en' => now(),
                'modificado_por'=> null,
                'modificado_en' => null,
            ])
        );

        return redirect()->route('academico.evidencias')
            ->with('success', 'Distribución APA confirmada. Ya puede cargar evidencias.');
    }

    /** @return array<string, float> */
    private function validarPorcentajes(Request $request): array
    {
        $data = $request->validate([
            'pct_docencia'       => ['required', 'numeric', 'min:0', 'max:100'],
            'pct_investigacion'  => ['required', 'numeric', 'min:0', 'max:100'],
            'pct_extension'      => ['required', 'numeric', 'min:0', 'max:100'],
            'pct_administracion' => ['required', 'numeric', 'min:0', 'max:100'],
            'pct_otras'          => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $suma = collect($data)->sum(fn ($v) => (float) $v);

        if (abs($suma - 100) > 0.01) {
            throw ValidationException::withMessages([
                'pct_docencia' => "Los porcentajes deben sumar exactamente 100% (actual: {$suma}%).",
            ]);
        }

        return array_map(fn ($v) => round((float) $v, 2), $data);
    }
}
