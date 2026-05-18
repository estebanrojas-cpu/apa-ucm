import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_BADGE = {
    borrador:      { label: 'Borrador',       cls: 'bg-gray-100 text-gray-600' },
    activo:        { label: 'Activo',          cls: 'bg-green-100 text-green-700' },
    en_evaluacion: { label: 'En Evaluación',   cls: 'bg-blue-100 text-blue-700' },
    cerrado:       { label: 'Cerrado',          cls: 'bg-red-100 text-red-600' },
};

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const part = dateStr.split('T')[0];
    const [y, m, d] = part.split('-');
    return `${d}/${m}/${y}`;
}

export default function PeriodoIndex({ periodos }) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Períodos Académicos" />
            <AppLayout title="Períodos Académicos">

                {flash?.success && (
                    <div className="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                <div className="flex justify-end mb-5">
                    <Link
                        href="/analista/periodos/crear"
                        className="inline-flex items-center gap-2 bg-[#1B2D6B] text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-[#152558] transition-colors"
                    >
                        + Registrar período
                    </Link>
                </div>

                {periodos.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p className="text-gray-400 text-sm">No hay períodos registrados.</p>
                        <Link
                            href="/analista/periodos/crear"
                            className="mt-3 inline-block text-[#0096D6] text-sm hover:underline"
                        >
                            Registrar el primer período
                        </Link>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Año</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Nombre</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Estado</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Inicio</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Cierre</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Académicos</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {periodos.map(p => {
                                    const badge = ESTADO_BADGE[p.estado] ?? { label: p.estado, cls: 'bg-gray-100 text-gray-600' };
                                    return (
                                        <tr key={p.id} className="hover:bg-gray-50">
                                            <td className="px-5 py-3 font-semibold text-gray-900">{p.anio}</td>
                                            <td className="px-5 py-3 text-gray-700">{p.nombre}</td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-block text-xs font-medium px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-gray-600">{formatDate(p.fecha_inicio)}</td>
                                            <td className="px-5 py-3 text-gray-600">{formatDate(p.fecha_cierre)}</td>
                                            <td className="px-5 py-3 text-gray-600">{p.nominas_count ?? 0}</td>
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
