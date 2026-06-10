import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useCallback, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const COLUMNAS = [
    { key: 'rut',             label: 'RUT',            type: 'text' },
    { key: 'nombre',          label: 'Nombre',         type: 'text' },
    { key: 'facultad_nombre', label: 'Facultad',       type: 'text' },
    { key: 'categoria',       label: 'Categoría',      type: 'select', options: ['auxiliar', 'adjunto', 'titular'] },
    { key: 'horas_contrato',  label: 'Horas contrato', type: 'number' },
];

function filaVacia() {
    return {
        rut: '', nombre: '', facultad_nombre: '', facultad_id: '',
        categoria: 'adjunto', horas_contrato: '',
        user_id: null, datos_adicionales: {}, compromiso_apa: null,
    };
}

export default function NominaExcel({ periodo, facultades, filas: filasIniciales }) {
    const { flash } = usePage().props;
    const importPreview = flash?.importPreview;
    const [modo, setModo] = useState('grilla');
    const [filas, setFilas] = useState(importPreview?.length ? importPreview : (filasIniciales.length ? filasIniciales : [filaVacia()]));
    const [columnasExtra, setColumnasExtra] = useState([]);
    const [facultadFiltro, setFacultadFiltro] = useState('');
    const gridRef = useRef(null);

    const form = useForm({ filas: [] });
    const importForm = useForm({ archivo: null });

    const filasFiltradas = useMemo(() => {
        if (!facultadFiltro) return filas;
        const fac = facultades.find(f => f.id === facultadFiltro);
        return filas.filter(f => f.facultad_id === facultadFiltro || f.facultad_nombre === fac?.nombre);
    }, [filas, facultadFiltro, facultades]);

    const actualizarCelda = useCallback((idx, key, value) => {
        setFilas(prev => prev.map((f, i) => i === idx ? { ...f, [key]: value } : f));
    }, []);

    function agregarFila() { setFilas(prev => [...prev, filaVacia()]); }
    function eliminarFila(idx) { setFilas(prev => prev.filter((_, i) => i !== idx)); }

    function agregarColumna() {
        const nombre = prompt('Nombre de la columna personalizada:');
        if (nombre?.trim()) setColumnasExtra(prev => [...prev, nombre.trim()]);
    }

    function guardar() {
        form.setData('filas', filas);
        form.post(`/analista/periodos/${periodo.id}/nominas`, { preserveScroll: true });
    }

    function subirExcel(e) {
        e.preventDefault();
        importForm.post(`/analista/periodos/${periodo.id}/nominas/importar`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    function handlePaste(e) {
        const text = e.clipboardData.getData('text');
        if (!text.includes('\t')) return;
        e.preventDefault();
        const lineas = text.trim().split('\n').map(l => l.split('\t'));
        const nuevas = lineas.map(cols => ({
            ...filaVacia(),
            rut: cols[0]?.trim() ?? '',
            nombre: cols[1]?.trim() ?? '',
            facultad_nombre: cols[2]?.trim() ?? '',
            categoria: (cols[3]?.trim() ?? 'adjunto').toLowerCase(),
            horas_contrato: cols[4]?.trim() ?? '',
        }));
        setFilas(prev => [...prev.filter(f => f.rut || f.nombre), ...nuevas]);
    }

    const exportUrl = facultadFiltro
        ? `/analista/periodos/${periodo.id}/nominas/exportar?facultad_id=${facultadFiltro}`
        : `/analista/periodos/${periodo.id}/nominas/exportar`;

    return (
        <>
            <Head title={`Nómina — ${periodo.nombre}`} />
            <AppLayout title="Nómina del Período">

                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-4">
                    <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                    <span>/</span>
                    <span className="text-gray-700 font-medium">{periodo.nombre}</span>
                    <span>/</span>
                    <span>Nómina</span>
                </div>

                <p className="text-xs text-gray-400 mb-4">
                    Los % APA los declara cada académico al ingresar al sistema. Esta columna es solo informativa.
                </p>

                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {form.errors.filas && (
                    <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {form.errors.filas}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3 mb-5">
                    <div className="flex rounded-lg border border-gray-200 overflow-hidden">
                        {['grilla', 'importar'].map(m => (
                            <button key={m} type="button" onClick={() => setModo(m)}
                                className={`px-4 py-2 text-sm font-medium ${modo === m ? 'bg-[#1B2D6B] text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}>
                                {m === 'grilla' ? 'Grilla editable' : 'Subir Excel'}
                            </button>
                        ))}
                    </div>
                    <a href="/analista/nominas/plantilla" className="text-sm text-[#0096D6] hover:underline">
                        Descargar plantilla
                    </a>
                    <a href={exportUrl} className="text-sm text-[#0096D6] hover:underline">
                        Exportar nómina
                    </a>
                    <select value={facultadFiltro} onChange={e => setFacultadFiltro(e.target.value)}
                        className="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                        <option value="">Todas las facultades</option>
                        {facultades.map(f => (
                            <option key={f.id} value={f.id}>{f.nombre}</option>
                        ))}
                    </select>
                </div>

                {modo === 'importar' ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 max-w-lg">
                        <h2 className="text-sm font-semibold text-gray-700 mb-2">Subir archivo Excel</h2>
                        <p className="text-xs text-gray-400 mb-4">
                            Suba un archivo .xlsx o .csv con la nómina. Los datos se cargarán en la grilla para revisar antes de guardar.
                        </p>
                        <form onSubmit={subirExcel} className="space-y-4">
                            <input type="file" accept=".xlsx,.xls,.csv"
                                onChange={e => importForm.setData('archivo', e.target.files[0])}
                                className="w-full text-sm" />
                            <button type="submit" disabled={importForm.processing || !importForm.data.archivo}
                                className="bg-[#1B2D6B] text-white text-sm font-medium px-5 py-2 rounded-lg disabled:opacity-50">
                                {importForm.processing ? 'Procesando...' : 'Cargar en grilla'}
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <p className="text-sm text-gray-600">
                                {filasFiltradas.length} fila(s) · Pegue desde Excel con Ctrl+V
                            </p>
                            <div className="flex gap-2">
                                <button type="button" onClick={agregarColumna}
                                    className="text-xs text-[#0096D6] hover:underline">+ Columna personalizada</button>
                                <button type="button" onClick={agregarFila}
                                    className="text-xs font-medium bg-gray-100 px-3 py-1 rounded hover:bg-gray-200">+ Fila</button>
                            </div>
                        </div>

                        <div ref={gridRef} className="overflow-x-auto" onPaste={handlePaste}>
                            <table className="w-full text-sm min-w-[700px]">
                                <thead className="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th className="px-2 py-2 w-8"></th>
                                        {COLUMNAS.map(c => (
                                            <th key={c.key} className="px-2 py-2 text-left whitespace-nowrap">{c.label}</th>
                                        ))}
                                        <th className="px-2 py-2 text-left">Compromiso APA</th>
                                        {columnasExtra.map(c => (
                                            <th key={c} className="px-2 py-2 text-left">{c}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {filasFiltradas.map((fila) => {
                                        const realIdx = filas.indexOf(fila);
                                        const tieneError = fila.errores?.length > 0;

                                        return (
                                            <tr key={realIdx} className={tieneError ? 'bg-red-50/50' : 'hover:bg-gray-50/50'}>
                                                <td className="px-2 py-1">
                                                    <button type="button" onClick={() => eliminarFila(realIdx)}
                                                        className="text-gray-300 hover:text-red-500 text-xs">✕</button>
                                                </td>
                                                {COLUMNAS.map(col => (
                                                    <td key={col.key} className="px-1 py-1">
                                                        {col.type === 'select' ? (
                                                            <select value={fila[col.key] ?? ''}
                                                                onChange={e => actualizarCelda(realIdx, col.key, e.target.value)}
                                                                className="w-full border border-gray-200 rounded px-2 py-1 text-xs bg-white">
                                                                {col.options.map(o => (
                                                                    <option key={o} value={o}>{o}</option>
                                                                ))}
                                                            </select>
                                                        ) : (
                                                            <input type={col.type} value={fila[col.key] ?? ''}
                                                                onChange={e => actualizarCelda(realIdx, col.key, e.target.value)}
                                                                className="w-full border border-gray-200 rounded px-2 py-1 text-xs" />
                                                        )}
                                                    </td>
                                                ))}
                                                <td className="px-2 py-1">
                                                    {fila.id ? (
                                                        <span className={`text-xs px-2 py-0.5 rounded-full ${
                                                            fila.compromiso_apa?.confirmado
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-amber-100 text-amber-800'
                                                        }`}>
                                                            {fila.compromiso_apa?.confirmado ? 'Confirmado' : 'Pendiente'}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">—</span>
                                                    )}
                                                </td>
                                                {columnasExtra.map(c => (
                                                    <td key={c} className="px-1 py-1">
                                                        <input type="text"
                                                            value={fila.datos_adicionales?.[c] ?? ''}
                                                            onChange={e => actualizarCelda(realIdx, 'datos_adicionales', {
                                                                ...fila.datos_adicionales, [c]: e.target.value,
                                                            })}
                                                            className="w-full border border-gray-200 rounded px-2 py-1 text-xs" />
                                                    </td>
                                                ))}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="px-4 py-3 border-t border-gray-100 flex items-center gap-4">
                            <button type="button" onClick={guardar} disabled={form.processing}
                                className="bg-[#1B2D6B] text-white text-sm font-medium px-6 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-50">
                                {form.processing ? 'Guardando...' : 'Guardar nómina'}
                            </button>
                            <Link href="/analista/periodos" className="text-sm text-gray-500 hover:text-gray-700">Volver</Link>
                        </div>
                    </div>
                )}

            </AppLayout>
        </>
    );
}
