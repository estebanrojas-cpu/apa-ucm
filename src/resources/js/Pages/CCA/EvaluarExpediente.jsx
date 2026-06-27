import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Fragment, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADOS = {
    carga_cerrada: { label: 'Por evaluar',   cls: 'bg-blue-100 text-blue-700' },
    en_evaluacion: { label: 'En evaluación', cls: 'bg-purple-100 text-purple-700' },
    evaluado:      { label: 'Evaluado',      cls: 'bg-green-100 text-green-700' },
};

const CONCEPTOS = {
    excelente:  'Excelente',
    muy_bueno:  'Muy Bueno',
    bueno:      'Bueno',
    regular:    'Regular',
    deficiente: 'Deficiente',
};

const AREA_LABELS = {
    docencia:           'Actividades de Docencia',
    investigacion:      'Actividades de Investigación',
    vinculacion:        'Extensión y Vinculación',
    gestion:            'Administración Académica',
    formacion_continua: 'Otras actividades autorizadas',
};

const SLUGS_A_CAMPO = {
    docencia:           'puntaje_docencia',
    investigacion:      'puntaje_investigacion',
    vinculacion:        'puntaje_vinculacion',
    gestion:            'puntaje_gestion',
    formacion_continua: 'puntaje_formacion',
};

const SLUG_A_REGLAMENTO = {
    docencia:           'docencia',
    investigacion:      'investigacion',
    vinculacion:        'vinculacion',
    gestion:            'gestion',
    formacion_continua: 'formacion',
};

const AREAS_HORAS = [
    { key: 'hrs_docencia',       label: 'Actividades de Docencia' },
    { key: 'hrs_investigacion',  label: 'Actividades de Investigación' },
    { key: 'hrs_extension',      label: 'Extensión y Vinculación' },
    { key: 'hrs_administracion', label: 'Administración Académica' },
    { key: 'hrs_otras',          label: 'Otras actividades autorizadas' },
];

const AREAS_HORAS_MAIN = AREAS_HORAS.filter(a => a.key !== 'hrs_otras');

function horasRealesVacias() {
    return {
        S1: Object.fromEntries(AREAS_HORAS.map(a => [a.key, ''])),
        S2: Object.fromEntries(AREAS_HORAS.map(a => [a.key, ''])),
    };
}

function horasRealesDesdeEvaluacion(miEvaluacion) {
    const out = horasRealesVacias();
    if (!miEvaluacion?.horas_reales) return out;
    for (const sem of ['S1', 'S2']) {
        for (const { key } of AREAS_HORAS) {
            const val = miEvaluacion.horas_reales[sem]?.[key];
            out[sem][key] = val != null ? String(val) : '';
        }
    }
    return out;
}

function totalHorasMain(horasObj) {
    return AREAS_HORAS_MAIN.reduce((s, { key }) => s + (parseFloat(horasObj?.[key]) || 0), 0);
}

function fmtHoras(n) {
    return (parseFloat(n) || 0).toFixed(1);
}

function deltaClass(delta) {
    if (Math.abs(delta) < 0.05) return 'text-gray-500';
    return delta > 0 ? 'text-amber-700 font-medium' : 'text-blue-700 font-medium';
}

const HRS_KEY_A_AREA = {
    hrs_docencia:       'docencia',
    hrs_investigacion:  'investigacion',
    hrs_extension:      'extension',
    hrs_administracion: 'administracion',
};

const AREA_A_REG = {
    docencia:      'docencia',
    investigacion: 'investigacion',
    extension:     'vinculacion',
    administracion:'gestion',
};

