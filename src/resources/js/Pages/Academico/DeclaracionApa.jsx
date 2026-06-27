import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const AREAS = [
    { hrsKey: 'hrs_docencia',       pctKey: 'pct_docencia',       label: 'Actividades de Docencia' },
    { hrsKey: 'hrs_investigacion',  pctKey: 'pct_investigacion',  label: 'Actividades de Investigación' },
    { hrsKey: 'hrs_extension',      pctKey: 'pct_extension',      label: 'Extensión y Vinculación' },
    { hrsKey: 'hrs_administracion', pctKey: 'pct_administracion', label: 'Administración Académica' },
];

/**
 * Normaliza horas → porcentajes (suma = 100).
 * Áreas sin horas reciben 0%. El ajuste de redondeo va al último área con horas.
 */
function calcularPorcentajes(horas, decimales = 2) {
    const factor    = Math.pow(10, decimales);
    const totalHrs  = AREAS.reduce((acc, a) => acc + (parseFloat(horas[a.hrsKey]) || 0), 0);

    if (totalHrs <= 0) {
        return Object.fromEntries(AREAS.map(a => [a.pctKey, 0]));
    }

    const areasConHrs = AREAS.filter(a => (parseFloat(horas[a.hrsKey]) || 0) > 0);
    const pcts = {};
    let sumaAcum = 0;

    for (const area of AREAS) {
        const hrs = parseFloat(horas[area.hrsKey]) || 0;
        if (hrs <= 0) {
            pcts[area.pctKey] = 0;
        } else {
            const pct = Math.round((hrs / totalHrs) * 100 * factor) / factor;
            pcts[area.pctKey] = pct;
            sumaAcum += pct;
        }
    }

    if (areasConHrs.length > 0) {
        const lastKey = areasConHrs[areasConHrs.length - 1].pctKey;
        pcts[lastKey] = Math.round((100 - (sumaAcum - pcts[lastKey])) * factor) / factor;
    }

    return pcts;
}

