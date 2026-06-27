import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADOS = {
    pendiente:      { label: 'Pendiente',       cls: 'bg-gray-100 text-gray-600' },
    en_carga:       { label: 'En revisión',      cls: 'bg-blue-100 text-blue-700' },
    carga_cerrada:  { label: 'Completo',          cls: 'bg-green-100 text-green-700' },
    en_evaluacion:  { label: 'En evaluación',    cls: 'bg-purple-100 text-purple-700' },
    evaluado:       { label: 'Evaluado',          cls: 'bg-indigo-100 text-indigo-700' },
    apelado:        { label: 'Apelado',           cls: 'bg-orange-100 text-orange-700' },
    cerrado:        { label: 'Cerrado',            cls: 'bg-red-100 text-red-600' },
};

const FILTROS_ESTADO = [
    { value: '',               label: 'Todos los estados' },
    { value: 'pendiente',      label: 'Pendiente' },
    { value: 'en_carga',       label: 'En revisión' },
    { value: 'carga_cerrada',  label: 'Completo' },
    { value: 'en_evaluacion',  label: 'En evaluación' },
    { value: 'evaluado',       label: 'Evaluado' },
    { value: 'apelado',        label: 'Apelado' },
    { value: 'cerrado',        label: 'Cerrado' },
];

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const [y, m, d] = dateStr.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
}

