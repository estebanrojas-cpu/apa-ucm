import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
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

function calcularNotaFinal(notas, categorias, pesosReglamento) {
    let suma = 0;
    categorias.forEach(cat => {
        const campo = SLUGS_A_CAMPO[cat.slug];
        const regKey = SLUG_A_REGLAMENTO[cat.slug] ?? cat.slug;
        const nota = Number(notas[campo] ?? 1);
        const peso = Number(pesosReglamento[regKey] ?? cat.peso ?? 0);
        suma += (peso * nota) / 100;
    });
    return Math.min(Math.round(suma * 100) / 100, 5.0);
}

function conceptoDesdeNota(nota) {
    if (nota >= 4.5) return 'excelente';
    if (nota >= 4.0) return 'muy_bueno';
    if (nota >= 3.5) return 'bueno';
    if (nota >= 2.7) return 'regular';
    return 'deficiente';
}

export default function EvaluarExpediente({
    nomina, categorias, pesosReglamento, evidenciasPorCategoria,
    miEvaluacion, todasEvaluaciones, calificacionFinal, esApelacion, sinCompromisoApa,
}) {
    const { flash } = usePage().props;
    const badge = ESTADOS[nomina.estado] ?? { label: nomina.estado, cls: 'bg-gray-100 text-gray-600' };
    const bloqueado = nomina.estado === 'evaluado';

    const evalForm = useForm({
        sin_calificacion:      miEvaluacion?.sin_calificacion      ?? false,
        motivo_sc:             miEvaluacion?.motivo_sc             ?? '',
        puntaje_docencia:      miEvaluacion?.puntaje_docencia      ?? 3.0,
        puntaje_investigacion: miEvaluacion?.puntaje_investigacion ?? 3.0,
        puntaje_vinculacion:   miEvaluacion?.puntaje_vinculacion   ?? 3.0,
        puntaje_gestion:       miEvaluacion?.puntaje_gestion       ?? 3.0,
        puntaje_formacion:     miEvaluacion?.puntaje_formacion     ?? 3.0,
        comentario:            miEvaluacion?.comentario            ?? '',
    });

    const finalForm = useForm({ observacion: '' });

    const notaCalculada = useMemo(
        () => evalForm.data.sin_calificacion
            ? 0
            : calcularNotaFinal(evalForm.data, categorias, pesosReglamento),
        [evalForm.data, categorias, pesosReglamento]
    );

    const conceptoCalculado = conceptoDesdeNota(notaCalculada);

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
                        Este académico no ha declarado su distribución APA.
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

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {/* Evidencias */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Documentación entregada
                        </h2>
                        <div className="space-y-3">
                            {categorias.map(cat => {
                                const archivos = evidenciasPorCategoria[cat.id] ?? [];
                                return (
                                    <div key={cat.id} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                        <div className="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                                            <span className="text-sm font-semibold text-gray-700">
                                                {AREA_LABELS[cat.slug] ?? cat.nombre}
                                            </span>
                                        </div>
                                        {archivos.length > 0 ? (
                                            <ul className="px-4 py-2 space-y-1.5">
                                                {archivos.map(ev => (
                                                    <li key={ev.id} className="flex items-center gap-2 text-sm">
                                                        <span className="flex-1 truncate text-gray-700">{ev.nombre_archivo}</span>
                                                        <a href={ev.url_descarga} className="text-xs text-[#0096D6] hover:underline">Descargar</a>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="px-4 py-3 text-xs text-gray-400">Sin archivos</p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Formulario evaluación */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            {miEvaluacion ? 'Mi evaluación' : 'Registrar evaluación'}
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
                                <form onSubmit={submitEval} className="space-y-4">
                                    <p className="text-xs text-gray-500">
                                        Ingrese nota 1.0 – 5.0 por área. Fórmula: min(Σ(%T × N) / 100, 5.0)
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

                                    {!evalForm.data.sin_calificacion && categorias.map(cat => {
                                        const campo = SLUGS_A_CAMPO[cat.slug];
                                        const val   = evalForm.data[campo] ?? 3;
                                        return (
                                            <div key={cat.id}>
                                                <div className="flex items-center justify-between mb-1">
                                                    <label className="text-xs font-medium text-gray-700">
                                                        {AREA_LABELS[cat.slug] ?? cat.nombre}
                                                    </label>
                                                    <span className="text-xs font-bold text-[#1B2D6B]">{Number(val).toFixed(1)}</span>
                                                </div>
                                                <input
                                                    type="range"
                                                    min={1} max={5} step={0.1}
                                                    value={val}
                                                    disabled={bloqueado}
                                                    onChange={e => evalForm.setData(campo, parseFloat(e.target.value))}
                                                    className="w-full accent-[#1B2D6B] disabled:opacity-50"
                                                />
                                            </div>
                                        );
                                    })}

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
                                        <label className="block text-xs font-medium text-gray-600 mb-1">
                                            Retroalimentación
                                        </label>
                                        <textarea
                                            rows={4}
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
                                </form>
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

                        {!bloqueado && todasEvaluaciones.length > 0 && (
                            <div className="mt-4 bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-800 mb-1">Registrar calificación final</h3>
                                <p className="text-xs text-gray-500 mb-3">
                                    Promedio de {todasEvaluaciones.length} evaluación(es) con fórmula CAD.
                                </p>
                                <form onSubmit={submitFinal} className="space-y-3">
                                    <textarea rows={2} value={finalForm.data.observacion}
                                        onChange={e => finalForm.setData('observacion', e.target.value)}
                                        placeholder="Observación general del CCA..."
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none" />
                                    <button type="submit" disabled={finalForm.processing}
                                        className="px-5 py-2.5 bg-green-700 text-white text-sm font-medium rounded-lg hover:bg-green-800 disabled:opacity-40">
                                        {finalForm.processing ? 'Registrando...' : 'Registrar calificación final'}
                                    </button>
                                </form>
                            </div>
                        )}
                    </div>
                </div>
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
