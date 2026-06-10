import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ETAPAS = [
    { value: 'carga_evidencias',       label: 'Carga de Evidencias' },
    { value: 'validacion_secretario',  label: 'Validación Secretario' },
    { value: 'evaluacion_cca',         label: 'Evaluación CCA' },
    { value: 'consejo_facultad',       label: 'Consejo de Facultad' },
    { value: 'apelaciones',            label: 'Apelaciones' },
    { value: 'revision_vicerrectoria', label: 'Revisión Vicerrectoría' },
    { value: 'cierre',                 label: 'Cierre' },
];

const ETAPAS_PARALELAS = new Set([
    'carga_evidencias',
    'validacion_secretario',
]);

const INICIO_SECUENCIAL = {
    evaluacion_cca:         'carga_evidencias',
    consejo_facultad:       'evaluacion_cca',
    apelaciones:            'consejo_facultad',
    revision_vicerrectoria: 'apelaciones',
    cierre:                 'revision_vicerrectoria',
};

export default function PeriodoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre:       '',
        fecha_inicio: '',
        fecha_cierre: '',
        cronograma:   ETAPAS.map(e => ({ etapa: e.value, fecha_fin: '' })),
    });

    function finEtapa(etapa) {
        return data.cronograma.find(c => c.etapa === etapa)?.fecha_fin || '';
    }

    function minFinEtapa(etapa) {
        if (ETAPAS_PARALELAS.has(etapa)) {
            return data.fecha_inicio || undefined;
        }

        const etapaPrevia = INICIO_SECUENCIAL[etapa];
        return finEtapa(etapaPrevia) || data.fecha_inicio || undefined;
    }

    function setCronograma(index, value) {
        const updated = data.cronograma.map((item, i) =>
            i === index ? { ...item, fecha_fin: value } : item
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

                <form onSubmit={submit} className="w-full space-y-8">

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

                        {errors.periodo && (
                            <p className="text-xs text-red-600">{errors.periodo}</p>
                        )}
                    </section>

                    <section className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">
                            Cronograma de etapas
                        </h2>
                        <p className="text-xs text-gray-400 mb-5">
                            La carga de evidencias y la validación del secretario inician al comienzo del período.
                            Las demás etapas se habilitan secuencialmente al cerrar la etapa anterior.
                            Defina la fecha de cierre de cada etapa.
                        </p>

                        {errors.cronograma && (
                            <p className="mb-4 text-xs text-red-600">{errors.cronograma}</p>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {ETAPAS.map((etapa, i) => (
                                <div key={etapa.value} className="rounded-lg border border-gray-100 bg-gray-50/80 p-4">
                                    <p className="text-sm font-medium text-gray-700 mb-2">{etapa.label}</p>
                                    <div>
                                        <label className="block text-xs text-gray-500 mb-1">Fin</label>
                                        <input
                                            type="date"
                                            value={data.cronograma[i].fecha_fin}
                                            min={minFinEtapa(etapa.value)}
                                            max={data.fecha_cierre || undefined}
                                            onChange={e => setCronograma(i, e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                        />
                                        {cronError(i, 'fecha_fin') && (
                                            <p className="mt-1 text-xs text-red-600">{cronError(i, 'fecha_fin')}</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

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
