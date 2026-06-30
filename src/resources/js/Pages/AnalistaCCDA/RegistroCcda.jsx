import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

function StatusBadge({ ok, label }) {
    return ok
        ? <span className="text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">✓ {label}</span>
        : <span className="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded-full">✗ {label}</span>;
}

const ESTADO_CFG = {
    verificado:        { cls: 'bg-green-100 text-green-700', label: 'Verificado' },
    con_observaciones: { cls: 'bg-amber-100 text-amber-700', label: 'Con obs.' },
    pendiente:         { cls: 'bg-gray-100 text-gray-500',   label: 'Pendiente' },
};

function DaConocerTable({ academicos }) {
    if (!academicos?.length) return null;

    return (
        <div className="border-t border-gray-100 bg-slate-50/80">
            <p className="px-5 py-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                Se da a conocer — no participan de la evaluación CCA
            </p>
            <table className="w-full text-xs">
                <thead>
                    <tr className="border-b border-slate-200 text-slate-400 uppercase tracking-wide">
                        <th className="text-left px-4 py-2 font-medium">Académico</th>
                        <th className="text-left px-4 py-2 font-medium">Cargo</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {academicos.map(ac => (
                        <tr key={ac.id}>
                            <td className="px-4 py-2.5">
                                <p className="font-medium text-slate-700">{ac.nombre}</p>
                                <p className="text-slate-400">{ac.rut}</p>
                            </td>
                            <td className="px-4 py-2.5 text-slate-600">{ac.cargo}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function AcademicoTable({ academicos }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-xs">
                <thead>
                    <tr className="border-b border-gray-100 text-gray-400 uppercase tracking-wide">
                        <th className="text-left px-4 py-2 font-medium">Académico</th>
                        <th className="text-center px-3 py-2 font-medium">Nota / Concepto</th>
                        <th className="text-center px-3 py-2 font-medium">Nota = Concepto</th>
                        <th className="text-center px-3 py-2 font-medium">Apelación</th>
                        <th className="text-center px-3 py-2 font-medium">Retroalimentación</th>
                        <th className="text-center px-3 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {academicos.map(ac => {
                        const cfg = ESTADO_CFG[ac.estado] ?? ESTADO_CFG.pendiente;
                        return (
                            <tr key={ac.id} className="hover:bg-gray-50/50">
                                <td className="px-4 py-2.5">
                                    <p className="font-medium text-gray-800">{ac.nombre}</p>
                                    <p className="text-gray-400">{ac.rut}</p>
                                </td>
                                <td className="px-3 py-2.5 text-center">
                                    {ac.nota
                                        ? <span className="font-semibold text-gray-700">{ac.nota} — {ac.concepto}</span>
                                        : <span className="text-gray-400">Sin calif.</span>}
                                </td>
                                <td className="px-3 py-2.5 text-center">
                                    {ac.nota
                                        ? (ac.nota_concepto_ok
                                            ? <span className="text-green-600 font-medium">✓</span>
                                            : <span className="text-red-500 font-medium" title="Nota y concepto inconsistentes">✗</span>)
                                        : <span className="text-gray-300">—</span>}
                                </td>
                                <td className="px-3 py-2.5 text-center">
                                    {!ac.apelacion_info
                                        ? <span className="text-gray-400">Sin apelación</span>
                                        : (ac.apel_resuelta
                                            ? <span className="text-green-600 font-medium">✓ Resuelta</span>
                                            : <span className="text-red-500 font-medium">Pendiente</span>)
                                    }
                                </td>
                                <td className="px-3 py-2.5 text-center">
                                    {ac.retro_registrada
                                        ? <span className="text-green-600 font-medium">✓</span>
                                        : <span className="text-gray-400">—</span>}
                                </td>
                                <td className="px-3 py-2.5 text-center">
                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${cfg.cls}`}>
                                        {cfg.label}
                                    </span>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function FacultadRow({ facultad, periodoId }) {
    const v         = facultad.verificacion;
    const s         = facultad.stats;
    const bloqueada = v?.verificado_en != null;
    const [open, setOpen] = useState(false);

    const form = useForm({
        doc_fisica_archivada: v?.doc_fisica_archivada ?? false,
        notas_comunicadas:    v?.notas_comunicadas    ?? false,
        observaciones:        v?.observaciones        ?? '',
        cerrar:               false,
    });

    function guardar(cerrar = false) {
        form.setData('cerrar', cerrar);
        form.post(`/analista/registro-ccda/${facultad.id}`, { preserveScroll: true });
    }

    const pct = s.total > 0 ? Math.round((s.evaluados / s.total) * 100) : 0;

    const nVerif = facultad.academicos.filter(a => a.estado === 'verificado').length;
    const nObs   = facultad.academicos.filter(a => a.estado === 'con_observaciones').length;
    const nPend  = facultad.academicos.filter(a => a.estado === 'pendiente').length;

    return (
        <div className={`bg-white rounded-xl border ${bloqueada ? 'border-green-200' : 'border-gray-200'} overflow-hidden`}>
            {/* Cabecera */}
            <div className="flex items-center gap-4 px-5 py-4 border-b border-gray-100">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <p className="font-semibold text-gray-900 text-sm">{facultad.nombre}</p>
                        {bloqueada && (
                            <span className="text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                Verificada {v.verificado_en}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2 mt-1.5">
                        <div className="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className={`h-full rounded-full transition-all ${pct === 100 ? 'bg-green-500' : 'bg-[#1B2D6B]'}`}
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                        <span className="text-xs text-gray-500 shrink-0">
                            {s.evaluados}/{s.total} evaluados
                        </span>
                    </div>
                </div>
                <div className="hidden sm:flex items-center gap-1.5 flex-wrap">
                    <StatusBadge ok={s.evaluados === s.total} label="Evaluados" />
                    <StatusBadge ok={s.apel_pendientes === 0} label="Sin apel. pend." />
                    <StatusBadge ok={s.ccda_pendientes === 0} label="CCDA" />
                    <StatusBadge ok={s.proceso_cerrado} label="Acta cierre" />
                </div>
                <button
                    type="button"
                    onClick={() => setOpen(o => !o)}
                    className="text-xs font-medium text-[#1B2D6B] hover:underline shrink-0"
                >
                    {open ? 'Ocultar académicos ▲' : `Ver académicos (${s.total}${facultad.da_conocer?.length ? ` + ${facultad.da_conocer.length} da conocer` : ''}) ▼`}
                </button>
            </div>

            {/* Tabla de académicos */}
            {open && (
                <div className="border-b border-gray-100">
                    {/* Resumen de estados */}
                    <div className="flex gap-3 px-5 py-2 bg-gray-50 text-xs text-gray-500">
                        <span className="text-green-700 font-medium">{nVerif} verificados</span>
                        {nObs > 0 && <span className="text-amber-600 font-medium">{nObs} con observaciones</span>}
                        {nPend > 0 && <span className="text-gray-500">{nPend} pendientes</span>}
                    </div>
                    <AcademicoTable academicos={facultad.academicos} />
                    <DaConocerTable academicos={facultad.da_conocer} />
                </div>
            )}

            {/* Checklist + observaciones */}
            <div className="px-5 py-4 space-y-3">
                {!s.lista_para_verificar && (
                    <div className="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                        Esta facultad aún no cumple todos los requisitos automáticos para ser verificada.
                        {s.apel_pendientes > 0 && <span className="block">· {s.apel_pendientes} apelación(es) pendiente(s).</span>}
                        {s.reeval_cca_pendientes > 0 && <span className="block">· {s.reeval_cca_pendientes} apelación(es) pendiente(s) de re-evaluación CCA.</span>}
                        {s.ccda_pendientes > 0 && <span className="block">· {s.ccda_pendientes} apelación(es) CCDA sin resolver.</span>}
                        {!s.proceso_cerrado && <span className="block">· El secretario no ha cerrado el proceso.</span>}
                        {s.evaluados < s.total && <span className="block">· {s.total - s.evaluados} expediente(s) sin calificación.</span>}
                    </div>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label className={`flex items-start gap-2 text-sm ${bloqueada ? 'cursor-default text-gray-500' : 'cursor-pointer text-gray-700'}`}>
                        <input
                            type="checkbox"
                            disabled={bloqueada}
                            checked={form.data.doc_fisica_archivada}
                            onChange={e => form.setData('doc_fisica_archivada', e.target.checked)}
                            className="mt-0.5 rounded border-gray-300"
                        />
                        <span>Documentación física archivada</span>
                    </label>
                    <label className={`flex items-start gap-2 text-sm ${bloqueada ? 'cursor-default text-gray-500' : 'cursor-pointer text-gray-700'}`}>
                        <input
                            type="checkbox"
                            disabled={bloqueada}
                            checked={form.data.notas_comunicadas}
                            onChange={e => form.setData('notas_comunicadas', e.target.checked)}
                            className="mt-0.5 rounded border-gray-300"
                        />
                        <span>Calificaciones comunicadas formalmente</span>
                    </label>
                </div>

                <div>
                    <label className="block text-xs text-gray-500 mb-1">Observaciones CCDA (opcional)</label>
                    <textarea
                        rows={2}
                        disabled={bloqueada}
                        value={form.data.observaciones}
                        onChange={e => form.setData('observaciones', e.target.value)}
                        maxLength={1000}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 disabled:bg-gray-50 disabled:text-gray-400"
                    />
                </div>

                {!bloqueada && (
                    <div className="flex items-center gap-2 pt-1">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => guardar(false)}
                            className="px-4 py-1.5 text-xs font-medium border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            Guardar borrador
                        </button>
                        <button
                            type="button"
                            disabled={form.processing || !s.lista_para_verificar}
                            onClick={() => guardar(true)}
                            title={!s.lista_para_verificar ? 'La facultad aún no cumple los requisitos' : ''}
                            className="px-4 py-1.5 text-xs font-medium bg-[#1B2D6B] text-white rounded-lg hover:bg-[#152558] disabled:opacity-50"
                        >
                            Cerrar verificación
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function RegistroCcda({
    periodo,
    etapa,
    facultades,
    total_facultades,
    facultades_verificadas,
    puede_cerrar,
}) {
    const { flash } = usePage().props;
    const [confirmandoCierre, setConfirmandoCierre] = useState(false);
    const [cierreProcessing, setCierreProcessing] = useState(false);

    function cerrarPeriodo() {
        if (!periodo) return;
        setCierreProcessing(true);
        router.post(`/analista/periodos/${periodo.id}/cerrar`, {}, {
            onFinish: () => { setCierreProcessing(false); setConfirmandoCierre(false); },
        });
    }

    const pctGlobal = total_facultades > 0
        ? Math.round((facultades_verificadas / total_facultades) * 100)
        : 0;

    return (
        <>
            <Head title="Registro CCDA" />
            <AppLayout title="Registro CCDA">
                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                        {etapa && (
                            <span className="ml-3 text-xs text-gray-400">
                                Etapa registro: {etapa.fecha_inicio} – {etapa.fecha_fin}
                                {etapa.esta_vigente
                                    ? <span className="ml-1 text-green-600 font-medium">(vigente)</span>
                                    : <span className="ml-1 text-gray-400">(fuera de ventana)</span>
                                }
                            </span>
                        )}
                    </p>
                )}

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

                {/* Progreso global */}
                {total_facultades > 0 && (
                    <div className={`rounded-xl border p-5 mb-6 ${periodo?.cerrado_en ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'}`}>
                        <div className="flex items-center justify-between mb-2">
                            <p className="text-sm font-medium text-gray-700">Progreso global</p>
                            <p className="text-sm font-semibold text-[#1B2D6B]">
                                {facultades_verificadas} / {total_facultades} facultades verificadas
                            </p>
                        </div>
                        <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className={`h-full rounded-full transition-all ${pctGlobal === 100 ? 'bg-green-500' : 'bg-[#1B2D6B]'}`}
                                style={{ width: `${pctGlobal}%` }}
                            />
                        </div>

                        {/* Estado de cierre */}
                        {periodo?.cerrado_en ? (
                            <div className="mt-3 flex items-center gap-3 flex-wrap">
                                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-100 text-green-800 text-sm font-semibold">
                                    Período cerrado — {periodo.cerrado_en}
                                </span>
                                <a
                                    href={`/analista/historial/${periodo.id}`}
                                    className="text-sm text-[#0096D6] hover:underline"
                                >
                                    Ver historial del período →
                                </a>
                            </div>
                        ) : puede_cerrar ? (
                            <div className="mt-3 pt-3 border-t border-gray-100">
                                {confirmandoCierre ? (
                                    <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                                        <p className="text-sm font-semibold text-red-800 mb-1">¿Confirmar cierre del período?</p>
                                        <p className="text-xs text-red-700 mb-3">
                                            Esta acción es irreversible. El período quedará cerrado y pasará al historial.
                                            No se podrán registrar más calificaciones ni modificaciones.
                                        </p>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                disabled={cierreProcessing}
                                                onClick={cerrarPeriodo}
                                                className="px-4 py-1.5 text-xs font-medium bg-red-700 text-white rounded-lg hover:bg-red-800 disabled:opacity-50"
                                            >
                                                {cierreProcessing ? 'Cerrando...' : 'Sí, cerrar período'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setConfirmandoCierre(false)}
                                                className="px-4 py-1.5 text-xs font-medium border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs text-gray-500">
                                            Todas las facultades verificadas. Puedes cerrar formalmente el período.
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => setConfirmandoCierre(true)}
                                            className="px-4 py-2 text-sm font-semibold bg-[#1B2D6B] text-white rounded-lg hover:bg-[#152558] transition-colors"
                                        >
                                            Cerrar período
                                        </button>
                                    </div>
                                )}
                            </div>
                        ) : pctGlobal === 100 ? (
                            <p className="text-xs text-green-700 mt-2 font-medium">
                                Todas las facultades verificadas. El proceso está listo para ser cerrado.
                            </p>
                        ) : null}
                    </div>
                )}

                {!periodo ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No hay período activo.</p>
                    </div>
                ) : facultades.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No hay nóminas cargadas para este período.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {facultades.map(f => (
                            <FacultadRow
                                key={f.id}
                                facultad={f}
                                periodoId={periodo.id}
                            />
                        ))}
                    </div>
                )}
            </AppLayout>
        </>
    );
}
