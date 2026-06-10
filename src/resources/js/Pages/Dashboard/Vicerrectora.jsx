import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Vicerrectora({ stats, periodo }) {
    return (
        <>
            <Head title="Dashboard Vicerrectoría" />
            <AppLayout title="Revisión Vicerrectoría">
                {periodo && (
                    <p className="text-sm text-gray-500 mb-6">
                        Período activo: <strong>{periodo.nombre}</strong> ({periodo.anio})
                    </p>
                )}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <p className="text-xs text-gray-400 uppercase">Académicos evaluados</p>
                        <p className="text-3xl font-bold text-[#1B2D6B] mt-1">{stats.evaluados}</p>
                    </div>
                </div>
                <Link href="/vicerrectora/academicos"
                    className="inline-flex bg-[#1B2D6B] text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-[#152558]">
                    Ver calificaciones por facultad
                </Link>
            </AppLayout>
        </>
    );
}
