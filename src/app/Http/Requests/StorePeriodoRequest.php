<?php

namespace App\Http\Requests;

use App\Models\Cronograma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePeriodoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre'                 => ['required', 'string', 'max:120'],
            'fecha_inicio'           => ['required', 'date'],
            'fecha_cierre'           => ['required', 'date', 'after:fecha_inicio'],
            'cronograma'             => ['required', 'array', 'size:7'],
            'cronograma.*.etapa'     => ['required', 'string', 'in:'.implode(',', Cronograma::ETAPAS)],
            'cronograma.*.fecha_fin' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $inicio     = $this->input('fecha_inicio');
            $cierre     = $this->input('fecha_cierre');
            $cronograma = $this->input('cronograma', []);

            if (!$inicio || !$cierre || count($cronograma) !== 7) {
                return;
            }

            $finesPorEtapa = collect($cronograma)->pluck('fecha_fin', 'etapa');

            foreach (Cronograma::ETAPAS as $etapa) {
                if (!$finesPorEtapa->has($etapa)) {
                    $v->errors()->add('cronograma', "Falta la etapa: ".Cronograma::etiqueta($etapa).'.');
                }
            }

            foreach ($cronograma as $i => $entry) {
                $etapa    = $entry['etapa'] ?? null;
                $fechaFin = $entry['fecha_fin'] ?? null;

                if (!$etapa || !$fechaFin) {
                    continue;
                }

                try {
                    $fechaInicio = Cronograma::calcularFechaInicio($etapa, $inicio, $finesPorEtapa->all());
                } catch (\InvalidArgumentException) {
                    continue;
                }

                if ($fechaFin <= $fechaInicio) {
                    $v->errors()->add(
                        "cronograma.$i.fecha_fin",
                        'La fecha de cierre debe ser posterior al inicio calculado de la etapa ('.date('d/m/Y', strtotime($fechaInicio)).').'
                    );
                }

                if ($fechaFin < $inicio || $fechaFin > $cierre) {
                    $v->errors()->add("cronograma.$i.fecha_fin", 'Debe estar dentro del período principal.');
                }
            }

            $secuencia = [
                ['actual' => 'evaluacion_cca',         'previa' => 'carga_evidencias',      'label' => 'Evaluación CCA'],
                ['actual' => 'consejo_facultad',       'previa' => 'evaluacion_cca',        'label' => 'Consejo de Facultad'],
                ['actual' => 'apelaciones',            'previa' => 'consejo_facultad',      'label' => 'Apelaciones'],
                ['actual' => 'revision_vicerrectoria', 'previa' => 'apelaciones',           'label' => 'Revisión Vicerrectoría'],
                ['actual' => 'cierre',                 'previa' => 'revision_vicerrectoria','label' => 'Cierre'],
            ];

            foreach ($secuencia as $par) {
                $finActual  = $finesPorEtapa->get($par['actual']);
                $finPrevia  = $finesPorEtapa->get($par['previa']);

                if ($finActual && $finPrevia && $finActual < $finPrevia) {
                    $idx = collect($cronograma)->search(fn ($e) => ($e['etapa'] ?? null) === $par['actual']);
                    $key = $idx !== false ? "cronograma.$idx.fecha_fin" : 'cronograma';
                    $v->errors()->add($key, "{$par['label']} no puede cerrar antes que la etapa anterior.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fecha_cierre.after'              => 'La fecha de cierre debe ser posterior al inicio.',
            'cronograma.size'                 => 'El cronograma debe tener exactamente 7 etapas.',
            'cronograma.*.etapa.in'           => 'La etapa indicada no es válida.',
            'cronograma.*.fecha_fin.required' => 'La fecha de cierre de la etapa es obligatoria.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        $data['cronograma'] = Cronograma::prepararParaGuardar(
            $data['fecha_inicio'],
            $data['cronograma'],
        );

        return $data;
    }
}
