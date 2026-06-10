import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

function Campo({ label, value }) {
    return (
        <div>
            <p className="text-xs text-gray-400 mb-0.5">{label}</p>
            <p className="text-sm text-gray-800 font-medium">{value || '—'}</p>
        </div>
    );
}

export default function NominaDetalle({ periodo, nomina, historial_calificaciones, historial_categorias }) {
    const vigenciaColor = nomina.nota_vigente_activa
        ? 'bg-green-50 border-green-200 text-green-800'
        : 'bg-gray-50 border-gray-200 text-gray-600';

    return (
        <>
            <Head title={`Detalle — ${nomina.nombre}`} />
            <AppLayout title="Detalle académico">

                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                    <span>/</span>
                    <Link href={route('analista.periodos.nominas.create', periodo.id)}
                        className="hover:text-gray-700">{periodo.nombre}</Link>
                    <span>/</span>
                    <span className="text-gray-700 font-medium">{nomina.nombre}</span>
                </div>

                <div className="space-y-6">

                    {/* ── Datos personales y organizacionales ── */}
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-sm font-semibold text-gray-700 mb-4">Datos del académico</h2>
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <Campo label="N° Personal"           value={nomina.numero_personal} />
                            <Campo label="Cédula de Identidad"   value={nomina.rut} />
                            <Campo label="Nombre"                value={nomina.nombre} />
                            <Campo label="Adscripción Académica" value={nomina.adscripcion_academica} />
                            <Campo label="Unidad Superior"       value={nomina.unidad_superior} />
                            <Campo label="Unidad"                value={nomina.unidad} />
                            <Campo label="Nombre de la Posición" value={nomina.nombre_posicion} />
                            <Campo label="Tipo de Trabajador"    value={nomina.tipo_trabajador} />
                            <Campo label="Fecha Inicio Contrato" value={nomina.fecha_inicio_contrato} />
                            <Campo label="Horas de Contrato"     value={nomina.horas_contrato} />
                            <Campo label="Categoría actual"      value={nomina.categoria
                                ? nomina.categoria.charAt(0).toUpperCase() + nomina.categoria.slice(1) : null} />
                            <Campo label="Fecha Categorización"  value={nomina.fecha_categorizacion} />
                        </div>
                    </div>

                    {/* ── Nota vigente ── */}
                    <div className={`rounded-xl border p-5 ${vigenciaColor}`}>
                        <h2 className="text-sm font-semibold mb-3">Nota vigente</h2>
                        {nomina.nota_vigente ? (
                            <div className="flex flex-wrap items-center gap-6">
                                <div>
                                    <p className="text-xs opacity-70 mb-0.5">Nota</p>
                                    <p className="text-2xl font-bold">{Number(nomina.nota_vigente).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-xs opacity-70 mb-0.5">Estado</p>
                                    <p className="text-sm font-medium">
                                        {nomina.nota_vigente_activa ? 'Vigente' : 'Vencida'}
                                    </p>
                                </div>
                                {nomina.vencimiento_nota && (
                                    <div>
                                        <p className="text-xs opacity-70 mb-0.5">Vencimiento</p>
                                        <p className="text-sm font-medium">{nomina.vencimiento_nota}</p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-xs opacity-70 mb-0.5">Vigencia según categoría</p>
                                    <p className="text-sm font-medium">
                                        {nomina.categoria === 'auxiliar' ? '1 año' : '2 años'}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm opacity-70">Sin nota registrada en el historial.</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                        {/* ── Historial de calificaciones ── */}
                        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h2 className="text-sm font-semibold text-gray-700">Historial de calificaciones</h2>
                            </div>
                            {historial_calificaciones.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-gray-400 text-center">Sin historial importado.</p>
                            ) : (
                                <table className="w-full text-xs">
                                    <thead className="bg-gray-50 border-b border-gray-100">
                                        <tr>
                                            {['Año', 'Nota', 'Concepto', 'Observación', 'Proceso'].map(h => (
                                                <th key={h} className="px-3 py-2 text-left font-medium text-gray-500">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {historial_calificaciones.map(h => (
                                            <tr key={h.anio} className="hover:bg-gray-50">
                                                <td className="px-3 py-2 font-semibold text-gray-700">{h.anio}</td>
                                                <td className="px-3 py-2 text-gray-800">
                                                    {h.nota ? Number(h.nota).toFixed(2) : '—'}
                                                </td>
                                                <td className="px-3 py-2 text-gray-600">{h.concepto || '—'}</td>
                                                <td className="px-3 py-2 text-gray-500 max-w-[140px] truncate" title={h.observacion}>
                                                    {h.observacion || '—'}
                                                </td>
                                                <td className="px-3 py-2 text-gray-500 max-w-[120px] truncate" title={h.proceso}>
                                                    {h.proceso || '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>

                        {/* ── Historial de categorías ── */}
                        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h2 className="text-sm font-semibold text-gray-700">Historial de categorías</h2>
                            </div>
                            {historial_categorias.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-gray-400 text-center">Sin historial importado.</p>
                            ) : (
                                <table className="w-full text-xs">
                                    <thead className="bg-gray-50 border-b border-gray-100">
                                        <tr>
                                            {['Año', 'Categoría', 'Fecha Categorización'].map(h => (
                                                <th key={h} className="px-3 py-2 text-left font-medium text-gray-500">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {historial_categorias.map(h => (
                                            <tr key={h.anio} className="hover:bg-gray-50">
                                                <td className="px-3 py-2 font-semibold text-gray-700">{h.anio}</td>
                                                <td className="px-3 py-2 text-gray-800">
                                                    {h.categoria
                                                        ? h.categoria.charAt(0).toUpperCase() + h.categoria.slice(1)
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2 text-gray-600">
                                                    {h.fecha_categorizacion || '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