function calcularPesosDesdeHorasReales(horasReales) {
    if (!horasReales?.S1 || !horasReales?.S2) return null;

    for (const sem of ['S1', 'S2']) {
        for (const { key } of AREAS_HORAS_MAIN) {
            const v = horasReales[sem][key];
            if (v === '' || v == null || isNaN(parseFloat(v))) return null;
        }
    }

    const sumHoras = { docencia: 0, investigacion: 0, extension: 0, administracion: 0 };
    for (const sem of ['S1', 'S2']) {
        for (const [hrsKey, area] of Object.entries(HRS_KEY_A_AREA)) {
            sumHoras[area] += parseFloat(horasReales[sem][hrsKey]) || 0;
        }
    }

    const total = Object.values(sumHoras).reduce((a, b) => a + b, 0);
    if (total <= 0) return null;

    const pesos = { formacion: 0 };
    let suma = 0;
    let lastKey = null;

    for (const [area, regKey] of Object.entries(AREA_A_REG)) {
        const hrs = sumHoras[area];
        if (hrs > 0) {
            const pct = Math.round(hrs / total * 10000) / 100;
            pesos[regKey] = pct;
            suma += pct;
            lastKey = regKey;
        } else {
            pesos[regKey] = 0;
        }
    }

    if (lastKey) {
        pesos[lastKey] = Math.round((100 - (suma - pesos[lastKey])) * 100) / 100;
    }

    return pesos;
}

function calcularNotaFinal(notas, categorias, pesosReglamento, extra = 0) {
    let suma = 0;
    categorias
        .filter(cat => cat.slug !== 'formacion_continua')
        .forEach(cat => {
            const campo = SLUGS_A_CAMPO[cat.slug];
            const regKey = SLUG_A_REGLAMENTO[cat.slug] ?? cat.slug;
            const nota = Number(notas[campo] ?? 1);
            const peso = Number(pesosReglamento[regKey] ?? cat.peso ?? 0);
            suma += (peso * nota) / 100;
        });
    return Math.min(Math.round((suma + Number(extra)) * 100) / 100, 5.0);
}

function conceptoDesdeNota(nota) {
    if (nota >= 4.5) return 'excelente';
    if (nota >= 4.0) return 'muy_bueno';
    if (nota >= 3.5) return 'bueno';
    if (nota >= 2.7) return 'regular';
    return 'deficiente';
}