export default function Expedientes({ periodo, expedientes, plazo, actaCierre, puedesCerrarProceso, motivoNoPuede, requisitosCierre }) {
    const { flash, auth } = usePage().props;
    const facultad = auth.user.facultad?.nombre ?? '—';

    const [search,                setSearch]                = useState('');
    const [filtroEstado,          setFiltroEstado]          = useState('');
    const [editingPlazo,          setEditingPlazo]          = useState(false);
    const [confirmCierre,         setConfirmCierre]         = useState(false);
    const [confirmCierreProceso,  setConfirmCierreProceso]  = useState(false);

    const plazoForm = useForm({ fecha_limite: plazo?.fecha_limite ?? '' });

    const filtrados = useMemo(() => {
        const q = search.toLowerCase();
        return expedientes.filter(e => {
            const matchSearch = !q ||
                e.academico.name.toLowerCase().includes(q) ||
                (e.academico.rut ?? '').toLowerCase().includes(q);
            const matchEstado = !filtroEstado || e.estado === filtroEstado;
            return matchSearch && matchEstado;
        });
    }, [expedientes, search, filtroEstado]);

    const total      = expedientes.length;
    const pendientes = expedientes.filter(e => e.estado === 'pendiente').length;
    const enRevision = expedientes.filter(e => e.estado === 'en_carga').length;
    const completos  = expedientes.filter(e => e.estado === 'carga_cerrada').length;

    function savePlazo(e) {
        e.preventDefault();
        plazoForm.post('/secretario/plazos', {
            preserveScroll: true,
            onSuccess: () => setEditingPlazo(false),
        });
    }

    function cancelPlazo() {
        plazoForm.setData('fecha_limite', plazo?.fecha_limite ?? '');
        setEditingPlazo(false);
    }

    function ejecutarCierre() {
        router.post('/secretario/cierre', {}, {
            preserveScroll: true,
            onSuccess: () => setConfirmCierre(false),
        });
    }

    function ejecutarCierreProceso() {
        router.post('/secretario/cierre-proceso', {}, {
            preserveScroll: true,
            onSuccess: () => setConfirmCierreProceso(false),
        });
    }

    function refresh() {
        router.reload({ preserveScroll: true });
    }

    if (!periodo) {
        return (
            <>
                <Head title="Expedientes" />
                <AppLayout title="Expedientes">
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p className="text-gray-400 text-sm">No hay un período activo actualmente.</p>
                    </div>
                </AppLayout>
            </>
        );
    }

    return (
        <>
            <Head title="Expedientes" />
            <AppLayout title="Expedientes">

                {/* Cabecera */}
                <div className="flex items-start justify-between -mt-4 mb-6">
                    <div>
                        <p className="text-sm text-gray-500">
                            Período: <span className="font-medium text-gray-700">{periodo.nombre}</span>
                        </p>
                        <p className="text-sm text-gray-500">
                            Facultad: <span className="font-medium text-gray-700">{facultad}</span>
                        </p>
                    </div>
                    <button onClick={refresh}
                        className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors"
                        title="Actualizar datos">
                        <RefreshIcon />
                        Actualizar
                    </button>
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

                {/* ── Sección plazo de entrega ───────────────────── */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold text-gray-700">Plazo de entrega de evidencias</p>
                            {plazo ? (
                                <div className="flex items-center gap-2 mt-1">
                                    <p className="text-sm text-gray-600">
                                        {formatDate(plazo.fecha_limite)}
                                    </p>
                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                        plazo.vigente
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-600'
                                    }`}>
                                        {plazo.vigente ? 'Vigente' : 'Vencido'}
                                    </span>
                                    <span className="text-xs text-gray-400">
                                        · Configurado el {plazo.actualizado}
                                    </span>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-400 mt-1">Sin plazo configurado</p>
                            )}
                        </div>

                        {!editingPlazo && (
                            <button
                                onClick={() => {
                                    plazoForm.setData('fecha_limite', plazo?.fecha_limite ?? '');
                                    setEditingPlazo(true);
                                }}
                                className="text-sm font-medium text-[#0096D6] hover:underline"
                            >
                                {plazo ? 'Modificar plazo' : 'Configurar plazo'}
                            </button>
                        )}
                    </div>

                    {/* Formulario inline */}
                    {editingPlazo && (
                        <form onSubmit={savePlazo} className="mt-4 pt-4 border-t border-gray-100">
                            <div className="flex items-end gap-4">
                                <div className="flex-1 max-w-xs">
                                    <label className="block text-xs font-medium text-gray-600 mb-1">
                                        Nueva fecha límite
                                    </label>
                                    <input
                                        type="date"
                                        value={plazoForm.data.fecha_limite}
                                        onChange={e => plazoForm.setData('fecha_limite', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                        autoFocus
                                    />
                                    {plazoForm.errors.fecha_limite && (
                                        <p className="mt-1 text-xs text-red-600">{plazoForm.errors.fecha_limite}</p>
                                    )}
                                </div>
                                <div className="flex gap-3 pb-0.5">
                                    <button
                                        type="submit"
                                        disabled={plazoForm.processing || !plazoForm.data.fecha_limite}
                                        className="text-sm font-medium bg-[#1B2D6B] text-white px-4 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-50 transition-colors"
                                    >
                                        {plazoForm.processing ? 'Guardando...' : 'Guardar'}
                                    </button>
                                    <button type="button" onClick={cancelPlazo}
                                        className="text-sm text-gray-500 hover:text-gray-700">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                            <p className="mt-2 text-xs text-gray-400">
                                Los académicos de la facultad serán bloqueados para subir evidencias al vencer esta fecha.
                            </p>
                        </form>
                    )}
                </div>

                {/* ── Cierre formal ───────────────────────────── */}
                {periodo && (
                    plazo?.cerrado ? (
                        <div className="bg-red-50 border border-red-200 rounded-xl p-5 mb-6 flex items-center justify-between">
                            <div>
                                <p className="text-sm font-semibold text-red-800">Recepción cerrada formalmente</p>
                                <p className="text-xs text-red-600 mt-0.5">
                                    Cerrada el {plazo.cerrado_en}. No se aceptan nuevas cargas de evidencias.
                                </p>
                            </div>
                            <LockIcon />
                        </div>
                    ) : (
                        <div className="bg-white border border-gray-200 rounded-xl p-5 mb-6 flex items-center justify-between">
                            <div>
                                <p className="text-sm font-semibold text-gray-800">Cierre formal de recepción</p>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    Cierra todos los expedientes activos y bloquea nuevas cargas de evidencias.
                                </p>
                            </div>
                            {!confirmCierre ? (
                                <button
                                    onClick={() => setConfirmCierre(true)}
                                    className="ml-4 shrink-0 px-4 py-2 border border-red-300 text-red-700 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                                >
                                    Cerrar recepción
                                </button>
                            ) : (
                                <div className="ml-4 shrink-0 flex items-center gap-3 bg-red-50 border border-red-200 rounded-lg px-4 py-2">
                                    <p className="text-xs text-red-700 font-medium">¿Confirmar cierre? Esta acción no se puede deshacer.</p>
                                    <button
                                        onClick={ejecutarCierre}
                                        className="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors"
                                    >
                                        Confirmar
                                    </button>
                                    <button
                                        onClick={() => setConfirmCierre(false)}
                                        className="text-xs text-gray-500 hover:text-gray-700"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            )}
                        </div>
                    )
                )}

                {/* ── Cierre del proceso (acta) ───────────────── */}
                {periodo && (
                    actaCierre ? (
                        <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-5 mb-6 flex items-center justify-between">
                            <div>
                                <p className="text-sm font-semibold text-indigo-800">Proceso cerrado formalmente</p>
                                <p className="text-xs text-indigo-600 mt-0.5">
                                    Acta de cierre generada el {actaCierre.fecha}.
                                </p>
                            </div>
                            <a
                                href={actaCierre.url}
                                target="_blank"
                                rel="noreferrer"
                                className="ml-4 shrink-0 flex items-center gap-1.5 text-xs font-medium text-indigo-700 border border-indigo-300 bg-white hover:bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors"
                            >
                                <PrintIcon /> Ver acta de cierre
                            </a>
                        </div>
                    ) : (
                        <div className="bg-white border border-gray-200 rounded-xl p-5 mb-6 space-y-4">
                            <div>
                                <p className="text-sm font-semibold text-gray-800">Acta de cierre del proceso</p>
                                <p className="text-xs text-gray-500 mt-1">
                                    Paso final de la facultad: después de que la CCA evaluó a todos,
                                    se resolvieron las apelaciones y la CCA re-evaluó las que correspondan,
                                    el secretario cierra el proceso y se genera el acta PDF.
                                </p>
                            </div>

                            {requisitosCierre?.length > 0 && (
                                <ul className="space-y-1.5">
                                    {requisitosCierre.map((req, i) => (
                                        <li key={i} className="flex items-center gap-2 text-xs">
                                            <span className={req.ok ? 'text-green-600 font-bold' : 'text-gray-300'}>
                                                {req.ok ? '✓' : '○'}
                                            </span>
                                            <span className={req.ok ? 'text-green-800' : 'text-gray-600'}>
                                                {req.label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="flex items-center justify-between pt-1 border-t border-gray-100">
                                <div>
                                    {puedesCerrarProceso ? (
                                        <p className="text-xs text-green-700 font-medium">
                                            Requisitos cumplidos — puede generar el acta de cierre.
                                        </p>
                                    ) : (
                                        <p className="text-xs text-amber-600">{motivoNoPuede ?? 'Aún no se cumplen todos los requisitos.'}</p>
                                    )}
                                </div>
                                {puedesCerrarProceso && (
                                    !confirmCierreProceso ? (
                                        <button
                                            onClick={() => setConfirmCierreProceso(true)}
                                            className="ml-4 shrink-0 px-4 py-2 border border-indigo-300 text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-50 transition-colors"
                                        >
                                            Cerrar proceso y generar acta
                                        </button>
                                    ) : (
                                        <div className="ml-4 shrink-0 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2">
                                            <p className="text-xs text-indigo-700 font-medium">¿Confirmar? Se generará el acta de cierre PDF.</p>
                                            <button
                                                onClick={ejecutarCierreProceso}
                                                className="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                                            >
                                                Confirmar
                                            </button>
                                            <button
                                                onClick={() => setConfirmCierreProceso(false)}
                                                className="text-xs text-gray-500 hover:text-gray-700"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    )
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <StatCard label="Total"       value={total}      active={!filtroEstado}                    onClick={() => setFiltroEstado('')} />
                    <StatCard label="Pendientes"  value={pendientes} active={filtroEstado === 'pendiente'}     onClick={() => setFiltroEstado(f => f === 'pendiente'     ? '' : 'pendiente')} />
                    <StatCard label="En revisión" value={enRevision} active={filtroEstado === 'en_carga'}      onClick={() => setFiltroEstado(f => f === 'en_carga'      ? '' : 'en_carga')} />
                    <StatCard label="Completos"   value={completos}  active={filtroEstado === 'carga_cerrada'} onClick={() => setFiltroEstado(f => f === 'carga_cerrada' ? '' : 'carga_cerrada')} />
                </div>

                {/* Filtros */}
                <div className="flex flex-col sm:flex-row gap-3 mb-5">
                    <div className="relative flex-1">
                        <SearchIcon />
                        <input
                            type="text"
                            placeholder="Buscar por nombre o RUT..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                        />
                    </div>
                    <select
                        value={filtroEstado}
                        onChange={e => setFiltroEstado(e.target.value)}
                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                    >
                        {FILTROS_ESTADO.map(f => (
                            <option key={f.value} value={f.value}>{f.label}</option>
                        ))}
                    </select>
                </div>

                {/* Tabla */}
                {total === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p className="text-gray-400 text-sm">No hay académicos en la nómina de tu facultad para este período.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Académico</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">RUT</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Estado</th>
                                    <th className="text-left px-5 py-3 font-semibold text-gray-600">Caso especial</th>
                                    <th className="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {filtrados.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-5 py-8 text-center text-sm text-gray-400">
                                            No hay expedientes que coincidan con los filtros aplicados.
                                        </td>
                                    </tr>
                                ) : filtrados.map(e => {
                                    const badge = ESTADOS[e.estado] ?? { label: e.estado, cls: 'bg-gray-100 text-gray-600' };
                                    return (
                                        <tr key={e.id} className="hover:bg-gray-50">
                                            <td className="px-5 py-3 font-medium text-gray-900">{e.academico.name}</td>
                                            <td className="px-5 py-3 text-gray-500">{e.academico.rut ?? '—'}</td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-block text-xs font-medium px-2.5 py-0.5 rounded-full ${badge.cls}`}>
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3">
                                                {e.tiene_licencia_activa || e.licencia_pendiente || e.estado_especial ? (
                                                    <div>
                                                        <span className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full border ${
                                                            e.tiene_licencia_activa
                                                                ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                                : 'bg-yellow-50 text-yellow-800 border-yellow-200'
                                                        }`}>
                                                            {e.estado_especial ?? 'Caso especial'}
                                                        </span>
                                                        {e.observacion_licencia && (
                                                            <p className="text-xs text-gray-400 mt-0.5 max-w-xs truncate" title={e.observacion_licencia}>
                                                                {e.observacion_licencia}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : e.con_licencia ? (
                                                    <span className="inline-block text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">
                                                        Caso especial
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-300">—</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <Link
                                                    href={`/secretario/expedientes/${e.id}`}
                                                    className="text-xs font-medium text-[#0096D6] hover:underline"
                                                >
                                                    Ver detalle
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        {filtrados.length > 0 && (
                            <div className="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
                                Mostrando {filtrados.length} de {total} expedientes
                            </div>
                        )}
                    </div>
                )}
            </AppLayout>
        </>
    );
}

function StatCard({ label, value, active, onClick }) {
    return (
        <button type="button" onClick={onClick}
            className={`rounded-xl border p-4 text-left transition-colors w-full ${
                active ? 'border-[#1B2D6B] bg-[#1B2D6B]/5' : 'border-gray-200 bg-white hover:border-gray-300'
            }`}
        >
            <p className="text-xs text-gray-500">{label}</p>
            <p className={`text-2xl font-bold mt-0.5 ${active ? 'text-[#1B2D6B]' : 'text-gray-900'}`}>{value}</p>
        </button>
    );
}

function SearchIcon() {
    return (
        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    );
}

function RefreshIcon() {
    return (
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
    );
}

function LockIcon() {
    return (
        <svg className="w-6 h-6 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
    );
}

function PrintIcon() {
    return (
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.38-4.171l.36 4.171M6.34 18H5.25A2.25 2.25 0 013 15.75V9a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 9v6.75a2.25 2.25 0 01-2.25 2.25H17.66m-11.32 0l-.36-4.171M17.66 18l.36-4.171M6.34 18h11.32M9 9.75h6M9 12.75h6" />
        </svg>
    );
}
