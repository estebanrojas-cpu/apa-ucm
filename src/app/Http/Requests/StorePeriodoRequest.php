<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePeriodoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre'                    => ['required', 'string', 'max:120'],
            'fecha_inicio'              => ['required', 'date'],
            'fecha_cierre'              => ['required', 'date', 'after:fecha_inicio'],
            'cronograma'                => ['required', 'array', 'size:6'],
            'cronograma.*.etapa'        => ['required', 'string'],
            'cronograma.*.fecha_inicio' => ['required', 'date'],
            'cronograma.*.fecha_fin'    => ['required', 'date', 'after_or_equal:cronograma.*.fecha_inicio'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $inicio     = $this->input('fecha_inicio');
            $cierre     = $this->input('fecha_cierre');
            $cronograma = $this->input('cronograma', []);

            if (!$inicio || !$cierre) return;

            foreach ($cronograma as $i => $entry) {
                $eInicio = $entry['fecha_inicio'] ?? null;
                $eFin    = $entry['fecha_fin'] ?? null;

                if ($eInicio && ($eInicio < $inicio || $eInicio > $cierre)) {
                    $v->errors()->add(
                        "cronograma.$i.fecha_inicio",
                        'Debe estar dentro del período principal.'
                    );
                }
                if ($eFin && ($eFin < $inicio || $eFin > $cierre)) {
                    $v->errors()->add(
                        "cronograma.$i.fecha_fin",
                        'Debe estar dentro del período principal.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fecha_cierre.after'                    => 'La fecha de cierre debe ser posterior al inicio.',
            'cronograma.size'                       => 'El cronograma debe tener exactamente 6 etapas.',
            'cronograma.*.fecha_fin.after_or_equal' => 'La fecha fin debe ser igual o posterior al inicio de la etapa.',
            'cronograma.*.etapa.required'           => 'La etapa es obligatoria.',
            'cronograma.*.fecha_inicio.required'    => 'La fecha de inicio de la etapa es obligatoria.',
            'cronograma.*.fecha_fin.required'       => 'La fecha de fin de la etapa es obligatoria.',
        ];
    }
}
