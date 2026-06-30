import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const estadoLabels = {
    pendiente:     { label: 'Pendiente',    color: 'text-yellow-700 bg-yellow-100' },
    en_carga:      { label: 'En carga',      color: 'text-blue-700 bg-blue-100' },
    en_evaluacion: { label: 'En evaluación', color: 'text-purple-700 bg-purple-100' },
    evaluado:      { label: 'Evaluado',      color: 'text-green-700 bg-green-100' },
    apelado:       { label: 'En apelación',  color: 'text-orange-700 bg-orange-100' },
    cerrado:       { label: 'Cerrado',       color: 'text-gray-700 bg-gray-100' },
};

const califColors = {
    excelente:  'text-emerald-700',
    muy_bueno:  'text-green-700',
    bueno:      'text-blue-700',
    regular:    'text-amber-700',
    deficiente: 'text-red-700',
};

const apelacionEstadosBase = {
    solicitada: { label: 'Solicitada — pendiente de revisión', cls: 'bg-yellow-50 border-yellow-200 text-yellow-800' },
    en_revision:{ label: 'Aprobada — puede cargar evidencias', cls: 'bg-blue-50 border-blue-200 text-blue-800' },
    rechazada:  { label: 'Rechazada',                         cls: 'bg-red-50 border-red-200 text-red-800' },
};

function getApelacionInfo(ap, calificacion) {
    if (!ap) return null;
    if (ap.estado === 'resuelta') {
        if (calificacion?.es_apelacion || ap.reevaluacion_pendiente === false) {
            return { label: 'Resuelta — calificación actualizada por CCA', cls: 'bg-green-50 border-green-200 text-green-800' };
        }
        return { label: 'Resuelta — en re-evaluación CCA', cls: 'bg-purple-50 border-purple-200 text-purple-800' };
    }
    return apelacionEstadosBase[ap.estado] ?? { label: ap.estado, cls: 'bg-gray-50 border-gray-200 text-gray-800' };
}

