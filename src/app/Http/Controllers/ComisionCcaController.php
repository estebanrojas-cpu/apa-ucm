<?php



namespace App\Http\Controllers;



use App\Models\ComisionCca;

use App\Models\Facultad;

use App\Models\Nomina;

use App\Models\Periodo;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;

use Inertia\Inertia;

use Inertia\Response;



class ComisionCcaController extends Controller

{

    public function index(Periodo $periodo): Response

    {

        $facultades = Facultad::orderBy('nombre')->get()->map(function (Facultad $facultad) use ($periodo) {

            $comision = ComisionCca::with('integrantes.nomina.academico')

                ->where('periodo_id', $periodo->id)

                ->where('facultad_id', $facultad->id)

                ->first();



            $nominasFacultad = Nomina::where('periodo_id', $periodo->id)

                ->where('facultad_id', $facultad->id)

                ->count();



            return [

                'id'              => $facultad->id,

                'nombre'          => $facultad->nombre,

                'codigo'          => $facultad->codigo,

                'nominas_count'   => $nominasFacultad,

                'comision'        => $comision ? [

                    'id'            => $comision->id,

                    'estado'        => $comision->estado,

                    'confirmada_en' => $comision->confirmada_en?->format('d/m/Y H:i'),

                    'integrantes'   => $comision->integrantes->map(fn ($i) => [

                        'id'    => $i->nomina_id,

                        'name'  => $i->nomina->nombre ?? $i->nomina->academico?->name,

                        'email' => $i->nomina->academico?->email,

                        'rut'   => $i->nomina->rut ?? $i->nomina->academico?->rut,

                    ])->values(),

                ] : null,

            ];

        });



        return Inertia::render('Comision/Index', [

            'periodo'    => $periodo->only(['id', 'anio', 'nombre', 'estado']),

            'facultades' => $facultades,

        ]);

    }



    public function edit(Periodo $periodo, Facultad $facultad): Response

    {

        $comision = ComisionCca::paraPeriodoFacultad($periodo->id, $facultad->id);

        $comision->load('integrantes');



        $candidatos = Nomina::with('academico')

            ->where('periodo_id', $periodo->id)

            ->where('facultad_id', $facultad->id)

            ->evaluables()

            ->orderBy('nombre')

            ->get()

            ->map(fn (Nomina $n) => [

                'id'    => $n->id,

                'name'  => $n->nombre ?? $n->academico?->name,

                'email' => $n->academico?->email,

                'rut'   => $n->rut ?? $n->academico?->rut,

            ])

            ->values();



        return Inertia::render('Comision/Edit', [

            'periodo'    => $periodo->only(['id', 'anio', 'nombre']),

            'facultad'   => $facultad->only(['id', 'nombre', 'codigo']),

            'comision'   => [

                'id'          => $comision->id,

                'estado'      => $comision->estado,

                'integrantes' => $comision->integrantes->pluck('nomina_id')->all(),

            ],

            'candidatos' => $candidatos,

        ]);

    }



    public function update(Request $request, Periodo $periodo, Facultad $facultad)

    {

        $data = $request->validate([

            'integrantes'   => ['required', 'array', 'min:2'],

            'integrantes.*' => ['required', 'uuid', 'exists:nominas,id'],

        ], [

            'integrantes.min' => 'La comisión debe tener al menos 2 integrantes.',

        ]);



        $comision = ComisionCca::paraPeriodoFacultad($periodo->id, $facultad->id);



        if ($comision->estaConfirmada()) {

            return back()->with('error', 'La comisión ya está confirmada. No se puede modificar.');

        }



        $validos = Nomina::where('periodo_id', $periodo->id)

            ->where('facultad_id', $facultad->id)

            ->evaluables()

            ->whereIn('id', $data['integrantes'])

            ->pluck('id')

            ->all();



        if (count($validos) !== count($data['integrantes'])) {

            throw ValidationException::withMessages([

                'integrantes' => 'Todos los integrantes deben ser académicos evaluables de la nómina de esta facultad.',

            ]);

        }



        $comision->integrantes()->delete();

        foreach ($data['integrantes'] as $nominaId) {

            $comision->integrantes()->create(['nomina_id' => $nominaId]);

        }



        $comision->update(['designado_por' => $request->user()->id]);



        return redirect()

            ->route('analista.periodos.comisiones.index', $periodo)

            ->with('success', "Integrantes de la comisión {$facultad->nombre} guardados.");

    }



    public function confirmar(Request $request, Periodo $periodo, Facultad $facultad)

    {

        $comision = ComisionCca::withCount('integrantes')

            ->where('periodo_id', $periodo->id)

            ->where('facultad_id', $facultad->id)

            ->firstOrFail();



        if ($comision->integrantes_count < 2) {

            return back()->with('error', 'Debe designar al menos 2 integrantes antes de confirmar.');

        }



        $comision->update([

            'estado'        => 'confirmada',

            'designado_por' => $request->user()->id,

            'confirmada_en' => now(),

        ]);



        return back()->with('success', "Comisión evaluadora de {$facultad->nombre} confirmada.");

    }

}


