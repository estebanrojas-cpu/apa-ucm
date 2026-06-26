<?php

namespace App\Http\Controllers;

use App\Models\Apelacion;
use App\Models\CategoriaApa;
use App\Models\Cronograma;
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
        $motivoBloqueoCarga = $nomina && !$puedeCargar
            ? $nomina->motivoBloqueoCargaEvidencias()
            : null;

        $apelacionEtapaVigente = $periodo ? Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'apelaciones')
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->exists() : false;

        $apelacion = $nomina?->apelacion;
        $puedeCargarApelacion = $nomina
            && in_array($nomina->estado, ['evaluado', 'apelado'])
            && $apelacionEtapaVigente
            && (!$apelacion || $apelacion->estado === 'en_revision');

        return Inertia::render('Academico/Evidencias', [
            'periodo'                => $periodo?->only(['id', 'anio', 'nombre']),
            'nomina'                 => $nomina ? [
                'id'                     => $nomina->id,
                'estado'                 => $nomina->estado,
                'con_licencia'           => $nomina->con_licencia,
                'observacion_licencia'   => $nomina->observacion_licencia,
                'plazo_licencia'         => $nomina->plazo_licencia?->format('Y-m-d'),
                'observacion_secretario' => $nomina->observacion_secretario,
            ] : null,
            'plazo'                  => $plazo,
            'puedeCargar'            => $puedeCargar,
            'motivoBloqueoCarga'     => $motivoBloqueoCarga,
            'puedeCargarApelacion'   => $puedeCargarApelacion,
            'apelacionEtapaVigente'  => $apelacionEtapaVigente,
            'apelacion'              => $apelacion ? [
                'estado'     => $apelacion->estado,
                'resolucion' => $apelacion->resolucion,
            ] : null,
            'categorias'             => $categorias->map(fn ($c) => [
                'id'          => $c->id,
                'nombre'      => $c->nombre,
                'slug'        => $c->slug,
                'descripcion' => $c->descripcion,
            ]),
            'conteoEvidencias'       => $nomina ? $categorias->mapWithKeys(fn ($c) => [
                $c->id => [
                    'normales'   => $nomina->evidenciasNormales->where('categoria_id', $c->id)->count(),
                    'apelacion'  => $nomina->evidenciasApelacion->where('categoria_id', $c->id)->count(),
                ],
            ]) : [],
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

        $etapaVigente = Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'apelaciones')
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->exists();

        if (!$etapaVigente) {
            return back()->with('error', 'El plazo de apelaciones no está vigente.');
        }

        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina || !in_array($nomina->estado, ['evaluado', 'apelado'])) {
            return back()->with('error', 'No puede cargar evidencias de apelación en este momento.');
        }

        $apelacion = $nomina->apelacion;
        if ($apelacion && $apelacion->estado === 'resuelta') {
            return back()->with('error', 'La apelación ya fue resuelta. No puede agregar más evidencias.');
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

        // Auto-create apelación si es la primera evidencia de apelación
        if (!$apelacion) {
            Apelacion::create([
                'nomina_id'       => $nomina->id,
                'motivo'          => 'Apelación presentada mediante evidencias adicionales.',
                'estado'          => 'en_revision',
                'fecha_solicitud' => now()->toDateString(),
            ]);
            $nomina->update(['estado' => 'apelado']);
        }

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

    public function preview(Evidencia $evidencia)
    {
        $user = auth()->user();

        if ($evidencia->nomina->user_id !== $user->id) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($evidencia->ruta)) {
            abort(404);
        }

        return Storage::disk('public')->response($evidencia->ruta, $evidencia->nombre_archivo);
    }

    public function showCategoria(CategoriaApa $categoria): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return redirect()->route('academico.evidencias');
        }

        $nomina = Nomina::with([
            'evidenciasNormales.categoria',
            'evidenciasApelacion.categoria',
            'apelacion',
        ])
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina) {
            return redirect()->route('academico.evidencias');
        }

        $plazoModel = PlazoFacultad::where('periodo_id', $periodo->id)
            ->where('facultad_id', $user->facultad_id)
            ->first();

        $puedeCargar = $nomina->cargaEvidenciasHabilitada();

        $apelacionEtapaVigente = Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'apelaciones')
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->exists();

        $apelacion = $nomina->apelacion;
        $puedeCargarApelacion = in_array($nomina->estado, ['evaluado', 'apelado'])
            && $apelacionEtapaVigente
            && (!$apelacion || $apelacion->estado === 'en_revision');

        $mapEv = fn ($ev) => [
            'id'             => $ev->id,
            'nombre_archivo' => $ev->nombre_archivo,
            'tamano'         => $ev->tamanoFormateado(),
            'descripcion'    => $ev->descripcion,
            'mime_type'      => $ev->mime_type,
            'created_at'     => $ev->created_at->format('d/m/Y H:i'),
            'url_descarga'   => route('academico.evidencias.download',  $ev->id),
            'url_preview'    => route('academico.evidencias.preview',    $ev->id),
        ];

        return Inertia::render('Academico/EvidenciaCategoria', [
            'periodo'              => $periodo->only(['id', 'nombre']),
            'categoria'            => [
                'id'          => $categoria->id,
                'nombre'      => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
            ],
            'evidenciasNormales'   => $nomina->evidenciasNormales
                ->where('categoria_id', $categoria->id)
                ->values()->map($mapEv),
            'evidenciasApelacion'  => $nomina->evidenciasApelacion
                ->where('categoria_id', $categoria->id)
                ->values()->map($mapEv),
            'puedeCargar'          => $puedeCargar,
            'puedeCargarApelacion' => $puedeCargarApelacion,
            'nominaEstado'         => $nomina->estado,
        ]);
    }
}
