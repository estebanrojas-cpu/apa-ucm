import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const CALIFICACIONES = {
    'Muy Bueno':  'text-green-700',
    'Bueno':      'text-blue-700',
    'Aceptable':  'text-amber-700',
    'Deficiente': 'text-red-700',
};

export default function Directivos({ periodo, directivos, habilitada }) {
    return (
        <>
            <Head title="Directivos — Decano/a" />
            <AppLayout title="Informes a directivos">

                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                    </p>
                )}

                {!habilitada && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                        <p className="text-amber-800 font-semibold text-sm mb-1">
                            Etapa de informes de jefatura aún no disponible
                        </p>
                        <p className="text-amber-700 text-sm">
                            Podrá emitir informes sobre secretario/a y director/a de escuela cuando se habilite la etapa correspondiente.
                        </p>
                    </div>
                )}

                {habilitada && directivos.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">
                            No hay directivos asignados con expediente en evaluación.
                        </p>
                    </div>
                )}

                {habilitada && directivos.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                                    <th className="text-left px-5 py-3 font-medium">Directivo</th>
                                    <th className="text-left px-5 py-3 font-medium">Cargo</th>
                                    <th className="text-left px-5 py-3 font-medium">Calificación CCA</th>
                                    <th className="text-left px-5 py-3 font-medium">Informe</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {directivos.map(d => {
                                    const califColor = CALIFICACIONES[d.calificacion] ?? 'text-gray-500';
                                    return (
                                        <tr key={d.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-5 py-3.5">
                                                <p className="font-medium text-gray-900">{d.academico.name}</p>
                                                <p className="text-xs text-gray-400">{d.academico.rut}</p>
                                            </td>
                                            <td className="px-5 py-3.5 text-gray-600">{d.cargo ?? '—'}</td>
                                            <td className="px-5 py-3.5">
                                                {d.calificacion ? (
                                                    <span className={`font-semibold ${califColor}`}>
                                                        {d.calificacion} ({d.puntaje} pts)
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400 text-xs">Sin calificación</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {d.tiene_informe ? (
                                                    <span className="text-xs text-green-700 font-medium">✓ Emitido</span>
                                                ) : (
                                                    <span className="text-xs text-gray-400">Pendiente</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <Link
                                                    href={`/decano/directivos/${d.id}`}
                                                    className="text-xs font-medium text-[#0096D6] hover:underline"
                                                >
                                                    {d.tiene_informe ? 'Ver informe' : 'Emitir informe'}
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </AppLayout>
        </>
    );
}
