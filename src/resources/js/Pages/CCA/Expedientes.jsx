import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_BADGE = {
    carga_cerrada: 'bg-blue-100 text-blue-700',
    en_evaluacion: 'bg-purple-100 text-purple-700',
    evaluado:      'bg-green-100 text-green-700',
};

export default function Expedientes({
    periodo, expedientes, evaluacionHabilitada, fechaAperturaEval,
    comisionConfirmada, esIntegranteComision,
}) {
    const { flash } = usePage().props;

    const [busqueda,      setBusqueda]      = useState('');
    const [filtroEval,    setFiltroEval]    = useState('todos');
    const [filtroCategoria, setFiltroCategoria] = useState('todas');

    const categorias = useMemo(() => {
        const set = new Set(expedientes.map(e => e.categoria).filter(Boolean));
        return [...set].sort();
    }, [expedientes]);

    const filtrados = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        return expedientes.filter(exp => {
            if (q) {
                const nombre = exp.academico.name.toLowerCase();
                const rut    = exp.academico.rut?.toLowerCase() ?? '';
                if (!nombre.includes(q) && !rut.includes(q)) return false;
            }
            if (filtroEval === 'pendiente'  && exp.yo_evaluado)  return false;
            if (filtroEval === 'registrada' && !exp.yo_evaluado) return false;
            if (filtroCategoria !== 'todas' && exp.categoria !== filtroCategoria) return false;
            return true;
        });
    }, [expedientes, busqueda, filtroEval, filtroCategoria]);

    return (
        <>
            <Head title="Expedientes CCA" />
            <AppLayout title="Expedientes para Evaluación">
                {periodo && (
                    <p className="text-sm text-gray-500 -mt-4 mb-6">
                        Período: <span className="font-medium text-gray-700">{periodo.nombre} {periodo.anio}</span>
                    </p>
                )}

                {flash?.error && (
                    <div className="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {!esIntegranteComision && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-6 text-center mb-5">
                        <p className="text-red-800 font-semibold text-sm mb-1">
                            No está designado en la comisión evaluadora
                        </p>
                        <p className="text-red-700 text-sm">
                            El analista CCDA debe confirmar su integración en la comisión del período activo.
                        </p>
                    </div>
                )}

                {esIntegranteComision && !comisionConfirmada && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center mb-5">
                        <p className="text-amber-800 font-semibold text-sm mb-1">
                            Comisión evaluadora pendiente de confirmación
                        </p>
                        <p className="text-amber-700 text-sm">
                            El analista CCDA aún no confirma la comisión de su facultad para este período.
                        </p>
                    </div>
                )}

                {esIntegranteComision && comisionConfirmada && !evaluacionHabilitada && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                        <p className="text-amber-800 font-semibold text-sm mb-1">
                            Validación del secretario aún vigente
                        </p>
                        <p className="text-amber-700 text-sm">
                            La evaluación se habilitará cuando cierre la etapa de validación del secretario
                            {fechaAperturaEval && (
                                <span className="font-semibold"> ({fechaAperturaEval})</span>
                            )}.
                        </p>
                    </div>
                )}

                {evaluacionHabilitada && expedientes.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">
                            No hay expedientes validados por el secretario disponibles para evaluación.
                        </p>
                    </div>
                )}

                {evaluacionHabilitada && expedientes.length > 0 && (
                    <>
                        {/* Barra de búsqueda y filtros */}
                        <div className="flex flex-wrap items-center gap-3 mb-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <SearchIcon />
                                <input
                                    type="text"
                                    value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    placeholder="Buscar por nombre o RUT..."
                                    className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B]"
                                />
                            </div>

                            <select
                                value={filtroEval}
                                onChange={e => setFiltroEval(e.target.value)}
                                className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B] bg-white"
                            >
                                <option value="todos">Mi evaluación: Todas</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="registrada">Registrada</option>
                            </select>

                            {categorias.length > 1 && (
                                <select
                                    value={filtroCategoria}
                                    onChange={e => setFiltroCategoria(e.target.value)}
                                    className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30 focus:border-[#1B2D6B] bg-white"
                                >
                                    <option value="todas">Categoría: Todas</option>
                                    {categorias.map(c => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                            )}

                            {(busqueda || filtroEval !== 'todos' || filtroCategoria !== 'todas') && (
                                <button
                                    onClick={() => { setBusqueda(''); setFiltroEval('todos'); setFiltroCategoria('todas'); }}
                                    className="text-xs text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    Limpiar filtros
                                </button>
                            )}
                        </div>

                        {/* Tabla */}
                        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            {filtrados.length === 0 ? (
                                <div className="py-12 text-center text-sm text-gray-400 italic">
                                    No hay resultados para los filtros aplicados.
                                </div>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                                            <th className="text-left px-5 py-3 font-medium">Académico</th>
                                            <th className="text-left px-5 py-3 font-medium">Facultad</th>
                                            <th className="text-left px-5 py-3 font-medium">Categoría</th>
                                            <th className="text-left px-5 py-3 font-medium">Estado</th>
                                            <th className="text-left px-5 py-3 font-medium">Mi evaluación</th>
                                            <th className="px-5 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {filtrados.map(exp => (
                                            <tr key={exp.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-5 py-3.5">
                                                    <p className="font-medium text-gray-900">{exp.academico.name}</p>
                                                    <p className="text-xs text-gray-400">{exp.academico.rut}</p>
                                                </td>
                                                <td className="px-5 py-3.5 text-gray-600">
                                                    {exp.facultad ?? '—'}
                                                </td>
                                                <td className="px-5 py-3.5 text-gray-600">
                                                    {exp.categoria ?? '—'}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${ESTADO_BADGE[exp.estado] ?? 'bg-gray-100 text-gray-600'}`}>
                                                        {exp.estado_label}
                                                    </span>
                                                    {exp.con_licencia && (
                                                        <span className="ml-1.5 text-xs text-amber-600">· Licencia</span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    {exp.yo_evaluado ? (
                                                        <span className="text-xs text-green-700 font-medium">✓ Registrada</span>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">Pendiente</span>
                                                    )}
                                                    {exp.concepto_final && (
                                                        <p className="text-xs text-gray-500 mt-0.5">
                                                            Final: {exp.concepto_final} ({exp.nota_final})
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <Link
                                                        href={`/cca/expedientes/${exp.id}`}
                                                        className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-[#1B2D6B] text-white hover:bg-[#152558] transition-colors"
                                                    >
                                                        {exp.estado === 'evaluado' && exp.yo_evaluado ? 'Ver' : 'Evaluar'}
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>

                        {filtrados.length > 0 && filtrados.length < expedientes.length && (
                            <p className="text-xs text-gray-400 mt-2 text-right">
                                Mostrando {filtrados.length} de {expedientes.length} expedientes
                            </p>
                        )}
                    </>
                )}
            </AppLayout>
        </>
    );
}

function SearchIcon() {
    return (
        <svg className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    );
}
