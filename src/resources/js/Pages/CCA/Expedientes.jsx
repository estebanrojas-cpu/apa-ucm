import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADOS = {
    carga_cerrada: { label: 'Por evaluar',    cls: 'bg-blue-100 text-blue-700' },
    en_evaluacion: { label: 'En evaluación',  cls: 'bg-purple-100 text-purple-700' },
    evaluado:      { label: 'Evaluado',       cls: 'bg-green-100 text-green-700' },
};

const CALIFICACIONES = {
    muy_bueno:  { label: 'Muy Bueno',  cls: 'text-green-700' },
    bueno:      { label: 'Bueno',      cls: 'text-blue-700' },
    aceptable:  { label: 'Aceptable',  cls: 'text-amber-700' },
    deficiente: { label: 'Deficiente', cls: 'text-red-700' },
};

export default function Expedientes({ periodo, expedientes }) {
    return (
        <>
            <Head title="Expedientes CCA" />
            <AppLayout title="Expedientes para Evaluación">
                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                    </p>
                )}

                {expedientes.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No hay expedientes disponibles para evaluación en este período.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                                    <th className="text-left px-5 py-3 font-medium">Académico</th>
                                    <th className="text-left px-5 py-3 font-medium">Estado</th>
                                    <th className="text-center px-5 py-3 font-medium">Evaluaciones</th>
                                    <th className="text-left px-5 py-3 font-medium">Calificación</th>
                                    <th className="text-left px-5 py-3 font-medium">Mi evaluación</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {expedientes.map(exp => {
                                    const badge   = ESTADOS[exp.estado] ?? { label: exp.estado, cls: 'bg-gray-100 text-gray-600' };
                                    const calif   = CALIFICACIONES[exp.calificacion];
                                    return (
                                        <tr key={exp.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-5 py-3.5">
                                                <p className="font-medium text-gray-900">{exp.academico.name}</p>
                                                <p className="text-xs text-gray-400">{exp.academico.rut}</p>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                                                    {badge.label}
                                                </span>
                                                {exp.con_licencia && (
                                                    <span className="ml-1.5 text-xs text-amber-600 font-medium">· Licencia</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <span className="text-gray-700 font-semibold">{exp.n_evaluaciones}</span>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {calif ? (
                                                    <span className={`font-semibold ${calif.cls}`}>
                                                        {calif.label} ({exp.puntaje_total} pts)
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400 text-xs">Pendiente</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {exp['yo_evalué'] ? (
                                                    <span className="text-xs text-green-700 font-medium">✓ Registrada</span>
                                                ) : (
                                                    <span className="text-xs text-gray-400">Sin registrar</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <Link
                                                    href={`/cca/expedientes/${exp.id}`}
                                                    className="text-xs font-medium text-[#0096D6] hover:underline"
                                                >
                                                    {exp.estado === 'evaluado' ? 'Ver detalle' : 'Evaluar'}
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
