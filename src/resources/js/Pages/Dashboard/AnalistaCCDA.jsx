import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function AnalistaCCDA({ stats }) {
    return (
        <>
            <Head title="Panel Analista CCDA" />
            <AppLayout title="Panel Analista CCDA">

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                    <StatCard label="Períodos activos"      value={stats.periodos_activos} />
                    <StatCard label="Nóminas cargadas"      value={stats.nominas_cargadas} />
                    <StatCard label="Cronogramas vigentes"  value={stats.cronogramas_vigentes} />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <ActionCard
                        title="Períodos académicos"
                        description="Registra y gestiona los períodos del proceso APA y sus cronogramas."
                        href="/analista/periodos"
                        linkLabel="Ver períodos"
                    />
                    <ActionCard
                        title="Registrar nuevo período"
                        description="Inicia un nuevo proceso indicando las fechas y el cronograma por etapas."
                        href="/analista/periodos/crear"
                        linkLabel="Registrar período"
                        primary
                    />
                </div>

            </AppLayout>
        </>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{value ?? '—'}</p>
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
