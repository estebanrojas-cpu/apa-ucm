import { Head, Link, router, usePage } from '@inertiajs/react';
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

export default function Expedientes({ periodo, expedientes }) {
    const { flash, auth } = usePage().props;
    const facultad = auth.user.facultad?.nombre ?? '—';

    const [search,        setSearch]        = useState('');
    const [filtroEstado,  setFiltroEstado]  = useState('');

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

    // Contadores para las stat cards
    const total      = expedientes.length;
    const pendientes = expedientes.filter(e => e.estado === 'pendiente').length;
    const enRevision = expedientes.filter(e => e.estado === 'en_carga').length;
    const completos  = expedientes.filter(e => e.estado === 'carga_cerrada').length;

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

                {/* Cabecera: período + facultad */}
                <div className="flex items-start justify-between -mt-4 mb-6">
                    <div>
                        <p className="text-sm text-gray-500">
                            Período: <span className="font-medium text-gray-700">{periodo.nombre}</span>
                        </p>
                        <p className="text-sm text-gray-500">
                            Facultad: <span className="font-medium text-gray-700">{facultad}</span>
                        </p>
                    </div>
                    <button
                        onClick={refresh}
                        className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors"
                        title="Actualizar datos"
                    >
                        <RefreshIcon />
                        Actualizar
                    </button>
                </div>

                {flash?.success && (
                    <div className="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <StatCard label="Total"       value={total}      active={!filtroEstado} onClick={() => setFiltroEstado('')} />
                    <StatCard label="Pendientes"  value={pendientes} active={filtroEstado === 'pendiente'}     onClick={() => setFiltroEstado(f => f === 'pendiente'    ? '' : 'pendiente')} />
                    <StatCard label="En revisión" value={enRevision} active={filtroEstado === 'en_carga'}      onClick={() => setFiltroEstado(f => f === 'en_carga'     ? '' : 'en_carga')} />
                    <StatCard label="Completos"   value={completos}  active={filtroEstado === 'carga_cerrada'} onClick={() => setFiltroEstado(f => f === 'carga_cerrada'? '' : 'carga_cerrada')} />
                </div>

                {/* Barra de filtros */}
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

                {/* Tabla de expedientes */}
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
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {filtrados.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-8 text-center text-sm text-gray-400">
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
                                                {e.con_licencia ? (
                                                    <div>
                                                        <span className="inline-block text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">
                                                            Caso especial
                                                        </span>
                                                        {e.observacion_licencia && (
                                                            <p className="text-xs text-gray-400 mt-0.5">{e.observacion_licencia}</p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-300">—</span>
                                                )}
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
        <button
            type="button"
            onClick={onClick}
            className={`rounded-xl border p-4 text-left transition-colors w-full ${
                active
                    ? 'border-[#1B2D6B] bg-[#1B2D6B]/5'
                    : 'border-gray-200 bg-white hover:border-gray-300'
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
