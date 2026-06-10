import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_BADGE = {
    activa:  'bg-amber-100 text-amber-800',
    cerrada: 'bg-gray-100 text-gray-600',
};

function ModalReincorporar({ solicitud, plazo, motivo, processing, onPlazoChange, onMotivoChange, onConfirm, onCancel }) {
    if (!solicitud) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/40" onClick={onCancel} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-reincorporar-title"
                className="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6"
            >
                <h3 id="modal-reincorporar-title" className="text-base font-semibold text-gray-900">
                    Reincorporar a {solicitud.academico.name}
                </h3>
                <p className="text-sm text-gray-500 mt-1">
                    Defina el nuevo plazo de carga de evidencias para este académico.
                </p>

                <div className="mt-5 space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Nueva fecha límite de carga de evidencias <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={plazo}
                            onChange={e => onPlazoChange(e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                            autoFocus
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Motivo de reincorporación <span className="text-gray-400">(opcional)</span>
                        </label>
                        <textarea
                            rows={3}
                            value={motivo}
                            onChange={e => onMotivoChange(e.target.value)}
                            placeholder="Ej.: Fin de licencia médica, retoma actividades docentes..."
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                        />
                    </div>
                </div>

                <div className="mt-6 flex gap-3 justify-end">
                    <button type="button" onClick={onCancel} disabled={processing}
                        className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50">
                        Cancelar
                    </button>
                    <button type="button" onClick={onConfirm} disabled={processing || !plazo}
                        className="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                        {processing ? 'Procesando...' : 'Confirmar reincorporación'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Solicitudes({ periodo, solicitudes, nominas }) {
    const { flash } = usePage().props;
    const [modalSolicitud, setModalSolicitud] = useState(null);
    const [plazoReincorporacion, setPlazoReincorporacion] = useState('');
    const [motivoReincorporacion, setMotivoReincorporacion] = useState('');
    const [processingReincorporacion, setProcessingReincorporacion] = useState(false);

    const form = useForm({
        nomina_id:    '',
        tipo:         'licencia_medica',
        fecha_inicio: '',
        fecha_fin:    '',
        motivo:       '',
        documento:    null,
    });

    function submit(e) {
        e.preventDefault();
        form.post('/secretario/solicitudes', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function abrirModalReincorporar(solicitud) {
        setModalSolicitud(solicitud);
        setPlazoReincorporacion('');
        setMotivoReincorporacion('');
    }

    function cerrarModalReincorporar() {
        if (processingReincorporacion) return;
        setModalSolicitud(null);
        setPlazoReincorporacion('');
        setMotivoReincorporacion('');
    }

    function confirmarReincorporacion() {
        if (!modalSolicitud || !plazoReincorporacion) return;

        setProcessingReincorporacion(true);
        router.patch(`/secretario/solicitudes/${modalSolicitud.id}/reincorporar`, {
            nuevo_plazo_evidencias: plazoReincorporacion,
            motivo_reincorporacion: motivoReincorporacion || null,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessingReincorporacion(false);
                cerrarModalReincorporar();
            },
        });
    }

    return (
        <>
            <Head title="Solicitudes" />
            <AppLayout title="Solicitudes y Excepciones">

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

                {!periodo ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-400 text-sm">
                        No hay período activo.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h2 className="text-sm font-semibold text-gray-700 mb-1">Iniciar solicitud</h2>
                            <p className="text-xs text-gray-400 mb-4">
                                Registre la solicitud para un académico de su nómina. Quedará activa de inmediato.
                            </p>

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Académico (nómina)</label>
                                    <select value={form.data.nomina_id}
                                        onChange={e => form.setData('nomina_id', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="">— Seleccionar —</option>
                                        {nominas.map(n => (
                                            <option key={n.id} value={n.id}>{n.label}</option>
                                        ))}
                                    </select>
                                    {form.errors.nomina_id && (
                                        <p className="text-xs text-red-600 mt-1">{form.errors.nomina_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                                    <select value={form.data.tipo}
                                        onChange={e => form.setData('tipo', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="licencia_medica">Licencia médica</option>
                                        <option value="extension_plazo">Extensión de plazo</option>
                                    </select>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Fecha inicio</label>
                                        <input type="date" value={form.data.fecha_inicio}
                                            onChange={e => form.setData('fecha_inicio', e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Fecha fin (opc.)</label>
                                        <input type="date" value={form.data.fecha_fin}
                                            onChange={e => form.setData('fecha_fin', e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Motivo</label>
                                    <textarea rows={3} value={form.data.motivo}
                                        onChange={e => form.setData('motivo', e.target.value)}
                                        placeholder="Indique el motivo de la solicitud..."
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none" />
                                    {form.errors.motivo && (
                                        <p className="text-xs text-red-600 mt-1">{form.errors.motivo}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Documento adjunto</label>
                                    <p className="text-xs text-gray-400 mb-1">Respaldo (PDF o imagen)</p>
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={e => form.setData('documento', e.target.files[0])}
                                        className="w-full text-sm" />
                                </div>

                                <button type="submit" disabled={form.processing}
                                    className="w-full bg-[#1B2D6B] text-white text-sm font-medium py-2.5 rounded-lg hover:bg-[#152558] disabled:opacity-50">
                                    {form.processing ? 'Registrando...' : 'Registrar solicitud'}
                                </button>
                            </form>
                        </div>

                        <div className="lg:col-span-2 space-y-3">
                            <h2 className="text-sm font-semibold text-gray-700">
                                Mis solicitudes ({solicitudes.length})
                            </h2>

                            {solicitudes.length === 0 ? (
                                <div className="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-400 text-sm">
                                    Aún no ha registrado solicitudes en este período.
                                </div>
                            ) : solicitudes.map(s => (
                                <div key={s.id} className={`bg-white rounded-xl border p-4 ${
                                    s.estado === 'activa' ? 'border-amber-300' : 'border-gray-200'
                                }`}>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${ESTADO_BADGE[s.estado] ?? 'bg-gray-100'}`}>
                                                    {s.estado_label}
                                                </span>
                                                <span className="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                                    {s.tipo_label}
                                                </span>
                                                {s.tipo === 'licencia_medica' && s.estado === 'activa' && (
                                                    <span className="text-xs bg-red-50 text-red-700 px-2 py-0.5 rounded-full">
                                                        Acceso bloqueado
                                                    </span>
                                                )}
                                            </div>
                                            <p className="font-semibold text-gray-900 text-sm mt-2">{s.academico.name}</p>
                                            <p className="text-xs text-gray-500">{s.academico.rut}</p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {s.fecha_inicio}{s.fecha_fin ? ` → ${s.fecha_fin}` : ''}
                                            </p>
                                            <p className="text-sm text-gray-600 mt-2">{s.motivo}</p>
                                            {s.estado === 'cerrada' && s.fecha_reincorporacion && (
                                                <div className="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500 space-y-0.5">
                                                    <p>Reincorporado: {s.fecha_reincorporacion}</p>
                                                    <p>Nuevo plazo evidencias: {s.nuevo_plazo_evidencias}</p>
                                                    {s.motivo_reincorporacion && (
                                                        <p>Motivo: {s.motivo_reincorporacion}</p>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex flex-col gap-2 items-end">
                                            {s.documento_url && (
                                                <a href={s.documento_url} className="text-xs text-[#0096D6] hover:underline">
                                                    Ver documento
                                                </a>
                                            )}
                                            {s.estado === 'activa' && (
                                                <button
                                                    onClick={() => abrirModalReincorporar(s)}
                                                    className="text-xs font-medium text-green-700 hover:underline"
                                                >
                                                    Cerrar / Reincorporar
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <ModalReincorporar
                    solicitud={modalSolicitud}
                    plazo={plazoReincorporacion}
                    motivo={motivoReincorporacion}
                    processing={processingReincorporacion}
                    onPlazoChange={setPlazoReincorporacion}
                    onMotivoChange={setMotivoReincorporacion}
                    onConfirm={confirmarReincorporacion}
                    onCancel={cerrarModalReincorporar}
                />
            </AppLayout>
        </>
    );
}
