import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
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

const CALIFICACIONES = {
    muy_bueno:  'Muy Bueno',
    bueno:      'Bueno',
    aceptable:  'Aceptable',
    deficiente: 'Deficiente',
};

export default function ExpedienteDetalle({ nomina, categorias, evidenciasPorCategoria, evidenciasApelacionPorCategoria, totalEvidencias, apelacion, destinoApelacionCierre, calificacionFinal }) {
    const { flash } = usePage().props;
    const badge = ESTADOS[nomina.estado] ?? { label: nomina.estado, cls: 'bg-gray-100 text-gray-600' };
    const puedeValidar  = PUEDE_VALIDAR.includes(nomina.estado);
    const puedeReabrir  = nomina.estado === 'carga_cerrada';

    const reabrirForm = useForm({});

    function submitReabrir(e) {
        e.preventDefault();
        if (!confirm('¿Seguro que desea reabrir este expediente? El académico podrá cargar nuevamente.')) return;
        reabrirForm.patch(`/secretario/expedientes/${nomina.id}/reabrir`, { preserveScroll: true });
    }

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

                {/* Evidencias por categoría — cards navegables */}
                <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                    Documentación entregada por categoría
                </h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
                    {categorias.map(cat => {
                        const normales  = (evidenciasPorCategoria[cat.id] ?? []).length;
                        const apelacion = (evidenciasApelacionPorCategoria[cat.id] ?? []).length;
                        return (
                            <Link
                                key={cat.id}
                                href={`/secretario/expedientes/${nomina.id}/categoria/${cat.id}`}
                                className="bg-white rounded-xl border border-gray-200 p-4 hover:border-[#1B2D6B] hover:shadow-sm transition-all group"
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <h3 className="font-semibold text-gray-800 text-sm group-hover:text-[#1B2D6B] transition-colors">
                                        {cat.nombre}
                                    </h3>
                                    <span className="text-[#1B2D6B] text-sm font-bold shrink-0">→</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                        normales > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                    }`}>
                                        {normales} {normales === 1 ? 'archivo' : 'archivos'}
                                    </span>
                                    {apelacion > 0 && (
                                        <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">
                                            +{apelacion} apelación
                                        </span>
                                    )}
                                </div>
                            </Link>
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

                {/* Reabrir expediente */}
                {puedeReabrir && (
                    <form onSubmit={submitReabrir} className="mt-4">
                        <button
                            type="submit"
                            disabled={reabrirForm.processing}
                            className="text-sm text-amber-700 border border-amber-300 bg-amber-50 hover:bg-amber-100 px-4 py-2 rounded-lg font-medium transition-colors disabled:opacity-40"
                        >
                            {reabrirForm.processing ? 'Reabriendo...' : 'Reabrir expediente para carga'}
                        </button>
                    </form>
                )}

                {/* Panel de licencia médica */}
                {nomina.con_licencia && (
                    <LicenciaPanel nomina={nomina} />
                )}

                {/* Calificación final (solo lectura) */}
                {calificacionFinal && (
                    <div className="mt-6 bg-indigo-50 border border-indigo-200 rounded-xl p-5">
                        <p className="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-1">Calificación final registrada</p>
                        <p className="text-lg font-bold text-indigo-900">
                            {CALIFICACIONES[calificacionFinal.calificacion] ?? calificacionFinal.calificacion}
                            <span className="ml-2 text-base font-normal text-indigo-600">({calificacionFinal.puntaje_total} pts)</span>
                        </p>
                    </div>
                )}

                {/* Sección de apelación */}
                {apelacion && (
                    <ApelacionPanel
                        apelacion={apelacion}
                        destinoApelacionCierre={destinoApelacionCierre}
                        nominaId={nomina.id}
                        categorias={categorias}
                        evidenciasApelacionPorCategoria={evidenciasApelacionPorCategoria ?? {}}
                    />
                )}

            </AppLayout>
        </>
    );
}

function LicenciaPanel({ nomina }) {
    const fileRef = useRef(null);
    const { data, setData, post, processing, errors } = useForm({
        plazo_licencia: nomina.plazo_licencia ?? '',
        documento:      null,
    });

    function submit(e) {
        e.preventDefault();
        post(`/secretario/expedientes/${nomina.id}/licencia-plazo`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData('documento', null);
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    }

    const formatDate = (dateStr) => {
        if (!dateStr) return null;
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    };

    const plazoVigente = nomina.plazo_licencia
        ? new Date(nomina.plazo_licencia) >= new Date(new Date().toDateString())
        : false;

    return (
        <div className="mt-6 bg-amber-50 border border-amber-200 rounded-xl p-5">
            <h2 className="text-sm font-semibold text-amber-800 mb-1">Licencia médica — Plazo especial</h2>
            {nomina.observacion_licencia && (
                <p className="text-xs text-amber-700 mb-4">{nomina.observacion_licencia}</p>
            )}

            {nomina.plazo_licencia && (
                <div className="flex items-center gap-4 mb-4">
                    <p className={`text-sm font-semibold ${plazoVigente ? 'text-green-700' : 'text-red-700'}`}>
                        Plazo actual: {formatDate(nomina.plazo_licencia)}
                        <span className="ml-1.5 font-normal text-xs">({plazoVigente ? 'vigente' : 'vencido'})</span>
                    </p>
                    {nomina.url_documento_licencia && (
                        <a
                            href={nomina.url_documento_licencia}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs text-[#0096D6] hover:underline flex items-center gap-1"
                        >
                            <DownloadIcon /> Ver documento
                        </a>
                    )}
                </div>
            )}

            <form onSubmit={submit} className="space-y-3">
                <div className="flex flex-col sm:flex-row gap-3">
                    <div className="flex-1">
                        <label className="block text-xs font-medium text-amber-800 mb-1">
                            {nomina.plazo_licencia ? 'Actualizar plazo' : 'Asignar plazo especial'}
                        </label>
                        <input
                            type="date"
                            value={data.plazo_licencia}
                            onChange={e => setData('plazo_licencia', e.target.value)}
                            className="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 bg-white"
                        />
                        {errors.plazo_licencia && (
                            <p className="text-xs text-red-600 mt-1">{errors.plazo_licencia}</p>
                        )}
                    </div>

                    <div className="flex-1">
                        <label className="block text-xs font-medium text-amber-800 mb-1">
                            Documento de respaldo (opcional)
                        </label>
                        <div
                            onClick={() => fileRef.current?.click()}
                            className="border border-dashed border-amber-300 rounded-lg px-3 py-2 text-sm text-amber-700 cursor-pointer hover:border-amber-500 bg-white"
                        >
                            {data.documento ? data.documento.name : 'PDF, JPG o PNG (máx. 5 MB)'}
                        </div>
                        <input
                            ref={fileRef}
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            className="sr-only"
                            onChange={e => setData('documento', e.target.files[0] ?? null)}
                        />
                        {errors.documento && (
                            <p className="text-xs text-red-600 mt-1">{errors.documento}</p>
                        )}
                    </div>
                </div>

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={processing || !data.plazo_licencia}
                        className="px-5 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-40 text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        {processing ? 'Guardando...' : (nomina.plazo_licencia ? 'Actualizar plazo' : 'Asignar plazo')}
                    </button>
                </div>
            </form>
        </div>
    );
}

function ApelacionPanel({ apelacion, destinoApelacionCierre, nominaId, categorias = [], evidenciasApelacionPorCategoria = {} }) {
    const cerrarForm = useForm({});

    function submitCerrar(e) {
        e.preventDefault();
        cerrarForm.patch(`/secretario/apelaciones/${apelacion.id}/cerrar`, { preserveScroll: true });
    }

    const estadoApelacion = {
        en_revision: { label: 'Con evidencias — pendiente de envío', cls: 'bg-blue-100 text-blue-700' },
        resuelta:    { label: 'Resuelta',                            cls: 'bg-green-100 text-green-700' },
        rechazada:   { label: 'Rechazada',                          cls: 'bg-red-100 text-red-700' },
    };

    const badge = estadoApelacion[apelacion.estado] ?? { label: apelacion.estado, cls: 'bg-gray-100 text-gray-600' };
    const totalEvidencias = Object.values(evidenciasApelacionPorCategoria).reduce((sum, arr) => sum + arr.length, 0);

    return (
        <div className="mt-6 bg-white border border-orange-200 rounded-xl p-5">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h2 className="text-sm font-semibold text-gray-800">Apelación del académico</h2>
                    <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${badge.cls}`}>{badge.label}</span>
                </div>
                {totalEvidencias > 0 && (
                    <span className="text-xs text-gray-400">
                        {totalEvidencias} {totalEvidencias === 1 ? 'evidencia adjunta' : 'evidencias adjuntas'} — revísalas en cada carpeta
                    </span>
                )}
            </div>

            {/* Enviar a evaluación */}
            {apelacion.estado === 'en_revision' && (
                <form onSubmit={submitCerrar} className="border-t border-gray-100 pt-4">
                    <p className="text-xs text-gray-500 mb-3">
                        Al enviar, el expediente quedará disponible para re-evaluación por{' '}
                        <strong>{destinoApelacionCierre?.label ?? 'CCA o CCDA'}</strong>
                        {destinoApelacionCierre?.destino === 'ccda'
                            ? ' (apelación 2° nivel — calificación Regular o Deficiente).'
                            : ' (Comisión de Calificación de la facultad).'}
                    </p>
                    <div className="flex justify-end">
                        <button type="submit" disabled={cerrarForm.processing}
                            className="px-5 py-2.5 bg-purple-700 text-white text-sm font-medium rounded-lg hover:bg-purple-800 disabled:opacity-40 transition-colors">
                            {cerrarForm.processing ? 'Enviando...' : 'Enviar a evaluación'}
                        </button>
                    </div>
                </form>
            )}

            {apelacion.estado === 'resuelta' && (
                <div className="border-t border-gray-100 pt-4 text-xs text-gray-500">
                    Apelación enviada a{' '}
                    {apelacion.destino === 'ccda' ? 'CCDA (2° nivel)' : 'CCA'}.
                    {apelacion.destino === 'ccda'
                        ? ' Pendiente de resolución por el analista CCDA.'
                        : ' El expediente está en proceso de re-evaluación por la CCA.'}
                </div>
            )}
        </div>
    );
}

function BackIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
    );
}

function DownloadIcon() {
    return (
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
    );
}

