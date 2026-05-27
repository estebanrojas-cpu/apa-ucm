import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADOS = {
    carga_cerrada: { label: 'Por evaluar',   cls: 'bg-blue-100 text-blue-700' },
    en_evaluacion: { label: 'En evaluación', cls: 'bg-purple-100 text-purple-700' },
    evaluado:      { label: 'Evaluado',      cls: 'bg-green-100 text-green-700' },
};

const CALIFICACIONES = {
    muy_bueno:  'Muy Bueno',
    bueno:      'Bueno',
    aceptable:  'Aceptable',
    deficiente: 'Deficiente',
};

const SLUGS_A_CAMPO = {
    docencia:         'puntaje_docencia',
    investigacion:    'puntaje_investigacion',
    vinculacion:      'puntaje_vinculacion',
    gestion:          'puntaje_gestion',
    formacion_continua: 'puntaje_formacion',
};

export default function EvaluarExpediente({
    nomina, categorias, evidenciasPorCategoria,
    miEvaluacion, todasEvaluaciones, calificacionFinal,
}) {
    const { flash } = usePage().props;
    const badge = ESTADOS[nomina.estado] ?? { label: nomina.estado, cls: 'bg-gray-100 text-gray-600' };
    const yaEvaluado = nomina.estado === 'evaluado';

    const evalForm = useForm({
        puntaje_docencia:      miEvaluacion?.puntaje_docencia      ?? 0,
        puntaje_investigacion: miEvaluacion?.puntaje_investigacion ?? 0,
        puntaje_vinculacion:   miEvaluacion?.puntaje_vinculacion   ?? 0,
        puntaje_gestion:       miEvaluacion?.puntaje_gestion       ?? 0,
        puntaje_formacion:     miEvaluacion?.puntaje_formacion     ?? 0,
        comentario:            miEvaluacion?.comentario            ?? '',
    });

    const finalForm = useForm({ observacion: '' });

    const totalActual = Object.values({
        a: evalForm.data.puntaje_docencia,
        b: evalForm.data.puntaje_investigacion,
        c: evalForm.data.puntaje_vinculacion,
        d: evalForm.data.puntaje_gestion,
        e: evalForm.data.puntaje_formacion,
    }).reduce((s, v) => s + Number(v), 0);

    function submitEval(e) {
        e.preventDefault();
        evalForm.post(`/cca/expedientes/${nomina.id}/evaluar`, { preserveScroll: true });
    }

    function submitFinal(e) {
        e.preventDefault();
        finalForm.post(`/cca/expedientes/${nomina.id}/finalizar`, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Evaluar — ${nomina.academico.name}`} />
            <AppLayout title="Evaluación de Expediente">

                <div className="-mt-4 mb-6">
                    <Link href="/cca/expedientes"
                        className="text-sm text-[#0096D6] hover:underline flex items-center gap-1">
                        <BackIcon /> Volver a expedientes
                    </Link>
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

                {/* Cabecera */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6 flex flex-wrap items-start gap-x-8 gap-y-3">
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Académico</p>
                        <p className="font-semibold text-gray-900 text-sm mt-0.5">{nomina.academico.name}</p>
                        <p className="text-xs text-gray-500">{nomina.academico.rut} · {nomina.academico.email}</p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Estado</p>
                        <span className={`inline-block mt-1 text-xs font-semibold px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                            {badge.label}
                        </span>
                    </div>
                    {todasEvaluaciones.length > 0 && (
                        <div>
                            <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Evaluaciones registradas</p>
                            <p className="text-sm font-semibold text-gray-900 mt-0.5">{todasEvaluaciones.length}</p>
                        </div>
                    )}
                </div>

                {/* Calificación final ya registrada */}
                {calificacionFinal && (
                    <div className="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
                        <p className="text-xs font-semibold text-green-700 uppercase tracking-wide mb-2">Calificación Final</p>
                        <p className="text-2xl font-bold text-green-800">
                            {CALIFICACIONES[calificacionFinal.calificacion]} — {calificacionFinal.puntaje_total} pts
                        </p>
                        <p className="text-xs text-green-600 mt-1">Registrada el {calificacionFinal.fecha}</p>
                        {calificacionFinal.observacion && (
                            <p className="text-sm text-green-700 mt-2">{calificacionFinal.observacion}</p>
                        )}
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
                                        <div className="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                            <span className="text-sm font-semibold text-gray-700">{cat.nombre}</span>
                                            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                archivos.length > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                            }`}>
                                                {archivos.length > 0 ? `${archivos.length} archivo${archivos.length > 1 ? 's' : ''}` : 'Sin archivos'}
                                            </span>
                                        </div>
                                        {archivos.length > 0 && (
                                            <ul className="px-4 py-2 space-y-1.5">
                                                {archivos.map(ev => (
                                                    <li key={ev.id} className="flex items-center gap-2 text-sm">
                                                        <FileIcon />
                                                        <span className="flex-1 text-gray-700 truncate">{ev.nombre_archivo}</span>
                                                        <a href={ev.url_descarga}
                                                            className="text-xs text-[#0096D6] hover:underline shrink-0">
                                                            Descargar
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Formulario de evaluación */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            {miEvaluacion ? 'Mi evaluación' : 'Registrar evaluación'}
                        </h2>

                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            {yaEvaluado && !miEvaluacion ? (
                                <p className="text-sm text-gray-400 italic">No registraste una evaluación para este expediente.</p>
                            ) : (
                                <form onSubmit={submitEval} className="space-y-4">
                                    <p className="text-xs text-gray-500 mb-2">Puntaje por categoría (0–20 puntos cada una, máximo 100)</p>

                                    {categorias.map(cat => {
                                        const campo = SLUGS_A_CAMPO[cat.slug] ?? `puntaje_${cat.slug}`;
                                        const val   = evalForm.data[campo] ?? 0;
                                        return (
                                            <div key={cat.id}>
                                                <div className="flex items-center justify-between mb-1">
                                                    <label className="text-xs font-medium text-gray-700">{cat.nombre}</label>
                                                    <span className="text-xs font-bold text-[#1B2D6B] w-8 text-right">{val}</span>
                                                </div>
                                                <input
                                                    type="range"
                                                    min={0} max={20} step={1}
                                                    value={val}
                                                    disabled={yaEvaluado}
                                                    onChange={e => evalForm.setData(campo, parseInt(e.target.value))}
                                                    className="w-full accent-[#1B2D6B] disabled:opacity-50"
                                                />
                                                {evalForm.errors[campo] && (
                                                    <p className="text-xs text-red-600 mt-0.5">{evalForm.errors[campo]}</p>
                                                )}
                                            </div>
                                        );
                                    })}

                                    <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                                        <span className="text-sm font-semibold text-gray-700">Total</span>
                                        <span className={`text-xl font-bold ${
                                            totalActual >= 80 ? 'text-green-600'
                                            : totalActual >= 60 ? 'text-blue-600'
                                            : totalActual >= 40 ? 'text-amber-600'
                                            : 'text-red-600'
                                        }`}>
                                            {totalActual} / 100
                                        </span>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Comentario (opcional)</label>
                                        <textarea
                                            rows={3}
                                            value={evalForm.data.comentario}
                                            disabled={yaEvaluado}
                                            onChange={e => evalForm.setData('comentario', e.target.value)}
                                            placeholder="Observaciones sobre el expediente..."
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B] resize-none disabled:bg-gray-50 disabled:text-gray-500"
                                        />
                                    </div>

                                    {!yaEvaluado && (
                                        <div className="flex justify-end">
                                            <button
                                                type="submit"
                                                disabled={evalForm.processing}
                                                className="px-5 py-2.5 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] disabled:opacity-40 transition-colors"
                                            >
                                                {evalForm.processing ? 'Guardando...' : miEvaluacion ? 'Actualizar evaluación' : 'Registrar evaluación'}
                                            </button>
                                        </div>
                                    )}
                                </form>
                            )}
                        </div>

                        {/* Evaluaciones de otros miembros */}
                        {todasEvaluaciones.length > 0 && (
                            <div className="mt-4 bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                    Resumen de evaluaciones
                                </h3>
                                <ul className="space-y-2">
                                    {todasEvaluaciones.map((ev, i) => (
                                        <li key={i} className="flex items-center justify-between text-sm">
                                            <span className="text-gray-700">{ev.evaluador}</span>
                                            <span className="font-semibold text-gray-900">{ev.puntaje_total} pts</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Registrar calificación final */}
                        {!yaEvaluado && todasEvaluaciones.length > 0 && (
                            <div className="mt-4 bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-800 mb-1">Registrar calificación final</h3>
                                <p className="text-xs text-gray-500 mb-3">
                                    Se calculará el promedio de las {todasEvaluaciones.length} evaluación(es) registradas.
                                </p>
                                <form onSubmit={submitFinal} className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Observación (opcional)</label>
                                        <textarea
                                            rows={2}
                                            value={finalForm.data.observacion}
                                            onChange={e => finalForm.setData('observacion', e.target.value)}
                                            placeholder="Observación general del CCA..."
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B] resize-none"
                                        />
                                    </div>
                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={finalForm.processing}
                                            className="px-5 py-2.5 bg-green-700 text-white text-sm font-medium rounded-lg hover:bg-green-800 disabled:opacity-40 transition-colors"
                                        >
                                            {finalForm.processing ? 'Registrando...' : 'Registrar calificación final'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}
                    </div>
                </div>

            </AppLayout>
        </>
    );
}

function FileIcon() {
    return (
        <svg className="w-4 h-4 text-[#0096D6] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    );
}

function BackIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
    );
}
