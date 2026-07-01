import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const CALIFICACIONES = {
    'Muy Bueno':  'text-green-700',
    'Bueno':      'text-blue-700',
    'Aceptable':  'text-amber-700',
    'Deficiente': 'text-red-700',
};

export default function Academicos({ periodo, academicos, etapaHabilitada, fechaInicioJefatura }) {
    return (
        <>
            <Head title="Académicos — Jefatura" />
            <AppLayout title="Calificación de Académicos">
                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                    </p>
                )}

                {!etapaHabilitada && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                        <p className="text-amber-800 font-semibold text-sm mb-1">
                            Etapa de calificación de jefatura aún no disponible
                        </p>
                        <p className="text-amber-700 text-sm">
                            Se habilitará el{' '}
                            <span className="font-semibold">{fechaInicioJefatura}</span>.
                        </p>
                    </div>
                )}

                {etapaHabilitada && academicos.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">
                            No hay académicos con expediente evaluado en este período.
                        </p>
                    </div>
                )}

                {etapaHabilitada && academicos.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                                    <th className="text-left px-5 py-3 font-medium">Académico</th>
                                    <th className="text-left px-5 py-3 font-medium">Departamento</th>
                                    <th className="text-left px-5 py-3 font-medium">Informe jefatura</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {academicos.map(ac => {
                                    return (
                                        <tr key={ac.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-5 py-3.5">
                                                <p className="font-medium text-gray-900">{ac.academico.name}</p>
                                                <p className="text-xs text-gray-400">{ac.academico.rut}</p>
                                            </td>
                                            <td className="px-5 py-3.5 text-gray-600">
                                                {ac.academico.departamento ?? '—'}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {ac.tiene_informe ? (
                                                    <span className="text-xs text-green-700 font-medium">✓ Informe emitido</span>
                                                ) : (
                                                    <span className="text-xs text-gray-400">Pendiente</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <Link
                                                    href={`/jefe/academicos/${ac.id}`}
                                                    className="text-xs font-medium text-[#0096D6] hover:underline"
                                                >
                                                    {ac.tiene_informe ? 'Ver informe' : 'Emitir informe'}
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
