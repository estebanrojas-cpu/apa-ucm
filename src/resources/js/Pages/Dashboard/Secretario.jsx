import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Secretario({ stats, periodo }) {
    const { auth } = usePage().props;
    const facultad = auth.user.facultad?.nombre ?? '—';

    return (
        <>
            <Head title="Panel Secretario" />
            <AppLayout title="Panel Secretario">

                <p className="text-sm text-gray-500 -mt-4 mb-6">Facultad: <span className="font-medium text-gray-700">{facultad}</span></p>

                {periodo ? (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                            <StatCard label="Académicos en nómina"  value={stats.total} />
                            <StatCard label="Pendientes"             value={stats.pendientes} />
                            <StatCard label="Casos especiales"       value={stats.con_licencia} accent={stats.con_licencia > 0} />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <ActionCard
                                title="Expedientes"
                                description={`Ver y hacer seguimiento de los ${stats.total} expedientes del período ${periodo.nombre}.`}
                                href="/secretario/expedientes"
                                linkLabel="Ver expedientes"
                                primary
                            />
                        </div>
                    </>
                ) : (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p className="text-gray-400 text-sm">No hay un período activo actualmente.</p>
                    </div>
                )}

            </AppLayout>
        </>
    );
}

function StatCard({ label, value, accent = false }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <p className="text-sm text-gray-500">{label}</p>
            <p className={`text-2xl font-bold mt-1 ${accent ? 'text-amber-600' : 'text-gray-900'}`}>
                {value ?? '—'}
            </p>
        </div>
    );
}

function ActionCard({ title, description, href, linkLabel, primary = false }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3">
            <div>
                <p className="font-semibold text-gray-900 text-sm">{title}</p>
                <p className="text-sm text-gray-500 mt-1">{description}</p>
            </div>
            <Link
                href={href}
                className={`self-start text-sm font-medium px-4 py-2 rounded-lg transition-colors ${
                    primary
                        ? 'bg-[#1B2D6B] text-white hover:bg-[#152558]'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
            >
                {linkLabel}
            </Link>
        </div>
    );
}