export default function Academico({ stats, periodo, compromisoApa }) {
    const { flash } = usePage().props;
    const esDaConocer        = stats?.es_da_conocer ?? false;
    const estado             = estadoLabels[stats?.estado_nomina];
    const calificacion       = stats?.calificacion ?? null;
    const apelacion          = stats?.apelacion ?? null;
    const apelacionesAbiertas = stats?.apelaciones_abiertas ?? false;
    const puedeIniciarApelacion = apelacionesAbiertas
        && stats?.estado_nomina === 'evaluado'
        && (!apelacion || apelacion.estado === 'en_revision');

    return (
        <>
            <Head title="Panel Académico" />
            <AppLayout title="Mi Panel">

                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período activo: <span className="font-medium text-gray-700">{periodo.nombre}</span>
                    </p>
                )}

                {esDaConocer && (
                    <div className="mb-6 rounded-xl bg-blue-50 border border-blue-200 px-5 py-4">
                        <p className="text-sm font-semibold text-blue-800">Se da a conocer</p>
                        <p className="text-sm text-blue-700 mt-0.5">
                            En tu rol de Decano/a o directivo/a no participas del proceso evaluativo CCA.
                            Tu calificación vigente queda registrada como referencia institucional.
                        </p>
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

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                    <StatCard label="Evidencias cargadas" value={stats?.evidencias_cargadas ?? '—'} />
                    <StatCard
                        label="Estado expediente"
                        value={
                            estado
                                ? <span className={`text-base px-2.5 py-1 rounded-full font-semibold ${estado.color}`}>{estado.label}</span>
                                : '—'
                        }
                    />
                    <StatCard
                        label="Calificación final"
                        value={
                            calificacion
                                ? (
                                    <div>
                                        <span className={`text-xl font-bold ${califColors[calificacion.calificacion] ?? 'text-gray-900'}`}>
                                            {Number(calificacion.nota_final).toFixed(2)}
                                        </span>
                                        <span className="text-sm text-gray-500 font-normal"> / 5.0</span>
                                        <p className={`text-sm font-medium mt-0.5 ${califColors[calificacion.calificacion] ?? 'text-gray-700'}`}>
                                            {calificacion.label}
                                        </p>
                                    </div>
                                )
                                : '—'
                        }
                    />
                </div>

                {/* Banner calificación */}
                {calificacion && (
                    <div className={`border rounded-xl p-5 mb-6 ${
                        calificacion.pendiente_reevaluacion
                            ? 'bg-amber-50 border-amber-200'
                            : 'bg-green-50 border-green-200'
                    }`}>
                        <p className={`text-xs font-semibold uppercase tracking-wide mb-1 ${
                            calificacion.pendiente_reevaluacion ? 'text-amber-700' : 'text-green-700'
                        }`}>
                            Calificación APA — {calificacion.es_apelacion ? 'Resultado de apelación' : 'Resultado final'}
                        </p>
                        <div className="flex items-baseline gap-2 flex-wrap">
                            <p className={`text-4xl font-bold ${califColors[calificacion.calificacion]}`}>
                                {Number(calificacion.nota_final).toFixed(2)}
                            </p>
                            <span className={`text-lg font-medium ${califColors[calificacion.calificacion]}`}>/ 5.0</span>
                            <span className={`text-lg font-semibold ml-2 ${califColors[calificacion.calificacion]}`}>
                                — {calificacion.label}
                            </span>
                        </div>
                        <p className={`text-sm mt-1 ${calificacion.pendiente_reevaluacion ? 'text-amber-700' : 'text-green-700'}`}>
                            {calificacion.pendiente_reevaluacion && (
                                <>
                                    Calificación original vigente — sujeta a re-evaluación CCA por apelación.
                                    <span className="mx-2 opacity-50">·</span>
                                </>
                            )}
                            Registrada el {calificacion.fecha}
                        </p>
                    </div>
                )}

                {/* Estado apelación activa */}
                {apelacion && (
                    <div className={`border rounded-xl p-5 mb-6 ${getApelacionInfo(apelacion, calificacion)?.cls ?? 'bg-gray-50 border-gray-200'}`}>
                        <p className="text-xs font-semibold uppercase tracking-wide mb-1 opacity-70">Apelación</p>
                        <p className="text-sm font-semibold">
                            {getApelacionInfo(apelacion, calificacion)?.label ?? apelacion.estado}
                        </p>
                        {apelacion.resolucion && (
                            <p className="text-sm mt-1 opacity-80">{apelacion.resolucion}</p>
                        )}
                        {apelacion.estado === 'en_revision' && (
                            <Link
                                href="/academico/evidencias"
                                className="inline-block mt-3 text-sm font-medium text-white bg-[#1B2D6B] px-4 py-2 rounded-lg hover:bg-[#152558] transition-colors"
                            >
                                Cargar evidencias de apelación
                            </Link>
                        )}
                    </div>
                )}

                {/* Aviso período de apelaciones */}
                {puedeIniciarApelacion && (
                    <div className="bg-orange-50 border border-orange-200 rounded-xl p-5 mb-6">
                        <p className="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-1">
                            Período de apelaciones abierto
                        </p>
                        <p className="text-sm text-orange-800 mb-3">
                            Si no está de acuerdo con su calificación, puede adjuntar evidencia adicional.
                            No se reemplaza la evidencia original — se agrega documentación nueva de respaldo.
                        </p>
                        <Link
                            href="/academico/evidencias"
                            className="inline-block px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors"
                        >
                            Ir a evidencias →
                        </Link>
                    </div>
                )}

                {compromisoApa && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
                        <div>
                            <h2 className="font-semibold text-gray-800">Compromiso APA por semestre</h2>
                            <p className="text-sm text-gray-500 mt-1">
                                Declare las horas de cada área por semestre.
                                {compromisoApa.participa_evaluacion
                                    ? ' Debe completar I y II Semestre antes de cargar evidencias.'
                                    : ` Este período es solo registro (evaluación cada ${compromisoApa.ciclo_semestres} semestres).`}
                            </p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <SemestreApaCard
                                label="I Semestre"
                                cierre={compromisoApa.s1.cierre}
                                confirmado={compromisoApa.s1.confirmado}
                                href="/academico/declaracion-apa/S1"
                                disponible
                            />
                            <SemestreApaCard
                                label="II Semestre"
                                cierre={compromisoApa.s2.cierre}
                                confirmado={compromisoApa.s2.confirmado}
                                href="/academico/declaracion-apa/S2"
                                disponible={compromisoApa.s2.disponible}
                                bloqueadoMsg="Disponible cuando cierre el I Semestre"
                            />
                        </div>
                    </div>
                )}

                {!esDaConocer && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold text-gray-800">Carga de evidencias</h2>
                            <p className="text-sm text-gray-500 mt-1">
                                {compromisoApa?.participa_evaluacion === false
                                    ? 'No aplica este período: su categoría solo registra la declaración APA semestral.'
                                    : 'Suba sus documentos por categoría APA para el período activo.'}
                            </p>
                        </div>
                        {compromisoApa?.participa_evaluacion !== false && (
                            <Link
                                href="/academico/evidencias"
                                className="px-4 py-2 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] transition-colors shrink-0 ml-4"
                            >
                                Ir a evidencias
                            </Link>
                        )}
                    </div>
                )}

            </AppLayout>
        </>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <p className="text-sm text-gray-500">{label}</p>
            <div className="text-2xl font-bold text-gray-900 mt-1">{value}</div>
        </div>
    );
}

function SemestreApaCard({ label, cierre, confirmado, href, disponible, bloqueadoMsg }) {
    return (
        <div className={`rounded-lg border p-4 ${confirmado ? 'border-green-200 bg-green-50/50' : 'border-gray-200'}`}>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold text-gray-800">{label}</p>
                    {cierre && <p className="text-xs text-gray-500 mt-0.5">Cierre: {cierre}</p>}
                </div>
                {confirmado ? (
                    <span className="text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Confirmado</span>
                ) : (
                    <span className="text-xs font-medium text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pendiente</span>
                )}
            </div>
            {!confirmado && disponible && (
                <Link href={href} className="inline-block mt-3 text-sm font-medium text-[#1B2D6B] hover:underline">
                    Declarar compromiso →
                </Link>
            )}
            {!confirmado && !disponible && bloqueadoMsg && (
                <p className="text-xs text-gray-400 mt-3">{bloqueadoMsg}</p>
            )}
        </div>
    );
}
