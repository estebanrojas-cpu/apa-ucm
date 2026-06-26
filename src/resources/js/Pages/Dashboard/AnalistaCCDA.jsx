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
                    <ActionCard
                        title="Estado del proceso"
                        description="Visualiza el avance por facultad: expedientes evaluados, pendientes y cierres."
                        href="/analista/estado-proceso"
                        linkLabel="Ver estado"
                    />
                    <ActionCard
                        title="Reportes PDF"
                        description="Genera el reporte consolidado de calificaciones o el listado de incumplimientos."
                        href="/analista/reporte-calificaciones"
                        linkLabel="Reporte calificaciones"
                        external
                    />
                    <ActionCard
                        title="Registro CCDA"
                        description="Verificación por facultad del proceso completo antes de enviar a Vicerrectoría."
                        href="/analista/registro-ccda"
                        linkLabel="Ver registro"
                    />
                </div>

                <p className="text-xs text-gray-400 mt-6">
                    Desde <Link href="/analista/periodos" className="text-[#0096D6] hover:underline">Períodos</Link> gestione
                    nómina y comisión evaluadora. Los semestres APA se definen al registrar el período.
                </p>

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

function ActionCard({ title, description, href, linkLabel, primary = false, external = false }) {
    const cls = `self-start text-sm font-medium px-4 py-2 rounded-lg transition-colors ${
        primary
            ? 'bg-[#1B2D6B] text-white hover:bg-[#152558]'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
    }`;

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3">
            <div>
                <p className="font-semibold text-gray-900 text-sm">{title}</p>
                <p className="text-sm text-gray-500 mt-1">{description}</p>
            </div>
            {external ? (
                <a href={href} target="_blank" rel="noreferrer" className={cls}>{linkLabel}</a>
            ) : (
                <Link href={href} className={cls}>{linkLabel}</Link>
            )}
        </div>
    );
}
