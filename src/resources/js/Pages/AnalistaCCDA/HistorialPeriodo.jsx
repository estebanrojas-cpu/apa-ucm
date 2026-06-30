import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const CONCEPTO_ORDER = ['excelente', 'muy_bueno', 'bueno', 'regular', 'deficiente'];
const CONCEPTO_LABELS = {
    excelente:  'Excelente',
    muy_bueno:  'Muy Bueno',
    bueno:      'Bueno',
    regular:    'Regular',
    deficiente: 'Deficiente',
};
const CONCEPTO_CLS = {
    excelente:  'bg-green-100 text-green-700',
    muy_bueno:  'bg-teal-100 text-teal-700',
    bueno:      'bg-blue-100 text-blue-700',
    regular:    'bg-amber-100 text-amber-700',
    deficiente: 'bg-red-100 text-red-700',
};
const CONCEPTO_BAR = {
    excelente:  'bg-green-500',
    muy_bueno:  'bg-teal-400',
    bueno:      'bg-blue-400',
    regular:    'bg-amber-400',
    deficiente: 'bg-red-400',
};

function ConceptoBadge({ concepto, nota }) {
    if (!nota) return <span className="text-gray-400 text-xs">Sin calif.</span>;
    const key  = concepto?.toLowerCase().replace(' ', '_') ?? '';
    const cls  = CONCEPTO_CLS[key] ?? 'bg-gray-100 text-gray-600';
    return (
        <span className={`inline-block text-xs font-semibold px-2 py-0.5 rounded-full ${cls}`}>
            {nota} — {concepto}
        </span>
    );
}

function FacultadTable({ facultad }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden mb-4">
            <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-4">
                <p className="font-semibold text-sm text-gray-900">{facultad.nombre}</p>
                <div className="flex gap-3 text-xs text-gray-500">
                    <span>{facultad.stats.total} académicos</span>
                    {facultad.stats.promedio && (
                        <span>Promedio: <strong className="text-gray-700">{facultad.stats.promedio}</strong></span>
                    )}
                    {facultad.stats.sin_nota > 0 && (
                        <span className="text-amber-600">{facultad.stats.sin_nota} sin nota</span>
                    )}
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="bg-gray-50 text-gray-500 uppercase tracking-wide border-b border-gray-100">
                            <th className="text-left px-4 py-2 font-medium">Académico</th>
                            <th className="text-left px-4 py-2 font-medium">Cargo / Categoría</th>
                            <th className="text-left px-4 py-2 font-medium">Tipo / Hrs</th>
                            <th className="text-center px-3 py-2 font-medium">Calificación</th>
                            <th className="text-left px-4 py-2 font-medium">Obs. Vicerrectoría</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {facultad.academicos.map(ac => (
                            <tr key={ac.id} className="hover:bg-gray-50/50">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-gray-800">{ac.nombre}</p>
                                    <p className="text-gray-400">{ac.rut}</p>
                                    {ac.tiene_apelacion && (
                                        <span className="text-[10px] text-orange-600 font-medium">Apeló</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <p className="text-gray-700">{ac.cargo}</p>
                                    <p className="text-gray-400 capitalize">{ac.categoria} · {ac.adscripcion}</p>
                                </td>
                                <td className="px-4 py-3 text-gray-600">
                                    <p>{ac.tipo_trabajador}</p>
                                    {ac.horas_contrato && <p className="text-gray-400">{ac.horas_contrato} hrs</p>}
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <ConceptoBadge concepto={ac.concepto} nota={ac.nota} />
                                    {ac.sin_calificacion && (
                                        <p className="text-[10px] text-gray-400 mt-0.5">Sin calificación</p>
                                    )}
                                    {ac.fecha_calificacion && (
                                        <p className="text-[10px] text-gray-400 mt-0.5">{ac.fecha_calificacion}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 max-w-xs">
                                    {ac.comentario_vice
                                        ? <p className="text-gray-600 text-[11px] leading-relaxed line-clamp-3">{ac.comentario_vice}</p>
                                        : <span className="text-gray-300">—</span>
                                    }
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function HistorialPeriodo({ periodo, por_facultad, da_conocer, distribucion, total }) {
    const totalDist = Object.values(distribucion).reduce((a, b) => a + Number(b), 0);

    return (
        <>
            <Head title={`Historial — ${periodo.nombre}`} />
            <AppLayout title="Historial del período">

                <div className="-mt-4 mb-6 flex items-center justify-between flex-wrap gap-3">
                    <Link href="/analista/historial" className="text-sm text-[#0096D6] hover:underline">
                        ← Volver al historial
                    </Link>
                    <a
                        href={`/analista/historial/${periodo.id}/imprimir`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1B2D6B] text-white text-xs font-medium rounded-lg hover:bg-[#152558] transition-colors"
                    >
                        Imprimir acta
                    </a>
                </div>

                {/* Cabecera del período */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                    <div className="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">{periodo.nombre}</h2>
                            <div className="flex gap-4 mt-1 text-xs text-gray-500 flex-wrap">
                                <span>Inicio: {periodo.fecha_inicio ?? '—'}</span>
                                <span>Cierre: {periodo.fecha_cierre ?? '—'}</span>
                                {periodo.cerrado_en && (
                                    <span className="text-green-700 font-medium">Cerrado: {periodo.cerrado_en}</span>
                                )}
                            </div>
                        </div>
                        <div className="text-right">
                            <p className="text-2xl font-bold text-[#1B2D6B]">{total}</p>
                            <p className="text-xs text-gray-400">académicos evaluados</p>
                        </div>
                    </div>

                    {/* Distribución de notas */}
                    {totalDist > 0 && (
                        <div className="mt-4 pt-4 border-t border-gray-100">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                Distribución de calificaciones
                            </p>
                            <div className="space-y-1.5">
                                {CONCEPTO_ORDER.filter(k => distribucion[k] > 0).map(key => {
                                    const n   = Number(distribucion[key]) ?? 0;
                                    const pct = Math.round((n / totalDist) * 100);
                                    return (
                                        <div key={key} className="flex items-center gap-3 text-xs">
                                            <span className="w-20 text-right text-gray-500 shrink-0">
                                                {CONCEPTO_LABELS[key]}
                                            </span>
                                            <div className="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div
                                                    className={`h-full rounded-full ${CONCEPTO_BAR[key]}`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                            <span className="w-8 text-gray-700 font-semibold tabular-nums text-right">{n}</span>
                                            <span className="w-8 text-gray-400 tabular-nums">{pct}%</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Tablas por facultad */}
                {por_facultad.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p className="text-gray-400 text-sm">No hay académicos evaluados en este período.</p>
                    </div>
                ) : (
                    por_facultad.map(f => <FacultadTable key={f.nombre} facultad={f} />)
                )}

                {/* Se da a conocer */}
                {da_conocer.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden mt-4">
                        <div className="px-5 py-3 border-b border-gray-100">
                            <p className="font-semibold text-sm text-gray-600">Se da a conocer — no participan de la evaluación CCA</p>
                        </div>
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="bg-gray-50 text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                    <th className="text-left px-4 py-2 font-medium">Académico</th>
                                    <th className="text-left px-4 py-2 font-medium">Cargo</th>
                                    <th className="text-left px-4 py-2 font-medium">Facultad</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {da_conocer.map(ac => (
                                    <tr key={ac.id} className="hover:bg-gray-50/50">
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium text-gray-800">{ac.nombre}</p>
                                            <p className="text-gray-400">{ac.rut}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-gray-600">{ac.cargo}</td>
                                        <td className="px-4 py-2.5 text-gray-500">{ac.facultad}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