export default function EvaluarExpediente({
    nomina, categorias, pesosReglamento, conteoEvidencias, conteoEvidenciasApelacion = {},
    miEvaluacion, todasEvaluaciones, calificacionFinal, calificacionOriginal,
    esApelacion, apelacion, sinCompromisoApa, compromisosSemestres,
}) {
    const { flash } = usePage().props;
    const badge = ESTADOS[nomina.estado] ?? { label: nomina.estado, cls: 'bg-gray-100 text-gray-600' };
    const bloqueado = nomina.estado === 'evaluado';

    const evalForm = useForm({
        sin_calificacion:         miEvaluacion?.sin_calificacion         ?? false,
        motivo_sc:                miEvaluacion?.motivo_sc                ?? '',
        horas_reales:             horasRealesDesdeEvaluacion(miEvaluacion),
        puntaje_docencia:         miEvaluacion?.puntaje_docencia         ?? 3.0,
        puntaje_investigacion:    miEvaluacion?.puntaje_investigacion    ?? 3.0,
        puntaje_vinculacion:      miEvaluacion?.puntaje_vinculacion      ?? 3.0,
        puntaje_gestion:          miEvaluacion?.puntaje_gestion          ?? 3.0,
        extra_otras_actividades:  miEvaluacion?.extra_otras_actividades  ?? 0,
        comentario:               miEvaluacion?.comentario               ?? '',
    });

    const finalForm = useForm({ observacion: '' });

    const pesosEfectivos = useMemo(
        () => calcularPesosDesdeHorasReales(evalForm.data.horas_reales) ?? pesosReglamento,
        [evalForm.data.horas_reales, pesosReglamento]
    );

    const notaCalculada = useMemo(
        () => evalForm.data.sin_calificacion
            ? 0
            : calcularNotaFinal(evalForm.data, categorias, pesosEfectivos, evalForm.data.extra_otras_actividades),
        [evalForm.data, categorias, pesosEfectivos]
    );

    const conceptoCalculado = conceptoDesdeNota(notaCalculada);

    const compromisosPorSemestre = useMemo(() => {
        const map = {};
        (compromisosSemestres ?? []).forEach(c => { map[c.semestre] = c; });
        return map;
    }, [compromisosSemestres]);

    const horasContratoPorSemestre = {
        S1: parseFloat(nomina.academico.horas_contrato_isem) || 0,
        S2: parseFloat(nomina.academico.horas_contrato_iisem) || 0,
    };

    function setHoraReal(semestre, key, value) {
        evalForm.setData('horas_reales', {
            ...evalForm.data.horas_reales,
            [semestre]: {
                ...evalForm.data.horas_reales[semestre],
                [key]: value,
            },
        });
    }

    function submitEval(e) {
        e.preventDefault();
        evalForm.post(`/cca/expedientes/${nomina.id}/evaluar`, { preserveScroll: true });
    }

    function submitFinal(e) {
        e.preventDefault();
        finalForm.post(`/cca/expedientes/${nomina.id}/finalizar`, { preserveScroll: true });
    }

    const ac = nomina.academico;

    return (
        <>
            <Head title={`Evaluar — ${ac.name}`} />
            <AppLayout title="Evaluación de Expediente">

                <div className="-mt-4 mb-6">
                    <Link href="/cca/expedientes"
                        className="text-sm text-[#0096D6] hover:underline flex items-center gap-1">
                        ← Volver a expedientes
                    </Link>
                </div>

                {sinCompromisoApa && (
                    <div className="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        Este académico no ha declarado su distribución APA. Se usan los pesos por defecto del reglamento.
                    </div>
                )}

                {esApelacion && (
                    <div className="mb-5 rounded-lg bg-orange-50 border border-orange-200 px-4 py-4 text-sm text-orange-900">
                        <p className="font-semibold mb-1">Re-evaluación por apelación</p>
                        <p className="text-orange-800 text-xs leading-relaxed">
                            El secretario derivó este expediente a la CCA. Revise las evidencias originales del período
                            y las nuevas de la apelación antes de registrar su evaluación.
                        </p>
                        {calificacionOriginal && (
                            <p className="mt-2 text-xs font-medium">
                                Calificación original: {calificacionOriginal.concepto_label} ({calificacionOriginal.nota_final}/5.0)
                            </p>
                        )}
                        {apelacion?.motivo && (
                            <p className="mt-2 text-xs text-orange-700 line-clamp-3">
                                Motivo: {apelacion.motivo}
                            </p>
                        )}
                    </div>
                )}

                {flash?.success && (
                    <div className="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* Datos precargados (solo lectura) */}
                <div className="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-6">
                    <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Datos del académico — solo lectura
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <Info label="Nombre" value={ac.name} />
                        <Info label="RUT" value={ac.rut} />
                        <Info label="Facultad" value={ac.facultad} />
                        <Info label="Categoría académica" value={ac.categoria_academica} />
                        <Info label="Línea de desarrollo" value={ac.linea_desarrollo} />
                        <Info label="Horas I semestre" value={ac.horas_contrato_isem ? `${ac.horas_contrato_isem} hrs` : '—'} />
                        <Info label="Horas II semestre" value={ac.horas_contrato_iisem ? `${ac.horas_contrato_iisem} hrs` : '—'} />
                        <Info label="Calificación anterior" value={
                            ac.nota_anterior
                                ? `${Number(ac.nota_anterior).toFixed(1)} — ${ac.concepto_anterior ?? ''}`
                                : '—'
                        } />
                        <div>
                            <p className="text-xs text-gray-400">Estado expediente</p>
                            <span className={`inline-block mt-0.5 text-xs font-semibold px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                                {badge.label}
                            </span>
                        </div>
                    </div>

                    <div className="mt-4 pt-4 border-t border-gray-200">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                            % tiempo asignado por área APA (declaración del académico)
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            {categorias.map(cat => (
                                <div key={cat.id} className="flex justify-between text-sm bg-white rounded-lg px-3 py-2 border border-gray-100">
                                    <span className="text-gray-600">{AREA_LABELS[cat.slug] ?? cat.nombre}</span>
                                    <span className="font-semibold text-[#1B2D6B]">{cat.peso}%</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {calificacionFinal && (
                    <div className="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold text-green-700 uppercase tracking-wide mb-2">Calificación Final</p>
                                <p className="text-2xl font-bold text-green-800">
                                    {calificacionFinal.concepto_label} — {calificacionFinal.nota_final} / 5.0
                                </p>
                                <p className="text-xs text-green-600 mt-1">Registrada el {calificacionFinal.fecha}</p>
                                {calificacionFinal.observacion && (
                                    <p className="text-sm text-green-700 mt-2">{calificacionFinal.observacion}</p>
                                )}
                            </div>
                            <a
                                href={`/cca/expedientes/${nomina.id}/calificacion-pdf`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-[#1B2D6B] text-white text-xs font-medium rounded-lg hover:bg-[#152558] transition-colors"
                            >
                                ↓ Informe PDF
                            </a>
                        </div>
                    </div>
                )}

                <form onSubmit={submitEval} className="space-y-6">
                {compromisosSemestres?.length > 0 && (
                    <div className="bg-white rounded-xl border border-[#1B2D6B]/20 p-5">
                        <div className="mb-4">
                            <h2 className="text-sm font-semibold text-[#1B2D6B] uppercase tracking-wide">
                                Comparación horas APA — declaradas vs reales
                            </h2>
                            <p className="text-xs text-gray-500 mt-1">
                                Revise la evidencia y registre las horas que el académico ocupó efectivamente,
                                comparadas con lo declarado en su APA de cada semestre.
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs border-collapse">
                                <thead>
                                    <tr className="bg-gray-50 text-gray-600">
                                        <th className="text-left px-3 py-2 font-medium border-b border-gray-200" rowSpan={2}>Área</th>
                                        {['S1', 'S2'].filter(s => compromisosPorSemestre[s]).map(sem => (
                                            <th key={sem} colSpan={3}
                                                className="text-center px-2 py-2 font-semibold border-b border-gray-200 border-l border-gray-200 text-[#1B2D6B]">
                                                {compromisosPorSemestre[sem]?.label ?? sem}
                                                {horasContratoPorSemestre[sem] > 0 && (
                                                    <span className="block font-normal text-gray-400 text-[10px]">
                                                        Contrato: {fmtHoras(horasContratoPorSemestre[sem])} h
                                                    </span>
                                                )}
                                            </th>
                                        ))}
                                    </tr>
                                    <tr className="bg-gray-50 text-gray-500">
                                        {['S1', 'S2'].filter(s => compromisosPorSemestre[s]).map(sem => (
                                            <Fragment key={sem}>
                                                <th className="text-center px-2 py-1.5 font-medium border-b border-gray-200 border-l border-gray-200">
                                                    Declaradas
                                                </th>
                                                <th className="text-center px-2 py-1.5 font-medium border-b border-gray-200">
                                                    Reales (CCA)
                                                </th>
                                                <th className="text-center px-2 py-1.5 font-medium border-b border-gray-200">
                                                    Δ
                                                </th>
                                            </Fragment>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {AREAS_HORAS.map(({ key, label }) => (
                                        <tr key={key} className="hover:bg-gray-50/50">
                                            <td className="px-3 py-2 text-gray-700 font-medium">{label}</td>
                                            {['S1', 'S2'].filter(s => compromisosPorSemestre[s]).map(sem => {
                                                const declarado = parseFloat(compromisosPorSemestre[sem]?.[key]) || 0;
                                                const realStr   = evalForm.data.horas_reales[sem]?.[key] ?? '';
                                                const real      = parseFloat(realStr) || 0;
                                                const delta     = realStr !== '' ? real - declarado : null;
                                                const errKey    = `horas_reales.${sem}.${key}`;
                                                return (
                                                    <Fragment key={sem}>
                                                        <td
                                                            className="px-2 py-2 text-center tabular-nums text-gray-600 border-l border-gray-100 bg-gray-50/60">
                                                            {fmtHoras(declarado)} h
                                                        </td>
                                                        <td className="px-2 py-1.5 text-center">
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                step="0.1"
                                                                value={realStr}
                                                                disabled={bloqueado || evalForm.data.sin_calificacion}
                                                                onChange={e => setHoraReal(sem, key, e.target.value)}
                                                                placeholder="0.0"
                                                                className="w-20 mx-auto block border border-gray-300 rounded px-2 py-1 text-center text-[#1B2D6B] font-medium focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 disabled:bg-gray-100 disabled:text-gray-500"
                                                            />
                                                            {evalForm.errors[errKey] && (
                                                                <p className="text-[10px] text-red-600 mt-0.5">{evalForm.errors[errKey]}</p>
                                                            )}
                                                        </td>
                                                        <td
                                                            className={`px-2 py-2 text-center tabular-nums ${delta != null ? deltaClass(delta) : 'text-gray-300'}`}>
                                                            {delta != null
                                                                ? `${delta >= 0 ? '+' : ''}${delta.toFixed(1)}`
                                                                : '—'}
                                                        </td>
                                                    </Fragment>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                    <tr className="bg-blue-50/70 font-semibold text-blue-900">
                                        <td className="px-3 py-2">Total (sin otras)</td>
                                        {['S1', 'S2'].filter(s => compromisosPorSemestre[s]).map(sem => {
                                            const decl = totalHorasMain(compromisosPorSemestre[sem]);
                                            const real = totalHorasMain(evalForm.data.horas_reales[sem]);
                                            const realFilled = AREAS_HORAS_MAIN.some(
                                                ({ key }) => (evalForm.data.horas_reales[sem]?.[key] ?? '') !== ''
                                            );
                                            const delta = realFilled ? real - decl : null;
                                            const contrato = horasContratoPorSemestre[sem];
                                            const diffContrato = realFilled && contrato > 0
                                                ? Math.abs(real - contrato) > 0.05
                                                : false;
                                            return (
                                                <Fragment key={sem}>
                                                    <td
                                                        className="px-2 py-2 text-center tabular-nums border-l border-blue-100">
                                                        {fmtHoras(decl)} h
                                                    </td>
                                                    <td className="px-2 py-2 text-center tabular-nums">
                                                        {realFilled ? `${fmtHoras(real)} h` : '—'}
                                                        {diffContrato && (
                                                            <p className="text-[10px] font-normal text-amber-700 mt-0.5">
                                                                ≠ contrato ({fmtHoras(contrato)} h)
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td
                                                        className={`px-2 py-2 text-center tabular-nums ${delta != null ? deltaClass(delta) : ''}`}>
                                                        {delta != null
                                                            ? `${delta >= 0 ? '+' : ''}${delta.toFixed(1)}`
                                                            : '—'}
                                                    </td>
                                                </Fragment>
                                            );
                                        })}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        {evalForm.data.sin_calificacion && (
                            <p className="mt-3 text-xs text-gray-400 italic">
                                Sin calificación: las horas reales son opcionales.
                            </p>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {/* Evidencias */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Documentación entregada
                        </h2>
                        {esApelacion && (
                            <p className="text-xs text-gray-500 mb-3">
                                Se muestran archivos del período y, en naranja, los agregados en la apelación.
                            </p>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {categorias.map(cat => {
                                const normales  = conteoEvidencias[cat.id] ?? 0;
                                const apelacion = conteoEvidenciasApelacion[cat.id] ?? 0;
                                const total     = normales + apelacion;
                                return (
                                    <Link
                                        key={cat.id}
                                        href={`/cca/expedientes/${nomina.id}/categoria/${cat.id}`}
                                        className="bg-white rounded-xl border border-gray-200 p-4 hover:border-[#1B2D6B] hover:shadow-sm transition-all group"
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <h3 className="font-semibold text-gray-800 text-sm group-hover:text-[#1B2D6B] transition-colors">
                                                {AREA_LABELS[cat.slug] ?? cat.nombre}
                                            </h3>
                                            <span className="text-[#1B2D6B] text-sm font-bold shrink-0">→</span>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                normales > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                            }`}>
                                                {normales} {normales === 1 ? 'archivo' : 'archivos'}
                                            </span>
                                            {esApelacion && apelacion > 0 && (
                                                <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">
                                                    +{apelacion} apelación
                                                </span>
                                            )}
                                            {total === 0 && (
                                                <span className="text-xs text-gray-400">Sin archivos</span>
                                            )}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>

                    {/* Formulario evaluación */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            {esApelacion
                                ? (miEvaluacion ? 'Mi evaluación de apelación' : 'Registrar evaluación de apelación')
                                : (miEvaluacion ? 'Mi evaluación' : 'Registrar evaluación')}
                        </h2>

                        {miEvaluacion && (
                            <p className="text-xs text-gray-500 mb-3">
                                Registrada el {miEvaluacion.fecha} por {miEvaluacion.evaluador}
                            </p>
                        )}

                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            {bloqueado && !miEvaluacion ? (
                                <p className="text-sm text-gray-400 italic">
                                    El expediente ya tiene calificación final. No registró evaluación.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    <p className="text-xs text-gray-500">
                                        Ingrese nota 1.0 – 5.0 por área. Los %T se calculan desde sus horas reales registradas.
                                        Fórmula: min(Σ(%T × N) / 100, 5.0)
                                    </p>

                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox"
                                            checked={evalForm.data.sin_calificacion}
                                            disabled={bloqueado}
                                            onChange={e => evalForm.setData('sin_calificacion', e.target.checked)}
                                            className="rounded border-gray-300" />
                                        Sin calificación (casos especiales)
                                    </label>
                                    {evalForm.data.sin_calificacion && (
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                                Motivo sin calificación <span className="text-red-500">*</span>
                                            </label>
                                            <textarea rows={2} value={evalForm.data.motivo_sc}
                                                disabled={bloqueado}
                                                onChange={e => evalForm.setData('motivo_sc', e.target.value)}
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none" />
                                        </div>
                                    )}

                                    {!evalForm.data.sin_calificacion && categorias
                                        .filter(cat => cat.slug !== 'formacion_continua')
                                        .map(cat => {
                                            const campo = SLUGS_A_CAMPO[cat.slug];
                                            const val   = evalForm.data[campo] ?? 3;
                                            return (
                                                <div key={cat.id}>
                                                    <div className="flex items-center justify-between mb-1">
                                                        <label className="text-xs font-medium text-gray-700">
                                                            {AREA_LABELS[cat.slug] ?? cat.nombre}
                                                        </label>
                                                        <span className="text-[10px] text-gray-400 tabular-nums">
                                                            {Number(pesosEfectivos[SLUG_A_REGLAMENTO[cat.slug] ?? cat.slug] ?? 0).toFixed(2)}%T
                                                        </span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        min="1.0" max="5.0" step="0.1"
                                                        value={val}
                                                        disabled={bloqueado}
                                                        onChange={e => {
                                                            const v = parseFloat(e.target.value);
                                                            if (!isNaN(v)) evalForm.setData(campo, Math.min(5, Math.max(1, v)));
                                                        }}
                                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-[#1B2D6B] font-semibold focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 disabled:bg-gray-50"
                                                    />
                                                </div>
                                            );
                                        })
                                    }

                                    {!evalForm.data.sin_calificacion && (
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                                Otras actividades (bonus externo al 100%)
                                            </label>
                                            <select
                                                value={evalForm.data.extra_otras_actividades}
                                                disabled={bloqueado}
                                                onChange={e => evalForm.setData('extra_otras_actividades', parseFloat(e.target.value))}
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-[#1B2D6B] font-semibold focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 disabled:bg-gray-50"
                                            >
                                                <option value={0}>Sin bonus (0)</option>
                                                <option value={0.1}>+0.1</option>
                                                <option value={0.2}>+0.2</option>
                                                <option value={0.3}>+0.3</option>
                                            </select>
                                            <p className="text-xs text-gray-400 mt-1">
                                                Se suma a la nota ponderada. Nota final = mín(ponderado + bonus, 5.0)
                                            </p>
                                        </div>
                                    )}

                                    <div className="rounded-lg bg-[#1B2D6B]/5 border border-[#1B2D6B]/20 p-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-semibold text-gray-700">Nota final calculada</span>
                                            <span className="text-2xl font-bold text-[#1B2D6B]">{notaCalculada.toFixed(2)}</span>
                                        </div>
                                        <p className="text-sm text-gray-600 mt-1">
                                            Concepto: <strong>{CONCEPTOS[conceptoCalculado]}</strong>
                                        </p>
                                        <p className="text-xs text-gray-400 mt-1">
                                            Excelente 4.5–5.0 · Muy Bueno 4.0–4.4 · Bueno 3.5–3.9 · Regular 2.7–3.4 · Deficiente &lt;2.7
                                        </p>
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className="text-xs font-medium text-gray-600">
                                                Retroalimentación
                                            </label>
                                            <span className={`text-xs ${evalForm.data.comentario.length > 550 ? 'text-red-500 font-semibold' : 'text-gray-400'}`}>
                                                {evalForm.data.comentario.length}/600
                                            </span>
                                        </div>
                                        <textarea
                                            rows={4}
                                            maxLength={600}
                                            value={evalForm.data.comentario}
                                            disabled={bloqueado}
                                            onChange={e => evalForm.setData('comentario', e.target.value)}
                                            placeholder="Observaciones y retroalimentación para el académico..."
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 resize-none disabled:bg-gray-50"
                                        />
                                    </div>

                                    {!bloqueado && (
                                        <button type="submit" disabled={evalForm.processing}
                                            className="w-full px-5 py-2.5 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] disabled:opacity-40">
                                            {evalForm.processing ? 'Guardando...' : miEvaluacion ? 'Actualizar mi evaluación' : 'Guardar evaluación'}
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>

                        {todasEvaluaciones.length > 0 && (
                            <div className="mt-4 bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-xs font-semibold text-gray-500 uppercase mb-3">Evaluaciones del CCA</h3>
                                <ul className="space-y-2">
                                    {todasEvaluaciones.map((ev, i) => (
                                        <li key={i} className="flex justify-between text-sm">
                                            <span className="text-gray-700">{ev.evaluador}</span>
                                            <span className="font-semibold">{Number(ev.nota_final).toFixed(2)} / 5.0</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
                </form>

                {!bloqueado && todasEvaluaciones.length > 0 && (
                    <div className="mt-6 max-w-xl lg:ml-auto bg-white rounded-xl border border-gray-200 p-5">
                        <h3 className="text-sm font-semibold text-gray-800 mb-1">Registrar calificación final</h3>
                        <p className="text-xs text-gray-500 mb-3">
                            Promedio de {todasEvaluaciones.length} evaluación(es) con fórmula CAD.
                        </p>
                        <form onSubmit={submitFinal} className="space-y-3">
                            <div>
                                <div className="flex justify-end mb-1">
                                    <span className={`text-xs ${finalForm.data.observacion.length > 550 ? 'text-red-500 font-semibold' : 'text-gray-400'}`}>
                                        {finalForm.data.observacion.length}/600
                                    </span>
                                </div>
                                <textarea rows={2} value={finalForm.data.observacion}
                                    maxLength={600}
                                    onChange={e => finalForm.setData('observacion', e.target.value)}
                                    placeholder="Observación general del CCA..."
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none" />
                            </div>
                            <button type="submit" disabled={finalForm.processing}
                                className="px-5 py-2.5 bg-green-700 text-white text-sm font-medium rounded-lg hover:bg-green-800 disabled:opacity-40">
                                {finalForm.processing ? 'Registrando...' : 'Registrar calificación final'}
                            </button>
                        </form>
                    </div>
                )}
            </AppLayout>
        </>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <p className="text-xs text-gray-400">{label}</p>
            <p className="font-medium text-gray-800 mt-0.5">{value || '—'}</p>
        </div>
    );
}
