import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_CFG = {
    activo:         { label: 'Activo',         cls: 'bg-blue-100 text-blue-700' },
    cerrado:        { label: 'Cerrado',         cls: 'bg-gray-100 text-gray-600' },
    en_evaluacion:  { label: 'En evaluación',  cls: 'bg-purple-100 text-purple-700' },
    borrador:       { label: 'Borrador',        cls: 'bg-yellow-100 text-yellow-700' },
};

const CONCEPTO_ORDER = ['excelente', 'muy_bueno', 'bueno', 'regular', 'deficiente'];
const CONCEPTO_LABELS = {
    excelente:  'Excelente',
    muy_bueno:  'Muy Bueno',
    bueno:      'Bueno',
    regular:    'Regular',
    deficiente: 'Deficiente',
};
const CONCEPTO_COLORS = {
    excelente:  'bg-green-500',
    muy_bueno:  'bg-teal-400',
    bueno:      'bg-blue-400',
    regular:    'bg-amber-400',
    deficiente: 'bg-red-400',
};

function DistribucionBar({ distribucion, total }) {
    if (!total) return <p className="text-xs text-gray-400 italic">Sin calificaciones</p>;
    return (
        <div className="space-y-1 mt-2">
            {CONCEPTO_ORDER.filter(k => distribucion[k] > 0).map(key => {
                const n   = distribucion[key] ?? 0;
                const pct = Math.round((n / total) * 100);
                return (
                    <div key={key} className="flex items-center gap-2 text-xs">
                        <span className="w-20 text-right text-gray-500 shrink-0">{CONCEPTO_LABELS[key]}</span>
                        <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div className={`h-full rounded-full ${CONCEPTO_COLORS[key]}`} style={{ width: `${pct}%` }} />
                        </div>
                        <span className="w-6 text-gray-700 font-medium tabular-nums">{n}</span>
                    </div>
                );
            })}
        </div>
    );
}

export default function Historial({ periodos, anios, filtro_anio }) {
    function filtrar(anio) {
        router.get('/analista/historial', anio ? { anio } : {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Historial de períodos" />
            <AppLayout title="Historial de períodos">

                {/* Filtro por año */}
                <div className="flex items-center gap-2 mb-6 flex-wrap">
                    <button
                        onClick={() => filtrar(null)}
                        className={`px-3 py-1.5 text-xs rounded-lg font-medium transition-colors ${
                            !filtro_anio
                                ? 'bg-[#1B2D6B] text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                    >
                        Todos
                    </button>
                    {anios.map(a => (
                        <button
                            key={a}
                            onClick={() => filtrar(a)}
                            className={`px-3 py-1.5 text-xs rounded-lg font-medium transition-colors ${
                                filtro_anio === a
                                    ? 'bg-[#1B2D6B] text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {a}
                        </button>
                    ))}
                </div>

                {periodos.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No hay períodos registrados.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {periodos.map(p => {
                            const cfg = ESTADO_CFG[p.estado] ?? ESTADO_CFG.borrador;
                            return (
                                <div key={p.id} className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-semibold text-gray-900 text-sm">{p.nombre}</p>
                                            {p.cerrado_en && (
                                                <p className="text-xs text-gray-400 mt-0.5">Cerrado el {p.cerrado_en}</p>
                                            )}
                                        </div>
                                        <span className={`shrink-0 text-xs font-medium px-2 py-0.5 rounded-full ${cfg.cls}`}>
                                            {cfg.label}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-xs text-gray-500">
                                        <span>Inicio: <strong className="text-gray-700">{p.fecha_inicio ?? '—'}</strong></span>
                                        <span>Cierre: <strong className="text-gray-700">{p.fecha_cierre ?? '—'}</strong></span>
                                        <span>Evaluados: <strong className="text-gray-700">{p.total_evaluados}</strong></span>
                                    </div>

                                    <DistribucionBar distribucion={p.distribucion} total={p.total_evaluados} />

                                    <div className="flex items-center gap-3 pt-1 border-t border-gray-100">
                                        <Link
                                            href={`/analista/historial/${p.id}`}
                                            className="text-sm text-[#0096D6] hover:underline font-medium"
                                        >
                                            Ver detalle
                                        </Link>
                                        {p.total_evaluados > 0 && (
                                            <a
                                                href={`/analista/historial/${p.id}/imprimir`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-sm text-gray-500 hover:text-gray-700 hover:underline"
                                            >
                                                Imprimir acta
                                            </a>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </AppLayout>
        </>
    );
}
