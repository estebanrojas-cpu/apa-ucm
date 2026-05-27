import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function MiembroCCA({ stats, periodo }) {
    return (
        <>
            <Head title="Panel Miembro CCA" />
            <AppLayout title="Panel Miembro CCA">
                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período activo: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                    </p>
                )}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                    <StatCard label="Por evaluar" value={stats.pendientes} color="blue" />
                    <StatCard label="En evaluación" value={stats.en_evaluacion} color="purple" />
                    <StatCard label="Evaluados" value={stats.evaluados} color="green" />
                </div>
                {(stats.pendientes + stats.en_evaluacion) > 0 && (
                    <div className="bg-blue-50 border border-blue-200 rounded-xl p-5 flex items-center justify-between">
                        <p className="text-sm text-blue-800 font-medium">
                            Hay expedientes disponibles para evaluar.
                        </p>
                        <Link
                            href="/cca/expedientes"
                            className="text-sm font-medium text-white bg-[#1B2D6B] px-4 py-2 rounded-lg hover:bg-[#152558] transition-colors"
                        >
                            Ir a expedientes
                        </Link>
                    </div>
                )}
            </AppLayout>
        </>
    );
}

function StatCard({ label, value, color }) {
    const colors = {
        blue:   'text-blue-600',
        purple: 'text-purple-600',
        green:  'text-green-600',
    };
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <p className="text-sm text-gray-500">{label}</p>
            <p className={`text-3xl font-bold mt-1 ${colors[color] ?? 'text-gray-900'}`}>{value}</p>
        </div>
    );
}
