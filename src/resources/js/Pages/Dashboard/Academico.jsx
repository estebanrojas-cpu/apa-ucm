import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const estadoLabels = {
    pendiente:     { label: 'Pendiente',    color: 'text-yellow-700 bg-yellow-100' },
    en_carga:      { label: 'En carga',      color: 'text-blue-700 bg-blue-100' },
    en_evaluacion: { label: 'En evaluación', color: 'text-purple-700 bg-purple-100' },
    evaluado:      { label: 'Evaluado',      color: 'text-green-700 bg-green-100' },
    cerrado:       { label: 'Cerrado',       color: 'text-gray-700 bg-gray-100' },
};

const califColors = {
    muy_bueno:  'text-green-700',
    bueno:      'text-blue-700',
    aceptable:  'text-amber-700',
    deficiente: 'text-red-700',
};

export default function Academico({ stats, periodo }) {
    const estado     = estadoLabels[stats?.estado_nomina];
    const calificacion = stats?.calificacion ?? null;

    return (
        <>
            <Head title="Panel Académico" />
            <AppLayout title="Mi Panel">

                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período activo: <span className="font-medium text-gray-700">{periodo.nombre}</span>
                    </p>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                    <StatCard
                        label="Evidencias cargadas"
                        value={stats?.evidencias_cargadas ?? '—'}
                    />
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
                                ? <span className={`text-xl font-bold ${califColors[calificacion.calificacion] ?? 'text-gray-900'}`}>
                                    {calificacion.label}
                                  </span>
                                : '—'
                        }
                    />
                </div>

                {/* Banner de calificación cuando está disponible */}
                {calificacion && (
                    <div className="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
                        <p className="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">
                            Calificación APA — Resultado final
                        </p>
                        <p className={`text-3xl font-bold ${califColors[calificacion.calificacion]}`}>
                            {calificacion.label}
                        </p>
                        <p className="text-sm text-green-700 mt-1">
                            Puntaje: <span className="font-semibold">{calificacion.puntaje_total} / 100</span>
                            <span className="mx-2 text-green-400">·</span>
                            Registrada el {calificacion.fecha}
                        </p>
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200 p-6 flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-gray-800">Carga de evidencias</h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Suba sus documentos por categoría APA para el período activo.
                        </p>
                    </div>
                    <Link
                        href="/academico/evidencias"
                        className="px-4 py-2 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] transition-colors shrink-0 ml-4"
                    >
                        Ir a evidencias
                    </Link>
                </div>

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
