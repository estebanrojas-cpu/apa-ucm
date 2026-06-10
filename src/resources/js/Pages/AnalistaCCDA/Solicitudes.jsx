import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_BADGE = {
    activa:  'bg-amber-100 text-amber-800',
    cerrada: 'bg-gray-100 text-gray-600',
};

function SolicitudCard({ s }) {
    return (
        <div className={`bg-white rounded-xl border p-4 ${
            s.estado === 'activa' ? 'border-amber-300' : 'border-gray-200'
        }`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${ESTADO_BADGE[s.estado] ?? 'bg-gray-100 text-gray-600'}`}>
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
                    <p className="text-xs text-gray-500">{s.academico.rut} · {s.academico.facultad}</p>
                    <p className="text-xs text-gray-500 mt-1">
                        {s.fecha_inicio}{s.fecha_fin ? ` → ${s.fecha_fin}` : ''}
                    </p>
                    <p className="text-sm text-gray-600 mt-2">{s.motivo}</p>
                    {s.iniciada_por && (
                        <p className="text-xs text-gray-400 mt-1">Iniciada por: {s.iniciada_por}</p>
                    )}
                    {s.estado === 'cerrada' && s.fecha_reincorporacion && (
                        <div className="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500 space-y-0.5">
                            <p>Reincorporado: {s.fecha_reincorporacion} por {s.reincorporado_por}</p>
                            <p>Nuevo plazo evidencias: {s.nuevo_plazo_evidencias}</p>
                            {s.motivo_reincorporacion && (
                                <p>Motivo: {s.motivo_reincorporacion}</p>
                            )}
                        </div>
                    )}
                </div>
                {s.documento_url && (
                    <a href={s.documento_url} className="text-xs text-[#0096D6] hover:underline">
                        Ver documento
                    </a>
                )}
            </div>
        </div>
    );
}

export default function Solicitudes({ periodo, solicitudes }) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Solicitudes y Excepciones" />
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
                    <div className="space-y-4">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-700">
                                Solicitudes del período ({solicitudes.length})
                            </h2>
                            <p className="text-xs text-gray-400 mt-1">
                                Vista de solo lectura. Las solicitudes son gestionadas por los secretarios de facultad.
                            </p>
                        </div>

                        {solicitudes.length === 0 ? (
                            <div className="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-400 text-sm">
                                No hay solicitudes registradas en este período.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {solicitudes.map(s => (
                                    <SolicitudCard key={s.id} s={s} />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </AppLayout>
        </>
    );
}
