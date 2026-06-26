import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const [y, m, d] = dateStr.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
}

// ── Modal agregar académico individual ────────────────────────────────────────
function ModalAgregarAcademico({ periodo, facultades, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        rut: '', nombre: '', facultad_id: '', categoria: '',
        tipo_trabajador: '', unidad_superior: '', unidad: '', horas_contrato: '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/analista/periodos/${periodo.id}/nominas/agregar`, { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-800">Agregar académico</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                </div>
                <p className="text-xs text-gray-500">Solo se agrega a la nómina. La cuenta se crea cuando comunica el acceso por correo.</p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        {[
                            { label: 'RUT', key: 'rut', placeholder: '12.345.678-9', req: true },
                            { label: 'Nombre completo', key: 'nombre', placeholder: 'Juan Pérez Muñoz', req: true },
                            { label: 'Tipo trabajador', key: 'tipo_trabajador', placeholder: 'académico / directivo' },
                            { label: 'Unidad Superior', key: 'unidad_superior', placeholder: 'Facultad de...' },
                            { label: 'Unidad', key: 'unidad', placeholder: 'Departamento de...' },
                            { label: 'Horas contrato', key: 'horas_contrato', placeholder: '44', type: 'number' },
                        ].map(({ label, key, placeholder, req, type }) => (
                            <div key={key}>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    {label}{req && <span className="text-red-500 ml-0.5">*</span>}
                                </label>
                                <input
                                    type={type || 'text'}
                                    value={data[key]}
                                    onChange={e => setData(key, e.target.value)}
                                    placeholder={placeholder}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                                />
                                {errors[key] && <p className="mt-1 text-xs text-red-600">{errors[key]}</p>}
                            </div>
                        ))}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Facultad (opcional)</label>
                        <select value={data.facultad_id} onChange={e => setData('facultad_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30">
                            <option value="">— Seleccionar —</option>
                            {facultades.map(f => <option key={f.id} value={f.id}>{f.nombre}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Categoría académica</label>
                        <select value={data.categoria} onChange={e => setData('categoria', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30">
                            <option value="">— Seleccionar —</option>
                            {['auxiliar', 'adjunto', 'titular', 'jerarquizado'].map(c => (
                                <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>
                            ))}
                        </select>
                        {errors.categoria && <p className="mt-1 text-xs text-red-600">{errors.categoria}</p>}
                    </div>
                    <div className="flex gap-3 justify-end pt-2">
                        <button type="button" onClick={onClose} className="text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
                        <button type="submit" disabled={processing}
                            className="bg-[#1B2D6B] text-white text-sm px-4 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-60">
                            {processing ? 'Agregando...' : 'Agregar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Modal editar académico ─────────────────────────────────────────────────────
function ModalEditarAcademico({ periodo, nomina, columnasAdicionales, onClose }) {
    const initAdicionales = Object.fromEntries(
        columnasAdicionales.map(col => [col, nomina.datos_adicionales?.[col] ?? ''])
    );

    const { data, setData, patch, processing, errors } = useForm({
        rut:                    nomina.rut                    ?? '',
        nombre:                 nomina.nombre                 ?? '',
        numero_personal:        nomina.numero_personal        ?? '',
        adscripcion_academica:  nomina.adscripcion_academica  ?? '',
        unidad_superior:        nomina.unidad_superior        ?? '',
        unidad:                 nomina.unidad                 ?? '',
        nombre_posicion:        nomina.nombre_posicion        ?? '',
        tipo_trabajador:        nomina.tipo_trabajador        ?? '',
        fecha_inicio_contrato:  nomina.fecha_inicio_contrato  ?? '',
        horas_contrato:         nomina.horas_contrato         ?? '',
        categoria:              nomina.categoria              ?? '',
        fecha_categorizacion:   nomina.fecha_categorizacion   ?? '',
        datos_adicionales:      initAdicionales,
    });

    function submit(e) {
        e.preventDefault();
        patch(`/analista/periodos/${periodo.id}/nominas/${nomina.id}`, { onSuccess: onClose });
    }

    const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30';

    const camposFijos = [
        { label: 'RUT',                    key: 'rut',                    req: true },
        { label: 'Nombre',                 key: 'nombre',                 req: true },
        { label: 'N° Personal',            key: 'numero_personal' },
        { label: 'Adscripción Académica',  key: 'adscripcion_academica' },
        { label: 'Unidad Superior',        key: 'unidad_superior' },
        { label: 'Unidad',                 key: 'unidad' },
        { label: 'Nombre de la Posición',  key: 'nombre_posicion' },
        { label: 'Tipo de Trabajador',     key: 'tipo_trabajador' },
        { label: 'Horas de Contrato',      key: 'horas_contrato',         type: 'number' },
    ];

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-lg w-full max-w-2xl flex flex-col max-h-[90vh]">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <h3 className="text-sm font-semibold text-gray-800">Editar — {nomina.nombre || nomina.rut}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                </div>

                <form onSubmit={submit} className="overflow-y-auto px-6 py-4 space-y-5">
                    {/* Campos fijos */}
                    <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Datos institucionales</p>
                        <div className="grid grid-cols-2 gap-3">
                            {camposFijos.map(({ label, key, req, type }) => (
                                <div key={key}>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        {label}{req && <span className="text-red-500 ml-0.5">*</span>}
                                    </label>
                                    <input
                                        type={type || 'text'}
                                        value={data[key]}
                                        onChange={e => setData(key, e.target.value)}
                                        className={inputCls}
                                    />
                                    {errors[key] && <p className="mt-1 text-xs text-red-600">{errors[key]}</p>}
                                </div>
                            ))}
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Fecha Inicio Contrato</label>
                                <input type="date" value={data.fecha_inicio_contrato}
                                    onChange={e => setData('fecha_inicio_contrato', e.target.value)}
                                    className={inputCls} />
                            </div>
                        </div>
                    </div>

                    {/* Categoría */}
                    <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Categoría</p>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Categoría académica</label>
                                <select value={data.categoria} onChange={e => setData('categoria', e.target.value)}
                                    className={inputCls}>
                                    <option value="">— Sin categoría —</option>
                                    {['auxiliar', 'adjunto', 'titular', 'jerarquizado'].map(c => (
                                        <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>
                                    ))}
                                </select>
                                {errors.categoria && <p className="mt-1 text-xs text-red-600">{errors.categoria}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Fecha Categorización</label>
                                <input type="date" value={data.fecha_categorizacion}
                                    onChange={e => setData('fecha_categorizacion', e.target.value)}
                                    className={inputCls} />
                            </div>
                        </div>
                    </div>

                    {/* Columnas adicionales */}
                    {columnasAdicionales.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Columnas personalizadas</p>
                            <div className="grid grid-cols-2 gap-3">
                                {columnasAdicionales.map(col => (
                                    <div key={col}>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">{col}</label>
                                        <input
                                            type="text"
                                            value={data.datos_adicionales[col] ?? ''}
                                            onChange={e => setData('datos_adicionales', {
                                                ...data.datos_adicionales,
                                                [col]: e.target.value,
                                            })}
                                            className={inputCls}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </form>

                <div className="flex gap-3 justify-end px-6 py-4 border-t border-gray-100 shrink-0">
                    <button type="button" onClick={onClose} className="text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
                    <button onClick={submit} disabled={processing}
                        className="bg-[#1B2D6B] text-white text-sm px-5 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-60">
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Campos SAPD para mapeo ────────────────────────────────────────────────────
const CAMPOS_SAPD = [
    { key: 'rut',                   label: 'Cédula de Identidad',         req: true },
    { key: 'nombre',                label: 'Nombre del Trabajador',       req: true },
    { key: 'numero_personal',       label: 'N° Personal' },
    { key: 'adscripcion_academica', label: 'Adscripción Académica' },
    { key: 'unidad_superior',       label: 'Unidad Superior' },
    { key: 'unidad',                label: 'Unidad' },
    { key: 'nombre_posicion',       label: 'Nombre de la Posición' },
    { key: 'tipo_trabajador',       label: 'Tipo de Trabajador' },
    { key: 'fecha_inicio_contrato', label: 'Fecha Inicio Contrato' },
    { key: 'horas_contrato',        label: 'Horas de Contrato' },
    { key: 'categoria',             label: 'Categoría (año más reciente)' },
    { key: 'fecha_categorizacion',  label: 'Fecha Categoría' },
];

// ── Panel importación Excel ───────────────────────────────────────────────────
function PanelExcel({ periodo, facultades }) {
    const { flash, errors } = usePage().props;
    const preview   = flash?.excel_preview;

    const fileRef = useRef();
    const [uploading, setUploading]   = useState(false);
    const [mapeo, setMapeo]           = useState({});
    const [tieneEncabezado, setTieneEncabezado] = useState(true);
    const [importando, setImportando] = useState(false);
    const [facultadId, setFacultadId] = useState('');

    useEffect(() => {
        if (preview?.auto_mapeo) {
            setMapeo(prev => ({ ...preview.auto_mapeo, ...prev }));
        }
    }, [preview?.path]);

    function subirArchivo(e) {
        e.preventDefault();
        if (!fileRef.current?.files[0]) return;
        setUploading(true);
        const fd = new FormData();
        fd.append('archivo', fileRef.current.files[0]);
        router.post(
            `/analista/periodos/${periodo.id}/nominas/preview-excel`,
            fd,
            { forceFormData: true, onFinish: () => setUploading(false) }
        );
    }

    function importar() {
        if (!preview?.path) return;
        setImportando(true);
        router.post(
            `/analista/periodos/${periodo.id}/nominas/importar-excel`,
            { path: preview.path, tiene_encabezado: tieneEncabezado, mapeo, facultad_id: facultadId || null },
            { onFinish: () => setImportando(false) }
        );
    }

    const columnaOpts = preview?.columnas.map((col, i) => ({ value: i, label: `[${i + 1}] ${col || '(sin nombre)'}` })) ?? [];
    const mapeoCompleto = mapeo.rut !== undefined && mapeo.nombre !== undefined;
    const sinMapear = Object.entries(preview?.sin_mapear ?? {});
    const autoCount = Object.keys(preview?.auto_mapeo ?? {}).length;

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <p className="text-sm font-semibold text-gray-700">Importar Excel SAPD</p>
            <p className="text-xs text-gray-400">Formatos: .xlsx, .xls, .csv — máx. 5 MB</p>

            <div className="flex gap-2 items-center">
                <select value={facultadId} onChange={e => setFacultadId(e.target.value)}
                    className="text-xs border border-gray-300 rounded px-2 py-1.5 text-gray-700 bg-white">
                    <option value="">Facultad (opcional)</option>
                    {(facultades ?? []).map(f => <option key={f.id} value={f.id}>{f.nombre}</option>)}
                </select>
            </div>
            <form onSubmit={subirArchivo} className="flex gap-2 items-center">
                <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv"
                    className="text-xs text-gray-600 file:mr-2 file:text-xs file:border file:border-gray-300 file:rounded file:px-2 file:py-1 file:bg-gray-50 file:cursor-pointer"
                />
                <button type="submit" disabled={uploading}
                    className="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg disabled:opacity-50 whitespace-nowrap">
                    {uploading ? 'Leyendo...' : 'Cargar'}
                </button>
            </form>

            {errors?.archivo && (
                <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
                    {errors.archivo}
                </div>
            )}

            {preview && (
                <div className="space-y-4 border-t border-gray-100 pt-4">
                    <div className="overflow-x-auto rounded border border-gray-200">
                        <table className="text-xs w-full">
                            <thead className="bg-gray-50">
                                <tr>
                                    {preview.columnas.map((c, i) => (
                                        <th key={i} className="px-3 py-1.5 text-left font-medium text-gray-600 whitespace-nowrap">
                                            [{i + 1}] {c || '—'}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.preview_rows.map((row, ri) => (
                                    <tr key={ri} className="border-t border-gray-100">
                                        {row.map((cell, ci) => (
                                            <td key={ci} className="px-3 py-1 text-gray-600 max-w-[100px] truncate">{cell}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <label className="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" checked={tieneEncabezado}
                            onChange={e => setTieneEncabezado(e.target.checked)}
                            className="rounded border-gray-300" />
                        La primera fila es encabezado
                    </label>

                    <div>
                        <p className="text-xs font-medium text-gray-600 mb-2">
                            Mapeo de columnas
                            {autoCount > 0 && (
                                <span className="ml-2 text-green-600 font-normal">
                                    — {autoCount} detectados automáticamente
                                </span>
                            )}
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            {CAMPOS_SAPD.map(campo => (
                                <div key={campo.key}>
                                    <label className="block text-xs text-gray-500 mb-0.5">
                                        {campo.label}
                                        {campo.req && <span className="text-red-500 ml-0.5">*</span>}
                                        {preview.auto_mapeo?.[campo.key] !== undefined && (
                                            <span className="ml-1 text-green-500 text-[10px]">✓</span>
                                        )}
                                    </label>
                                    <select
                                        value={mapeo[campo.key] ?? ''}
                                        onChange={e => setMapeo(prev => ({
                                            ...prev,
                                            [campo.key]: e.target.value === '' ? undefined : Number(e.target.value),
                                        }))}
                                        className="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#1B2D6B]/30"
                                    >
                                        <option value="">{campo.req ? '— Seleccionar —' : '— No importar —'}</option>
                                        {columnaOpts.map(o => (
                                            <option key={o.value} value={o.value}>{o.label}</option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>
                    </div>

                    {sinMapear.length > 0 && (
                        <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                            <p className="text-xs font-medium text-amber-700 mb-1">
                                {sinMapear.length} columna(s) no reconocida(s) — se importarán como columnas extra
                            </p>
                            <p className="text-xs text-amber-600">{sinMapear.map(([, l]) => l).join(', ')}</p>
                        </div>
                    )}

                    <button onClick={importar} disabled={!mapeoCompleto || importando}
                        className="w-full bg-[#1B2D6B] text-white text-sm py-2 rounded-lg hover:bg-[#152558] disabled:opacity-50 transition-colors">
                        {importando ? 'Importando...' : 'Importar nómina'}
                    </button>
                    {!mapeoCompleto && (
                        <p className="text-xs text-red-500">Debes asignar al menos RUT y Nombre.</p>
                    )}
                </div>
            )}
        </div>
    );
}

// ── Panel columnas personalizadas ─────────────────────────────────────────────
function PanelColumnas({ periodo, columnasAdicionales }) {
    const { data, setData, post, processing, reset, errors } = useForm({ nombre_columna: '' });
    const [eliminando, setEliminando] = useState(null);

    function submit(e) {
        e.preventDefault();
        if (!data.nombre_columna.trim()) return;
        post(`/analista/periodos/${periodo.id}/nominas/columna`, {
            onSuccess: () => reset(),
        });
    }

    function eliminar(col) {
        if (!confirm(`¿Eliminar la columna "${col}" de toda la nómina? Se perderán los datos cargados en esa columna.`)) return;
        setEliminando(col);
        router.delete(`/analista/periodos/${periodo.id}/nominas/columna`,
            { data: { nombre_columna: col }, onFinish: () => setEliminando(null) }
        );
    }

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <p className="text-sm font-semibold text-gray-700">Columnas personalizadas</p>
            <p className="text-xs text-gray-400">Agrega columnas extra a toda la nómina.</p>

            <form onSubmit={submit} className="flex gap-2">
                <input
                    type="text"
                    value={data.nombre_columna}
                    onChange={e => setData('nombre_columna', e.target.value)}
                    placeholder="Ej: Cargo interno"
                    maxLength={60}
                    className="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                />
                <button type="submit" disabled={processing || !data.nombre_columna.trim()}
                    className="text-xs bg-[#1B2D6B] text-white px-3 py-1.5 rounded-lg hover:bg-[#152558] disabled:opacity-50 whitespace-nowrap">
                    {processing ? '...' : '+ Agregar'}
                </button>
            </form>
            {errors?.nombre_columna && <p className="text-xs text-red-600">{errors.nombre_columna}</p>}

            {columnasAdicionales.length > 0 && (
                <div className="flex flex-wrap gap-1.5 pt-1">
                    {columnasAdicionales.map(col => (
                        <span key={col}
                            className="inline-flex items-center gap-1 text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-200 pl-2 pr-1 py-0.5 rounded-full">
                            {col}
                            <button
                                onClick={() => eliminar(col)}
                                disabled={eliminando === col}
                                title="Eliminar columna"
                                className="text-indigo-400 hover:text-red-500 disabled:opacity-40 leading-none font-bold text-xs">
                                {eliminando === col ? '…' : '×'}
                            </button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Grilla de nómina ──────────────────────────────────────────────────────────
function NominaGrid({ nominasEnPeriodo, periodo, columnasAdicionales, onEditar }) {
    if (nominasEnPeriodo.length === 0) {
        return (
            <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                <p className="text-gray-400 text-sm">
                    La nómina está vacía. Importa un Excel SAPD o agrega académicos manualmente.
                </p>
            </div>
        );
    }

    const fixedHeaders = ['N° Personal', 'RUT', 'Nombre', 'Unidad Superior', 'Unidad',
                          'Posición', 'Tipo', 'Fecha Ctto.', 'Horas', 'Categoría', 'Fecha Categ.', 'Acceso'];
    const allHeaders   = [...fixedHeaders, ...columnasAdicionales, 'Estado', ''];

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 py-3 border-b border-gray-100">
                <p className="text-sm font-semibold text-gray-700">
                    Nómina del período
                    <span className="ml-2 text-xs font-normal text-gray-400">({nominasEnPeriodo.length} registros)</span>
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead className="bg-gray-50 border-b border-gray-100">
                        <tr>
                            {allHeaders.map((h, i) => (
                                <th key={i} className={`px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap ${
                                    columnasAdicionales.includes(h) ? 'bg-indigo-50 text-indigo-600' : ''
                                }`}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {nominasEnPeriodo.map(n => {
                            return (
                                <tr key={n.id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 text-gray-400">{n.numero_personal || '—'}</td>
                                    <td className="px-3 py-2 font-mono text-gray-600">{n.rut || '—'}</td>
                                    <td className="px-3 py-2 font-medium text-gray-800 min-w-[160px]">
                                        <Link
                                            href={`/analista/periodos/${periodo.id}/nominas/${n.id}/detalle`}
                                            className="hover:text-[#1B2D6B] hover:underline">
                                            {n.nombre || '—'}
                                        </Link>
                                        {n.con_licencia && (
                                            <span className="ml-2 text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full">
                                                especial
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-gray-500 max-w-[130px] truncate" title={n.unidad_superior}>{n.unidad_superior || '—'}</td>
                                    <td className="px-3 py-2 text-gray-500 max-w-[100px] truncate" title={n.unidad}>{n.unidad || '—'}</td>
                                    <td className="px-3 py-2 text-gray-500 max-w-[100px] truncate" title={n.nombre_posicion}>{n.nombre_posicion || '—'}</td>
                                    <td className="px-3 py-2 text-gray-500 whitespace-nowrap">{n.tipo_trabajador || '—'}</td>
                                    <td className="px-3 py-2 text-gray-500 whitespace-nowrap">{n.fecha_inicio_contrato ? formatDate(n.fecha_inicio_contrato) : '—'}</td>
                                    <td className="px-3 py-2 text-gray-600 text-center">{n.horas_contrato ?? '—'}</td>
                                    <td className="px-3 py-2 text-gray-600 whitespace-nowrap">
                                        {n.categoria ? n.categoria.charAt(0).toUpperCase() + n.categoria.slice(1) : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-gray-500 whitespace-nowrap">{n.fecha_categorizacion ? formatDate(n.fecha_categorizacion) : '—'}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {n.tiene_cuenta ? (
                                            <span className="text-[10px] font-medium text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                                                Acceso enviado
                                            </span>
                                        ) : (
                                            <span className="text-[10px] font-medium text-gray-500 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full">
                                                Sin cuenta
                                            </span>
                                        )}
                                    </td>

                                    {/* Columnas personalizadas */}
                                    {columnasAdicionales.map(col => (
                                        <td key={col} className="px-3 py-2 text-gray-600 max-w-[120px] truncate bg-indigo-50/30"
                                            title={n.datos_adicionales?.[col] ?? ''}>
                                            {n.datos_adicionales?.[col] || '—'}
                                        </td>
                                    ))}

                                    <td className="px-3 py-2">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-medium whitespace-nowrap ${
                                            n.estado === 'pendiente'  ? 'bg-gray-100 text-gray-600' :
                                            n.estado === 'en_carga'   ? 'bg-blue-50 text-blue-700' :
                                            n.estado === 'evaluado'   ? 'bg-green-50 text-green-700' :
                                            'bg-gray-50 text-gray-500'
                                        }`}>
                                            {n.estado.replace('_', ' ')}
                                        </span>
                                    </td>

                                    <td className="px-3 py-2 whitespace-nowrap">
                                        <button onClick={() => onEditar(n)}
                                            className="text-[10px] text-[#1B2D6B] hover:underline font-medium">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Componente principal ──────────────────────────────────────────────────────
export default function NominaCreate({ periodo, facultades, academicos, nominasEnPeriodo, columnas_adicionales }) {
    const { flash } = usePage().props;
    const [showModal, setShowModal]           = useState(false);
    const [editandoNomina, setEditandoNomina] = useState(false);
    const [enviandoAcceso, setEnviandoAcceso] = useState(false);

    const totalLicencias      = nominasEnPeriodo.filter(n => n.con_licencia).length;
    const sinCuenta           = nominasEnPeriodo.filter(n => !n.tiene_cuenta).length;
    const columnasAdicionales = columnas_adicionales ?? [];

    function handleEnviarAcceso() {
        if (nominasEnPeriodo.length === 0) return;
        if (!confirm(`¿Comunicar acceso a las ${nominasEnPeriodo.length} persona(s) de esta nómina?\n\nEl sistema creará su cuenta (si aún no existe), asignará sus perfiles según el cargo en nómina y enviará un correo con usuario y contraseña inicial.\n\nHasta que usted ejecute esta acción, no podrán ingresar al sistema.`)) return;
        setEnviandoAcceso(true);
        router.post(
            `/analista/periodos/${periodo.id}/nominas/enviar-credenciales`,
            {},
            { onFinish: () => setEnviandoAcceso(false) }
        );
    }

    return (
        <>
            <Head title={`Nómina — ${periodo.nombre}`} />
            <AppLayout title="Gestionar Nómina">

                <div className="flex items-center justify-between -mt-4 mb-6">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                        <span>/</span>
                        <span className="text-gray-700 font-medium">{periodo.nombre}</span>
                        <span>/</span>
                        <span>Nómina</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <a href="/analista/nominas/plantilla"
                            className="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                            ↓ Plantilla UCM
                        </a>
                        <a href={`/analista/periodos/${periodo.id}/nominas/exportar`}
                            className="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                            ↓ Exportar Excel
                        </a>
                        {nominasEnPeriodo.length > 0 && (
                            <button
                                onClick={handleEnviarAcceso}
                                disabled={enviandoAcceso}
                                className="text-xs border border-[#0096D6] text-[#0096D6] hover:bg-[#0096D6]/10 px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <MailIcon />
                                {enviandoAcceso ? 'Enviando...' : 'Comunicar acceso'}
                            </button>
                        )}
                        <button onClick={() => setShowModal(true)}
                            className="text-xs bg-[#1B2D6B] text-white px-3 py-1.5 rounded-lg hover:bg-[#152558] transition-colors">
                            + Agregar académico
                        </button>
                    </div>
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

                {nominasEnPeriodo.length > 0 && sinCuenta > 0 && (
                    <div className="mb-5 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-900">
                        <strong>Creación de cuentas:</strong> hay {sinCuenta} persona(s) en nómina sin acceso al sistema.
                        Use <strong>Comunicar acceso</strong> para crear sus cuentas, asignar perfiles y enviar credenciales por correo.
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <div className="space-y-5">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Período</p>
                            <p className="font-semibold text-gray-900">{periodo.nombre}</p>
                            <p className="text-sm text-gray-500 mt-1">Año {periodo.anio}</p>
                            <div className="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">Total en nómina</span>
                                    <span className="text-lg font-bold text-[#1B2D6B]">{nominasEnPeriodo.length}</span>
                                </div>
                                {totalLicencias > 0 && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-500" title="Académicos con licencia médica u otros casos especiales comunicados por secretarios">
                                            Casos especiales
                                        </span>
                                        <span className="text-sm font-semibold text-amber-600">{totalLicencias}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">Sin cuenta aún</span>
                                    <span className={`text-sm font-semibold ${sinCuenta > 0 ? 'text-amber-600' : 'text-green-600'}`}>
                                        {sinCuenta}
                                    </span>
                                </div>
                                {columnasAdicionales.length > 0 && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-500">Cols. extra</span>
                                        <span className="text-sm font-semibold text-indigo-600">{columnasAdicionales.length}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                        <PanelExcel periodo={periodo} facultades={facultades} />
                        <PanelColumnas periodo={periodo} columnasAdicionales={columnasAdicionales} />
                    </div>

                    <div className="lg:col-span-3">
                        <NominaGrid
                            nominasEnPeriodo={nominasEnPeriodo}
                            periodo={periodo}
                            columnasAdicionales={columnasAdicionales}
                            onEditar={setEditandoNomina}
                        />
                    </div>
                </div>
            </AppLayout>

            {showModal && (
                <ModalAgregarAcademico
                    periodo={periodo}
                    facultades={facultades}
                    onClose={() => setShowModal(false)}
                />
            )}

            {editandoNomina && (
                <ModalEditarAcademico
                    periodo={periodo}
                    nomina={editandoNomina}
                    columnasAdicionales={columnasAdicionales}
                    onClose={() => setEditandoNomina(null)}
                />
            )}
        </>
    );
}

function MailIcon() {
    return (
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
    );
}
