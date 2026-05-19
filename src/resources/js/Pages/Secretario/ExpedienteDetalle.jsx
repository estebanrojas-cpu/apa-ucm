import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADOS = {
    pendiente:     { label: 'Pendiente',    cls: 'bg-gray-100 text-gray-600' },
    en_carga:      { label: 'En revisión',  cls: 'bg-blue-100 text-blue-700' },
    carga_cerrada: { label: 'Completo',     cls: 'bg-green-100 text-green-700' },
    en_evaluacion: { label: 'En evaluación',cls: 'bg-purple-100 text-purple-700' },
    evaluado:      { label: 'Evaluado',     cls: 'bg-indigo-100 text-indigo-700' },
    apelado:       { label: 'Apelado',      cls: 'bg-orange-100 text-orange-700' },
    cerrado:       { label: 'Cerrado',      cls: 'bg-red-100 text-red-600' },
};

const PUEDE_VALIDAR = ['pendiente', 'en_carga'];

export default function ExpedienteDetalle({ nomina, categorias, evidenciasPorCategoria, totalEvidencias }) {
    const { flash } = usePage().props;
    const badge = ESTADOS[nomina.estado] ?? { label: nomina.estado, cls: 'bg-gray-100 text-gray-600' };
    const puedeValidar = PUEDE_VALIDAR.includes(nomina.estado);

    const { data, setData, patch, processing, errors } = useForm({
        accion:      'completo',
        observacion: nomina.observacion_secretario ?? '',
    });

    function submit(e) {
        e.preventDefault();
        patch(`/secretario/expedientes/${nomina.id}/validar`, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Expediente — ${nomina.academico.name}`} />
            <AppLayout title="Detalle de Expediente">

                {/* Volver */}
                <div className="-mt-4 mb-6">
                    <Link href="/secretario/expedientes"
                        className="text-sm text-[#0096D6] hover:underline flex items-center gap-1">
                        <BackIcon /> Volver a expedientes
                    </Link>
                </div>

                {flash?.success && (
                    <div className="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* Cabecera del expediente */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6 flex flex-wrap items-start gap-x-8 gap-y-3">
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Académico</p>
                        <p className="font-semibold text-gray-900 text-sm mt-0.5">{nomina.academico.name}</p>
                        <p className="text-xs text-gray-500">{nomina.academico.rut} · {nomina.academico.email}</p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Estado</p>
                        <span className={`inline-block mt-1 text-xs font-semibold px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                            {badge.label}
                        </span>
                    </div>
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Evidencias cargadas</p>
                        <p className="font-semibold text-gray-900 text-sm mt-0.5">{totalEvidencias}</p>
                    </div>
                    {nomina.con_licencia && (
                        <div>
                            <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                Caso especial · Licencia médica
                            </span>
                            {nomina.observacion_licencia && (
                                <p className="text-xs text-gray-500 mt-1">{nomina.observacion_licencia}</p>
                            )}
                        </div>
                    )}
                </div>

                {/* Evidencias por categoría */}
                <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                    Documentación entregada por categoría
                </h2>
                <div className="space-y-3 mb-8">
                    {categorias.map(cat => {
                        const archivos = evidenciasPorCategoria[cat.id] ?? [];
                        return (
                            <div key={cat.id} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <div className="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-700">{cat.nombre}</span>
                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                        archivos.length > 0
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-gray-100 text-gray-500'
                                    }`}>
                                        {archivos.length > 0 ? `${archivos.length} archivo${archivos.length > 1 ? 's' : ''}` : 'Sin archivos'}
                                    </span>
                                </div>
                                <div className="px-5 py-3">
                                    {archivos.length === 0 ? (
                                        <p className="text-xs text-gray-400 italic">El académico no ha cargado archivos en esta categoría.</p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {archivos.map(ev => (
                                                <li key={ev.id} className="flex items-center gap-2.5 py-1.5 text-sm">
                                                    <FileIcon />
                                                    <div className="min-w-0">
                                                        <p className="text-gray-800 font-medium truncate">{ev.nombre_archivo}</p>
                                                        <p className="text-xs text-gray-400">
                                                            {ev.tamano} · {ev.created_at}
                                                            {ev.descripcion && ` · ${ev.descripcion}`}
                                                        </p>
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Formulario de validación */}
                {puedeValidar ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-sm font-semibold text-gray-800 mb-4">Validar expediente</h2>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="flex flex-col sm:flex-row gap-3">
                                <label className={`flex-1 flex items-start gap-3 border rounded-xl p-4 cursor-pointer transition-colors ${
                                    data.accion === 'completo'
                                        ? 'border-green-500 bg-green-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                }`}>
                                    <input
                                        type="radio"
                                        name="accion"
                                        value="completo"
                                        checked={data.accion === 'completo'}
                                        onChange={() => setData('accion', 'completo')}
                                        className="mt-0.5 accent-green-600"
                                    />
                                    <div>
                                        <p className="text-sm font-semibold text-gray-800">Marcar como completo</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            El expediente queda certificado y disponible para la CCA.
                                        </p>
                                    </div>
                                </label>

                                <label className={`flex-1 flex items-start gap-3 border rounded-xl p-4 cursor-pointer transition-colors ${
                                    data.accion === 'observaciones'
                                        ? 'border-amber-400 bg-amber-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                }`}>
                                    <input
                                        type="radio"
                                        name="accion"
                                        value="observaciones"
                                        checked={data.accion === 'observaciones'}
                                        onChange={() => setData('accion', 'observaciones')}
                                        className="mt-0.5 accent-amber-500"
                                    />
                                    <div>
                                        <p className="text-sm font-semibold text-gray-800">Registrar observaciones</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            Se notifica al académico que debe corregir o completar su documentación.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            {data.accion === 'observaciones' && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">
                                        Observaciones para el académico
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={data.observacion}
                                        onChange={e => setData('observacion', e.target.value)}
                                        placeholder="Indique qué debe corregir o completar el académico..."
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B] resize-none"
                                    />
                                    {errors.observacion && (
                                        <p className="text-xs text-red-600 mt-1">{errors.observacion}</p>
                                    )}
                                </div>
                            )}

                            {errors.accion && (
                                <p className="text-xs text-red-600">{errors.accion}</p>
                            )}

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2.5 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] disabled:opacity-40 transition-colors"
                                >
                                    {processing ? 'Guardando...' : 'Confirmar validación'}
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    nomina.observacion_secretario && (
                        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5">
                            <p className="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                                Observaciones registradas
                            </p>
                            <p className="text-sm text-amber-800">{nomina.observacion_secretario}</p>
                        </div>
                    )
                )}

            </AppLayout>
        </>
    );
}

function FileIcon() {
    return (
        <svg className="w-4 h-4 text-[#0096D6] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    );
}

function BackIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
    );
}