function ModalConfirmarHorasApa({
    open, semestreLabel, areas, pcts, totalHras, horasContrato, decimales,
    aceptado, processing, onAceptadoChange, onConfirm, onCancel,
}) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/40" onClick={onCancel} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-confirmar-apa-title"
                className="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto"
            >
                <h3 id="modal-confirmar-apa-title" className="text-base font-semibold text-gray-900">
                    Confirmar compromiso APA — {semestreLabel}
                </h3>
                <p className="text-sm text-gray-500 mt-1">
                    Revise que las horas declaradas coinciden con lo acordado con su director de departamento.
                    Una vez confirmado no podrá modificarse.
                </p>

                <div className="mt-4 rounded-lg border border-gray-200 overflow-hidden text-sm">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-gray-50 text-xs text-gray-500">
                                <th className="text-left px-3 py-2 font-medium">Área</th>
                                <th className="text-right px-3 py-2 font-medium">Horas</th>
                                <th className="text-right px-3 py-2 font-medium">%</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {areas.map(a => (
                                <tr key={a.hrsKey}>
                                    <td className="px-3 py-2 text-gray-700">{a.label}</td>
                                    <td className="px-3 py-2 text-right tabular-nums text-gray-800">
                                        {(parseFloat(a.hrs) || 0).toFixed(1)} h
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums font-medium text-[#1B2D6B]">
                                        {pcts[a.pctKey].toFixed(decimales)}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-blue-50 font-semibold text-blue-900">
                                <td className="px-3 py-2">Total</td>
                                <td className="px-3 py-2 text-right tabular-nums">{totalHras.toFixed(1)} h</td>
                                <td className="px-3 py-2 text-right">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {horasContrato > 0 && (
                    <p className="mt-3 text-xs text-gray-500">
                        Horas de contrato del semestre: <span className="font-semibold text-gray-700">{horasContrato} h</span>
                    </p>
                )}

                <label className="mt-4 flex items-start gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={aceptado}
                        onChange={e => onAceptadoChange(e.target.checked)}
                        className="mt-0.5 rounded border-gray-300 text-[#1B2D6B] focus:ring-[#1B2D6B]/30"
                    />
                    <span className="text-sm text-gray-700 leading-snug">
                        Confirmo que las horas declaradas son correctas y corresponden a mi acuerdo
                        con la jefatura o director de departamento.
                    </span>
                </label>

                <div className="mt-6 flex gap-3 justify-end">
                    <button type="button" onClick={onCancel} disabled={processing}
                        className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50">
                        Cancelar
                    </button>
                    <button type="button" onClick={onConfirm} disabled={processing || !aceptado}
                        className="px-4 py-2 text-sm font-medium bg-[#1B2D6B] text-white rounded-lg hover:bg-[#152558] disabled:opacity-50">
                        {processing ? 'Confirmando...' : `Confirmar ${semestreLabel}`}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function DeclaracionApa({
    periodo, nomina, semestre, semestreLabel, yaDeclarado, fechaCierre, datos, config, soloRegistro, cicloEvaluacion,
}) {
    const { flash }  = usePage().props;
    const decimales      = config?.decimales_pct  ?? 2;
    const horasContrato  = config?.horas_contrato ?? 0;

    const form = useForm({
        semestre,
        hrs_docencia:       datos?.hrs_docencia       ?? '',
        hrs_investigacion:  datos?.hrs_investigacion  ?? '',
        hrs_extension:      datos?.hrs_extension      ?? '',
        hrs_administracion: datos?.hrs_administracion ?? '',
        hrs_otras:          datos?.hrs_otras          ?? '',
    });

    const { pcts, totalHras } = useMemo(() => {
        const total = AREAS.reduce((acc, a) => acc + (parseFloat(form.data[a.hrsKey]) || 0), 0);
        return {
            pcts:      calcularPorcentajes(form.data, decimales),
            totalHras: total,
        };
    }, [form.data, decimales]);

    const tieneHoras       = totalHras > 0;
    const horasIncorrectas = horasContrato > 0 && tieneHoras && Math.abs(totalHras - horasContrato) > 0.01;

    const [modalAbierto, setModalAbierto] = useState(false);
    const [aceptado, setAceptado]         = useState(false);

    const areasResumen = useMemo(() => AREAS.map(a => ({
        ...a,
        hrs: form.data[a.hrsKey],
    })), [form.data]);

    function abrirModal(e) {
        e.preventDefault();
        setAceptado(false);
        setModalAbierto(true);
    }

    function cerrarModal() {
        if (form.processing) return;
        setModalAbierto(false);
        setAceptado(false);
    }

    function confirmarDeclaracion() {
        form.post('/academico/declaracion-apa', {
            onFinish: () => {
                setModalAbierto(false);
                setAceptado(false);
            },
        });
    }

    if (!periodo) {
        return (
            <AppLayout title="Declaración APA">
                <div className="max-w-lg mx-auto">
                    <p className="text-sm text-gray-400">No hay período activo.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <>
            <Head title={`Declaración APA — ${semestreLabel}`} />
            <AppLayout title={`Declaración APA — ${semestreLabel}`}>
                <div className="max-w-lg mx-auto space-y-4">

                    {flash?.success && (
                        <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {flash?.error && (
                        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}

                    {/* Información del período y semestre */}
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-xs text-gray-500">
                            Período: {periodo.nombre}
                        </p>
                        {fechaCierre && (
                            <p className="text-xs text-gray-500 mt-1">
                                Fecha de cierre: {fechaCierre}
                            </p>
                        )}
                    </div>

                    {/* Formulario */}
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-base font-semibold text-gray-900">
                            {semestreLabel}
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Ingresa las horas dedicadas a cada área. El total debe ser exactamente{' '}
                            <span className="font-semibold text-gray-700">{horasContrato} h</span>{' '}
                            según tu contrato. El porcentaje se calcula automáticamente.
                        </p>
                        {soloRegistro && (
                            <p className="text-sm text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 mt-3">
                                Este período solo registra su declaración APA. La evaluación formal corresponde
                                al cierre de su ciclo ({cicloEvaluacion?.semestres ?? 4} semestres
                                · {cicloEvaluacion?.horas ?? '—'} h acumuladas).
                            </p>
                        )}

                        {yaDeclarado ? (
                            <div className="mt-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                                Este semestre ya fue confirmado.
                            </div>
                        ) : (
                            <form onSubmit={abrirModal} className="mt-6 space-y-4">

                                {/* ── Áreas del 100% ─────────────────────────── */}
                                {AREAS.map(a => (
                                    <div key={a.hrsKey}>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            {a.label}
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="number"
                                                min={0}
                                                step={0.5}
                                                value={form.data[a.hrsKey]}
                                                onChange={e => form.setData(a.hrsKey, e.target.value)}
                                                placeholder="0"
                                                disabled={form.processing}
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                            />
                                            <span className="text-sm text-gray-400 shrink-0">h</span>
                                            <span className={`text-sm font-semibold shrink-0 w-16 text-right tabular-nums ${
                                                tieneHoras ? 'text-[#1B2D6B]' : 'text-gray-300'
                                            }`}>
                                                {tieneHoras ? `${pcts[a.pctKey].toFixed(decimales)}%` : '—%'}
                                            </span>
                                        </div>
                                        {form.errors[a.hrsKey] && (
                                            <p className="text-xs text-red-600 mt-1">{form.errors[a.hrsKey]}</p>
                                        )}
                                    </div>
                                ))}

                                {/* ── Resumen total ───────────────────────────── */}
                                <div className={`rounded-lg px-4 py-3 text-sm ${
                                    !tieneHoras
                                        ? 'bg-gray-50 text-gray-400 border border-gray-200'
                                        : horasIncorrectas
                                            ? 'bg-red-50 text-red-800 border border-red-200'
                                            : 'bg-blue-50 text-blue-800 border border-blue-200'
                                }`}>
                                    <div className="flex justify-between font-medium">
                                        <span>Total horas declaradas</span>
                                        <span className="tabular-nums">{totalHras.toFixed(1)} h</span>
                                    </div>
                                    {tieneHoras && horasContrato > 0 && (
                                        <p className="text-xs mt-0.5 opacity-70">
                                            Requerido: {horasContrato} h según contrato
                                        </p>
                                    )}
                                </div>

                                {/* ── Error bloqueante: horas no coinciden con contrato ── */}
                                {horasIncorrectas && (
                                    <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                                        El total declarado ({totalHras.toFixed(1)} h) no coincide con las horas
                                        de contrato del semestre ({horasContrato} h). Ajusta los valores para poder confirmar.
                                    </div>
                                )}

                                {/* ── Otras actividades (fuera del 100%) ──────── */}
                                <div className="border-t border-gray-100 pt-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Otras actividades
                                        <span className="ml-2 font-normal text-xs text-gray-400">
                                            (fuera del 100% · validado por CCDA)
                                        </span>
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="number"
                                            min={0}
                                            step={0.5}
                                            value={form.data.hrs_otras}
                                            onChange={e => form.setData('hrs_otras', e.target.value)}
                                            placeholder="0"
                                            disabled={form.processing}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                        />
                                        <span className="text-sm text-gray-400 shrink-0">h</span>
                                        <span className="shrink-0 w-16" />
                                    </div>
                                    {form.errors.hrs_otras && (
                                        <p className="text-xs text-red-600 mt-1">{form.errors.hrs_otras}</p>
                                    )}
                                </div>

                                <p className="text-xs text-gray-400">
                                    Una vez confirmado no puede modificarse. Si necesitas cambios, contacta al analista CCDA.
                                </p>

                                <button
                                    type="submit"
                                    disabled={form.processing || !tieneHoras || horasIncorrectas}
                                    className="w-full bg-[#1B2D6B] text-white text-sm font-medium py-2.5 rounded-lg hover:bg-[#152558] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    {form.processing ? 'Confirmando...' : `Confirmar ${semestreLabel}`}
                                </button>
                            </form>
                        )}
                    </div>
                </div>

                <ModalConfirmarHorasApa
                    open={modalAbierto}
                    semestreLabel={semestreLabel}
                    areas={areasResumen}
                    pcts={pcts}
                    totalHras={totalHras}
                    horasContrato={horasContrato}
                    decimales={decimales}
                    aceptado={aceptado}
                    processing={form.processing}
                    onAceptadoChange={setAceptado}
                    onConfirm={confirmarDeclaracion}
                    onCancel={cerrarModal}
                />
            </AppLayout>
        </>
    );
}
