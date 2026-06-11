import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const CONCEPTO_COLOR = {
    Excelente:  'bg-green-100 text-green-800',
    'Muy Bueno':'bg-blue-100 text-blue-800',
    Bueno:      'bg-teal-100 text-teal-800',
    Regular:    'bg-yellow-100 text-yellow-800',
    Deficiente: 'bg-red-100 text-red-800',
};

const ESTADO_LABELS = {
    pendiente:      'Pendiente',
    en_carga:       'En carga',
    en_evaluacion:  'En evaluación',
    evaluado:       'Evaluado',
    apelado:        'Apelado',
    cerrado:        'Cerrado',
};

export default function Expediente({ nomina }) {
    const { academico, calificacion, evaluaciones, evidencias, apelacion } = nomina;

    return (
        <>
            <Head title={`Expediente — ${academico.name}`} />
            <AppLayout title="Expediente (solo lectura)">

                <Link href="/vicerrectora/academicos"
                    className="text-sm text-gray-500 hover:text-gray-700 mb-5 inline-block">
                    ← Volver a la lista
                </Link>

                {/* Datos del académico */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
                    <div className="flex items-start justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold text-gray-900 text-base">{academico.name}</p>
                            <p className="text-sm text-gray-500">{academico.rut} · {academico.email}</p>
                            <p className="text-sm text-gray-500 mt-0.5">
                                {academico.facultad} · {academico.categoria}
                            </p>
                        </div>
                        <span className="text-xs font-medium bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full">
                            {ESTADO_LABELS[nomina.estado] ?? nomina.estado}
                        </span>
                    </div>
                </div>

                {/* Calificación final */}
                {calificacion ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            Calificación Final {calificacion.es_apelacion && <span className="ml-1 text-amber-600">(Resultado de apelación)</span>}
                        </p>
                        <div className="flex items-center gap-3 mb-3">
                            <span className="text-2xl font-bold text-gray-900">{calificacion.nota_final}</span>
                            <span className={`text-sm font-semibold px-2.5 py-0.5 rounded-full ${CONCEPTO_COLOR[calificacion.concepto] ?? 'bg-gray-100 text-gray-600'}`}>
                                {calificacion.concepto}
                            </span>
                            <span className="text-xs text-gray-400 ml-auto">Determinada el {calificacion.fecha}</span>
                        </div>
                        {calificacion.observacion && (
                            <div className="border-l-2 border-gray-200 pl-3 text-sm text-gray-600 italic">
                                {calificacion.observacion}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-5 mb-4 text-sm text-gray-400 text-center">
                        Sin calificación final registrada.
                    </div>
                )}

                {/* Retroalimentación CCA */}
                {evaluaciones.filter(e => e.comentario).length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            Retroalimentación CCA
                        </p>
                        <div className="space-y-3">
                            {evaluaciones.filter(e => e.comentario).map((e, i) => (
                                <div key={i} className="text-sm text-gray-700">
                                    <span className="font-medium text-gray-900">{e.evaluador}:</span>{' '}
                                    <span className="text-gray-600">{e.comentario}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Apelación */}
                {apelacion && (
                    <div className="bg-white rounded-xl border border-amber-200 p-5 mb-4">
                        <p className="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">
                            Apelación — nivel {apelacion.destino === 'ccda' ? 'CCDA' : 'CCA'}
                        </p>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-medium bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full capitalize">
                                {apelacion.estado.replace('_', ' ')}
                            </span>
                        </div>
                        {apelacion.motivo && (
                            <p className="text-sm text-gray-600 italic">{apelacion.motivo}</p>
                        )}
                    </div>
                )}

                {/* Evidencias */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Evidencias cargadas ({evidencias.length})
                    </p>
                    {evidencias.length === 0 ? (
                        <p className="text-sm text-gray-400">Sin evidencias.</p>
                    ) : (
                        <div className="divide-y divide-gray-50">
                            {evidencias.map((ev, i) => (
                                <div key={i} className="flex items-center justify-between py-2 text-sm">
                                    <span className="text-gray-800 truncate max-w-xs">{ev.nombre}</span>
                                    <div className="flex items-center gap-3 text-xs text-gray-400 shrink-0 ml-4">
                                        {ev.categoria && <span>{ev.categoria}</span>}
                                        {ev.fecha && <span>{ev.fecha}</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <p className="text-xs text-gray-400 mt-4 pt-3 border-t border-gray-100">
                        Vista de solo lectura. La vicerrectoría no modifica calificaciones ni expedientes.
                    </p>
                </div>

            </AppLayout>
        </>
    );
}
