<?php

namespace App\Http\Controllers;

use App\Models\CategoriaApa;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenciaController extends Controller
{
    public function index(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $nomina     = null;
        $plazo      = null;
        $categorias = CategoriaApa::orderBy('orden')->get();

        if ($periodo) {
        $nomina = Nomina::with(['evidenciasNormales.categoria', 'evidenciasApelacion.categoria', 'apelacion', 'academico'])
                ->where('periodo_id', $periodo->id)
                ->where('user_id', $user->id)
                ->first();

            $plazoModel = PlazoFacultad::where('periodo_id', $periodo->id)
                ->where('facultad_id', $user->facultad_id)
                ->first();

            if ($plazoModel) {
                $plazo = [
                    'fecha_limite' => $plazoModel->fecha_limite->format('Y-m-d'),
                    'vigente'      => $plazoModel->estaVigente(),
                    'cerrado'      => $plazoModel->estaCerradoFormalmente(),
                    'cerrado_en'   => $plazoModel->cerrado_en?->format('d/m/Y H:i'),
                ];
            }
        }

        $puedeCargar = $nomina && $nomina->cargaEvidenciasHabilitada();

        $evidenciasPorCategoria = [];
        if ($nomina) {
            foreach ($nomina->evidenciasNormales as $ev) {
                $evidenciasPorCategoria[$ev->categoria_id][] = [
                    'id'             => $ev->id,
                    'nombre_archivo' => $ev->nombre_archivo,
                    'tamano'         => $ev->tamanoFormateado(),
                    'descripcion'    => $ev->descripcion,
                    'created_at'     => $ev->created_at->format('d/m/Y H:i'),
                    'url_descarga'   => route('academico.evidencias.download', $ev->id),
                ];
            }
        }

        $apelacion = $nomina?->apelacion;
        $puedeCargarApelacion = $nomina
            && $nomina->estado === 'apelado'
            && $apelacion
            && $apelacion->estado === 'en_revision';

        $evidenciasApelacionPorCategoria = [];
        if ($nomina) {
            foreach ($nomina->evidenciasApelacion as $ev) {
                $evidenciasApelacionPorCategoria[$ev->categoria_id][] = [
                    'id'             => $ev->id,
                    'nombre_archivo' => $ev->nombre_archivo,
                    'tamano'         => $ev->tamanoFormateado(),
                    'descripcion'    => $ev->descripcion,
                    'created_at'     => $ev->created_at->format('d/m/Y H:i'),
                    'url_descarga'   => route('academico.evidencias.download', $ev->id),
                ];
            }
        }

        return Inertia::render('Academico/Evidencias', [
            'periodo'                         => $periodo?->only(['id', 'anio', 'nombre']),
            'nomina'                          => $nomina ? [
                'id'                     => $nomina->id,
                'estado'                 => $nomina->estado,
                'con_licencia'           => $nomina->con_licencia,
                'observacion_licencia'   => $nomina->observacion_licencia,
                'plazo_licencia'         => $nomina->plazo_licencia?->format('Y-m-d'),
                'observacion_secretario' => $nomina->observacion_secretario,
            ] : null,
            'plazo'                           => $plazo,
            'puedeCargar'                     => $puedeCargar,
            'puedeCargarApelacion'            => $puedeCargarApelacion,
            'apelacion'                       => $apelacion ? [
                'estado'     => $apelacion->estado,
                'resolucion' => $apelacion->resolucion,
            ] : null,
            'categorias'                      => $categorias->map(fn ($c) => [
                'id'          => $c->id,
                'nombre'      => $c->nombre,
                'slug'        => $c->slug,
                'descripcion' => $c->descripcion,
            ]),
            'evidenciasPorCategoria'          => $evidenciasPorCategoria,
            'evidenciasApelacionPorCategoria' => $evidenciasApelacionPorCategoria,
        ]);
    }

    public function store(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay un período activo.');
        }

        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina) {
            return back()->with('error', 'No está en la nómina del período activo.');
        }

        if (!$nomina->puedeCargarEvidencias()) {
            return back()->with('error', 'Su expediente no permite carga de evidencias en este momento.');
        }

        $plazoRecord = PlazoFacultad::where('periodo_id', $periodo->id)
            ->where('facultad_id', $user->facultad_id)
            ->first();

        if ($plazoRecord?->estaCerradoFormalmente()) {
            return back()->with('error', 'La recepción de evidencias fue cerrada formalmente. No se aceptan nuevas cargas.');
        }

        if ($nomina->plazo_licencia && $nomina->plazo_licencia->toDateString() >= now()->toDateString()) {
            // Plazo individual (reincorporación o extensión): independiente del plazo de facultad
        } elseif ($nomina->con_licencia) {
            return back()->with('error', 'No tiene un plazo especial activo. Contacte a su secretario de facultad.');
        } elseif ($plazoRecord && !$plazoRecord->estaVigente()) {
            return back()->with('error', 'El plazo de carga de evidencias ha vencido.');
        }

        $data = $request->validate([
            'categoria_id' => ['required', 'exists:categorias_apa,id'],
            'archivo'      => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
        ], [
            'categoria_id.required' => 'Debe indicar la categoría.',
            'categoria_id.exists'   => 'Categoría no válida.',
            'archivo.required'      => 'Debe seleccionar un archivo.',
            'archivo.max'           => 'El archivo no puede superar los 10 MB.',
            'archivo.mimes'         => 'Solo se permiten archivos PDF, Word e imágenes (JPG, PNG).',
        ]);

        $archivo = $request->file('archivo');
        $ruta    = $archivo->store("evidencias/{$nomina->id}", 'public');

        Evidencia::create([
            'nomina_id'      => $nomina->id,
            'categoria_id'   => $data['categoria_id'],
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta'           => $ruta,
            'tamano'         => $archivo->getSize(),
            'mime_type'      => $archivo->getMimeType(),
            'subido_por'     => $user->id,
            'es_apelacion'   => false,
            'descripcion'    => $data['descripcion'] ?? null,
        ]);

        if ($nomina->estado === 'pendiente') {
            $nomina->update(['estado' => 'en_carga']);
        }

        return back()->with('success', 'Evidencia cargada correctamente.');
    }

    public function download(Evidencia $evidencia): StreamedResponse
    {
        $user = auth()->user();

        if ($evidencia->nomina->user_id !== $user->id) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($evidencia->ruta)) {
            abort(404);
        }

        return Storage::disk('public')->download($evidencia->ruta, $evidencia->nombre_archivo);
    }

    public function destroy(Evidencia $evidencia)
    {
        $user = auth()->user();

        if ($evidencia->nomina->user_id !== $user->id) {
            abort(403);
        }

        if (!$evidencia->nomina->con_licencia) {
            $periodo = Periodo::where('estado', 'activo')->latest()->first();
            $plazo   = PlazoFacultad::where('periodo_id', $periodo?->id)
                ->where('facultad_id', $user->facultad_id)
                ->first();

            if ($plazo && !$plazo->estaVigente()) {
                return back()->with('error', 'No puede eliminar evidencias fuera del plazo.');
            }
        }

        Storage::disk('public')->delete($evidencia->ruta);
        $evidencia->delete();

        return back()->with('success', 'Evidencia eliminada.');
    }

    public function storeApelacion(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay un período activo.');
        }

        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina || $nomina->estado !== 'apelado') {
            return back()->with('error', 'No puede cargar evidencias de apelación en este momento.');
        }

        $apelacion = $nomina->apelacion;
        if (!$apelacion || $apelacion->estado !== 'en_revision') {
            return back()->with('error', 'Su apelación no está aprobada para carga de evidencias.');
        }

        $data = $request->validate([
            'categoria_id' => ['required', 'exists:categorias_apa,id'],
            'archivo'      => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
        ], [
            'categoria_id.required' => 'Debe indicar la categoría.',
            'categoria_id.exists'   => 'Categoría no válida.',
            'archivo.required'      => 'Debe seleccionar un archivo.',
            'archivo.max'           => 'El archivo no puede superar los 10 MB.',
            'archivo.mimes'         => 'Solo se permiten archivos PDF, Word e imágenes (JPG, PNG).',
        ]);

        $archivo = $request->file('archivo');
        $ruta    = $archivo->store("evidencias/{$nomina->id}/apelacion", 'public');

        Evidencia::create([
            'nomina_id'      => $nomina->id,
            'categoria_id'   => $data['categoria_id'],
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta'           => $ruta,
            'tamano'         => $archivo->getSize(),
            'mime_type'      => $archivo->getMimeType(),
            'subido_por'     => $user->id,
            'es_apelacion'   => true,
            'descripcion'    => $data['descripcion'] ?? null,
        ]);

        return back()->with('success', 'Evidencia de apelación cargada correctamente.');
    }

    public function destroyApelacion(Evidencia $evidencia)
    {
        $user = auth()->user();

        if ($evidencia->nomina->user_id !== $user->id || !$evidencia->es_apelacion) {
            abort(403);
        }

        Storage::disk('public')->delete($evidencia->ruta);
        $evidencia->delete();

        return back()->with('success', 'Evidencia de apelación eliminada.');
    }
}
