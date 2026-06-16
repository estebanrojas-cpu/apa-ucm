import { Head, Link, useForm, usePage } from '@inertiajs/react';

const MIME_PREVIEWABLE = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
import { useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const AREA_LABELS = {
    docencia:    'Actividades de Docencia',
    investigacion: 'Actividades de Investigación',
    vinculacion: 'Extensión y Vinculación',
    gestion:     'Administración Académica',
};

const SLUGS_A_CAMPO = {
    docencia:     'puntaje_docencia',
    investigacion:'puntaje_investigacion',
    vinculacion:  'puntaje_vinculacion',
    gestion:      'puntaje_gestion',
};

function calcularNota(data, categorias, pesos, extra = 0) {
    let suma = 0;
    categorias
        .filter(cat => cat.slug !== 'formacion_continua')
        .forEach(cat => {
            const campo = SLUGS_A_CAMPO[cat.slug];
            const nota  = parseFloat(data[campo]) || 0;
            suma += (cat.peso * nota) / 100;
        });
    return Math.min(Math.round((suma + Number(extra)) * 100) / 100, 5.0);
}

export default function EvaluarApelacion({
    nomina,
    apelacion,
    calificacionOriginal,
    categorias,
    pesosReglamento,
    evidenciasPorCategoria,
    miEvaluacion,
    calificacionFinal,
    sinCompromisoApa,
    compromisosSemestres,
}) {
    const { flash } = usePage().props;
    const bloqueado = calificacionFinal !== null;

    const evalForm = useForm({
        puntaje_docencia:         miEvaluacion?.puntaje_docencia        ?? 3,
        puntaje_investigacion:    miEvaluacion?.puntaje_investigacion   ?? 3,
        puntaje_vinculacion:      miEvaluacion?.puntaje_vinculacion     ?? 3,
        puntaje_gestion:          miEvaluacion?.puntaje_gestion         ?? 3,
        extra_otras_actividades:  miEvaluacion?.extra_otras_actividades ?? 0,
        sin_calificacion:         miEvaluacion?.sin_calificacion        ?? false,
        motivo_sc:                miEvaluacion?.motivo_sc               ?? '',
        comentario:               miEvaluacion?.comentario              ?? '',
    });

    const finalForm = useForm({ observacion: calificacionFinal?.observacion ?? '' });

    const notaCalculada = useMemo(
        () => evalForm.data.sin_calificacion
            ? 0
            : calcularNota(evalForm.data, categorias, pesosReglamento, evalForm.data.extra_otras_actividades),
        [evalForm.data, categorias, pesosReglamento]
    );

    const conceptoLabel = (nota) => {
        if (nota >= 4.6) return 'Excelente';
        if (nota >= 3.8) return 'Muy Bueno';
        if (nota >= 3.0) return 'Bueno';
        if (nota >= 2.0) return 'Regular';
        return 'Deficiente';
    };

    function submitEval(e) {
        e.preventDefault();
        evalForm.post(`/analista/apelaciones/${nomina.id}/evaluar`, { preserveScroll: true });
    }

    function submitFinal(e) {
        e.preventDefault();
        finalForm.post(`/analista/apelaciones/${nomina.id}/finalizar`, { preserveScroll: true });
    }

    const evidenciasTotal = Object.values(evidenciasPorCategoria).flat().length;

    return (
        <>
            <Head title="Evaluar Apelación CCDA" />
            <AppLayout title="Evaluar Apelación — 2do Nivel CCDA">
                <div className="flex items-center gap-3 -mt-4 mb-6">
                    <Link
                        href="/analista/apelaciones"
                        className="text-xs text-gray-500 hover:text-gray-700"
                    >
                        ← Volver a apelaciones
                    </Link>
                    <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800">
                        CCDA · 2do nivel
                    </span>
                </div>

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

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Columna izquierda: info */}
                    <div className="space-y-4">

                        {/* Datos del académico */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Académico</h3>
                            <p className="font-medium text-gray-900">{nomina.academico.name}</p>
                            <p className="text-xs text-gray-400">{nomina.academico.rut}</p>
                            <div className="mt-3 space-y-1 text-xs text-gray-600">
                                <p><span className="text-gray-400">Facultad:</span> {nomina.academico.facultad ?? '—'}</p>
                                <p><span className="text-gray-400">Depto.:</span> {nomina.academico.departamento ?? '—'}</p>
                                <p><span className="text-gray-400">Categoría:</span> {nomina.academico.categoria_academica}</p>
                                {nomina.academico.nota_anterior != null && (
                                    <p><span className="text-gray-400">Período anterior:</span> {nomina.academico.nota_anterior} — {nomina.academico.concepto_anterior ?? '—'}</p>
                                )}
                            </div>
                        </div>

                        {/* Calificación original */}
                        {calificacionOriginal && (
                            <div className="bg-white rounded-xl border border-red-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">Calificación original (CCA)</h3>
                                <p className="text-2xl font-bold text-red-700">{calificacionOriginal.nota_final}</p>
                                <p className="text-sm text-red-600 font-medium">{calificacionOriginal.concepto}</p>
                                {calificacionOriginal.observacion && (
                                    <p className="text-xs text-gray-500 mt-2">{calificacionOriginal.observacion}</p>
                                )}
                            </div>
                        )}

                        {/* Motivo de apelación */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-2">Motivo de apelación</h3>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{apelacion.motivo}</p>
                            {apelacion.resolucion && (
                                <div className="mt-3 pt-3 border-t border-gray-100">
                                    <p className="text-xs text-gray-500 font-medium mb-1">Observación del secretario:</p>
                                    <p className="text-xs text-gray-600">{apelacion.resolucion}</p>
                                </div>
                            )}
                        </div>

                        {/* Distribución APA */}
                        {compromisosSemestres.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Distribución APA</h3>
                                {compromisosSemestres.map(s => (
                                    <div key={s.semestre} className="mb-3 last:mb-0">
                                        <p className="text-xs font-medium text-gray-500 mb-1">{s.label}</p>
                                        <div className="grid grid-cols-2 gap-x-3 text-xs text-gray-600">
                                            <span>Docencia: {s.pct_docencia}%</span>
                                            <span>Investigación: {s.pct_investigacion}%</span>
                                            <span>Extensión: {s.pct_extension}%</span>
                                            <span>Administración: {s.pct_administracion}%</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {sinCompromisoApa && (
                            <div className="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                                Sin compromiso APA confirmado. Los pesos son del reglamento base.
                            </div>
                        )}
                    </div>

                    {/* Columna central: evidencias */}
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-1">
                                Evidencias de apelación
                                <span className="ml-2 text-xs font-normal text-gray-400">({evidenciasTotal} archivos)</span>
                            </h3>
                            {evidenciasTotal === 0 ? (
                                <p className="text-xs text-gray-400 mt-3">El académico no cargó evidencias adicionales.</p>
                            ) : (
                                <div className="mt-3 space-y-4">
                                    {categorias
                                        .filter(cat => evidenciasPorCategoria[cat.id]?.length > 0)
                                        .map(cat => (
                                            <div key={cat.id}>
                                                <p className="text-xs font-medium text-gray-500 mb-1">
                                                    {AREA_LABELS[cat.slug] ?? cat.nombre}
                                                </p>
                                                <div className="space-y-1">
                                                    {evidenciasPorCategoria[cat.id].map(ev => (
                                                        <div key={ev.id} className="flex items-center justify-between gap-2">
                                                            <p className="text-xs text-gray-700 truncate">{ev.nombre_archivo}</p>
                                                            <div className="flex items-center gap-1.5 shrink-0">
                                                                {MIME_PREVIEWABLE.includes(ev.mime_type) && (
                                                                    <a href={ev.url_preview} target="_blank" rel="noopener noreferrer"
                                                                        className="text-xs px-2 py-0.5 rounded bg-[#1B2D6B] text-white hover:bg-[#152558] transition-colors">
                                                                        Ver
                                                                    </a>
                                                                )}
                                                                <a href={ev.url_descarga} target="_blank" rel="noreferrer"
                                                                    className="text-xs text-[#0096D6] hover:underline">
                                                                    Descargar
                                                                </a>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))
                                    }
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Columna derecha: evaluación */}
                    <div className="space-y-4">

                        {/* Resultado CCDA ya registrado */}
                        {calificacionFinal && (
                            <div className="bg-white rounded-xl border border-green-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">Resolución CCDA registrada</h3>
                                <p className="text-2xl font-bold text-[#1B2D6B]">{calificacionFinal.nota_final}</p>
                                <p className="text-sm font-medium text-gray-700">{calificacionFinal.concepto_label}</p>
                                <p className="text-xs text-gray-400 mt-1">{calificacionFinal.fecha}</p>
                                {calificacionFinal.observacion && (
                                    <p className="text-xs text-gray-500 mt-2">{calificacionFinal.observacion}</p>
                                )}
                            </div>
                        )}

                        {/* Formulario de evaluación */}
                        {!bloqueado && (
                            <form onSubmit={submitEval} className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                                <h3 className="text-sm font-semibold text-gray-900">Evaluación CCDA</h3>

                                <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={evalForm.data.sin_calificacion}
                                        onChange={e => evalForm.setData('sin_calificacion', e.target.checked)}
                                        className="rounded border-gray-300"
                                    />
                                    Sin calificación (licencia u otro motivo)
                                </label>

                                {evalForm.data.sin_calificacion ? (
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Motivo</label>
                                        <textarea
                                            rows={3}
                                            value={evalForm.data.motivo_sc}
                                            onChange={e => evalForm.setData('motivo_sc', e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                        />
                                    </div>
                                ) : (
                                    <>
                                        {categorias
                                            .filter(cat => cat.slug !== 'formacion_continua')
                                            .map(cat => {
                                                const campo = SLUGS_A_CAMPO[cat.slug];
                                                const val   = evalForm.data[campo] ?? 3;
                                                return (
                                                    <div key={cat.id}>
                                                        <div className="flex items-center justify-between mb-1">
                                                            <label className="text-xs font-medium text-gray-700">
                                                                {AREA_LABELS[cat.slug] ?? cat.nombre}
                                                                <span className="ml-1 text-gray-400">({cat.peso}%)</span>
                                                            </label>
                                                        </div>
                                                        <input
                                                            type="number"
                                                            min="1.0" max="5.0" step="0.1"
                                                            value={val}
                                                            onChange={e => {
                                                                const v = parseFloat(e.target.value);
                                                                if (!isNaN(v)) evalForm.setData(campo, Math.min(5, Math.max(1, v)));
                                                            }}
                                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-[#1B2D6B] font-semibold focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                                        />
                                                    </div>
                                                );
                                            })
                                        }

                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                                Otras actividades (bonus externo)
                                            </label>
                                            <select
                                                value={evalForm.data.extra_otras_actividades}
                                                onChange={e => evalForm.setData('extra_otras_actividades', parseFloat(e.target.value))}
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-[#1B2D6B] font-semibold focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                            >
                                                <option value={0}>Sin bonus (0)</option>
                                                <option value={0.1}>+0.1</option>
                                                <option value={0.2}>+0.2</option>
                                                <option value={0.3}>+0.3</option>
                                            </select>
                                        </div>

                                        {/* Nota calculada */}
                                        <div className="rounded-lg bg-[#1B2D6B]/5 border border-[#1B2D6B]/20 p-4 text-center">
                                            <p className="text-xs text-gray-500 mb-1">Nota calculada</p>
                                            <p className="text-3xl font-bold text-[#1B2D6B]">{notaCalculada.toFixed(2)}</p>
                                            <p className="text-sm font-medium text-gray-600 mt-0.5">{conceptoLabel(notaCalculada)}</p>
                                        </div>
                                    </>
                                )}

                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Comentario (opcional)</label>
                                    <textarea
                                        rows={2}
                                        value={evalForm.data.comentario}
                                        onChange={e => evalForm.setData('comentario', e.target.value)}
                                        maxLength={600}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={evalForm.processing}
                                    className="w-full bg-[#1B2D6B] text-white text-sm font-medium py-2.5 rounded-lg hover:bg-[#152558] disabled:opacity-50"
                                >
                                    {evalForm.processing ? 'Guardando…' : 'Guardar evaluación CCDA'}
                                </button>
                            </form>
                        )}

                        {/* Finalizar: aparece cuando hay miEvaluacion y no hay calificacionFinal */}
                        {miEvaluacion && !calificacionFinal && (
                            <form onSubmit={submitFinal} className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                                <h3 className="text-sm font-semibold text-gray-900">Registrar calificación definitiva</h3>
                                <p className="text-xs text-gray-500">
                                    Nota actual: <strong>{miEvaluacion.nota_final?.toFixed(2)}</strong> — {conceptoLabel(miEvaluacion.nota_final ?? 0)}.
                                    Esta acción no puede deshacerse.
                                </p>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Observación CCDA (opcional)</label>
                                    <textarea
                                        rows={2}
                                        value={finalForm.data.observacion}
                                        onChange={e => finalForm.setData('observacion', e.target.value)}
                                        maxLength={600}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={finalForm.processing}
                                    className="w-full bg-green-700 text-white text-sm font-medium py-2.5 rounded-lg hover:bg-green-800 disabled:opacity-50"
                                >
                                    {finalForm.processing ? 'Registrando…' : 'Finalizar resolución CCDA'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
