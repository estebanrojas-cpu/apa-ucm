import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ETAPAS = [
    { value: 'carga_evidencias',      label: 'Carga de Evidencias' },
    { value: 'evaluacion_secretario', label: 'Evaluación Secretario' },
    { value: 'evaluacion_cca',        label: 'Evaluación CCA' },
    { value: 'apelaciones',           label: 'Apelaciones' },
    { value: 'evaluacion_jefatura',   label: 'Evaluación Jefatura' },
    { value: 'cierre',                label: 'Cierre' },
];

export default function PeriodoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre:       '',
        fecha_inicio: '',
        fecha_cierre: '',
        cronograma:   ETAPAS.map(e => ({ etapa: e.value, fecha_inicio: '', fecha_fin: '' })),
    });

    function setCronograma(index, field, value) {
        const updated = data.cronograma.map((item, i) =>
            i === index ? { ...item, [field]: value } : item
        );
        setData('cronograma', updated);
    }

    function submit(e) {
        e.preventDefault();
        post('/analista/periodos');
    }

    const cronError = (i, field) => errors[`cronograma.${i}.${field}`];

    return (
        <>
            <Head title="Registrar Período" />
            <AppLayout title="Registrar Período Académico">

                <form onSubmit={submit} className="space-y-8 max-w-3xl">

                    {/* Información básica */}
                    <section className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                            Información del período
                        </h2>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nombre del período
                            </label>
                            <input
                                type="text"
                                value={data.nombre}
                                onChange={e => setData('nombre', e.target.value)}
                                placeholder="Ej: Proceso APA 2026"
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                            />
                            {errors.nombre && <p className="mt-1 text-xs text-red-600">{errors.nombre}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha de inicio
                                </label>
                                <input
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={e => setData('fecha_inicio', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                />
                                {errors.fecha_inicio && <p className="mt-1 text-xs text-red-600">{errors.fecha_inicio}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha de cierre
                                </label>
                                <input
                                    type="date"
                                    value={data.fecha_cierre}
                                    onChange={e => setData('fecha_cierre', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                />
                                {errors.fecha_cierre && <p className="mt-1 text-xs text-red-600">{errors.fecha_cierre}</p>}
                            </div>
                        </div>
                    </section>

                    {/* Cronograma */}
                    <section className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">
                            Cronograma de etapas
                        </h2>
                        <p className="text-xs text-gray-400 mb-5">
                            Todas las fechas deben estar dentro del período principal.
                        </p>

                        {errors.cronograma && (
                            <p className="mb-4 text-xs text-red-600">{errors.cronograma}</p>
                        )}

                        <div className="space-y-4">
                            {ETAPAS.map((etapa, i) => {
                                const finAnterior = i > 0 ? data.cronograma[i - 1].fecha_fin : data.fecha_inicio;
                                const minInicio   = finAnterior || data.fecha_inicio || undefined;
                                const minFin      = data.cronograma[i].fecha_inicio || minInicio;

                                return (
                                    <div key={etapa.value}>
                                        <p className="text-sm font-medium text-gray-700 mb-2">{etapa.label}</p>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-xs text-gray-500 mb-1">Inicio</label>
                                                <input
                                                    type="date"
                                                    value={data.cronograma[i].fecha_inicio}
                                                    min={minInicio}
                                                    max={data.fecha_cierre || undefined}
                                                    onChange={e => setCronograma(i, 'fecha_inicio', e.target.value)}
                                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                                />
                                                {cronError(i, 'fecha_inicio') && (
                                                    <p className="mt-1 text-xs text-red-600">{cronError(i, 'fecha_inicio')}</p>
                                                )}
                                            </div>
                                            <div>
                                                <label className="block text-xs text-gray-500 mb-1">Fin</label>
                                                <input
                                                    type="date"
                                                    value={data.cronograma[i].fecha_fin}
                                                    min={minFin}
                                                    max={data.fecha_cierre || undefined}
                                                    onChange={e => setCronograma(i, 'fecha_fin', e.target.value)}
                                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                                />
                                                {cronError(i, 'fecha_fin') && (
                                                    <p className="mt-1 text-xs text-red-600">{cronError(i, 'fecha_fin')}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {/* Acciones */}
                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-[#1B2D6B] text-white text-sm font-medium px-6 py-2.5 rounded-lg hover:bg-[#152558] disabled:opacity-60 transition-colors"
                        >
                            {processing ? 'Registrando...' : 'Registrar período'}
                        </button>
                        <Link
                            href="/analista/periodos"
                            className="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                        >
                            Cancelar
                        </Link>
                    </div>

                </form>
            </AppLayout>
        </>
    );
}
