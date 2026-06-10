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
        post(route('analista.periodos.nominas.agregar', periodo.id), { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-800">Agregar académico</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                </div>
                <p className="text-xs text-gray-500">Si el RUT ya existe en el sistema, se usará el usuario existente.</p>
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
function PanelExcel({ periodo }) {
    const { flash } = usePage().props;
    const preview   = flash?.excel_preview;

    const fileRef = useRef();
    const [uploading, setUploading]   = useState(false);
    const [mapeo, setMapeo]           = useState({});
    const [tieneEncabezado, setTieneEncabezado] = useState(true);
    const [importando, setImportando] = useState(false);

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
            route('analista.periodos.nominas.preview-excel', periodo.id),
            fd,
            { forceFormData: true, onFinish: () => setUploading(false) }
        );
    }

    function importar() {
        if (!preview?.path) return;
        setImportando(true);
        router.post(
            route('analista.periodos.nominas.importar-excel', periodo.id),
            { path: preview.path, tiene_encabezado: tieneEncabezado, mapeo },
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

            <form onSubmit={subirArchivo} className="flex gap-2 items-center">
                <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv"
                    className="text-xs text-gray-600 file:mr-2 file:text-xs file:border file:border-gray-300 file:rounded file:px-2 file:py-1 file:bg-gray-50 file:cursor-pointer"
                />
                <button type="submit" disabled={uploading}
                    className="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg disabled:opacity-50 whitespace-nowrap">
                    {uploading ? 'Leyendo...' : 'Cargar'}
                </button>
            </form>

            {preview && (
                <div className="space-y-4 border-t border-gray-100 pt-4">
                    {/* Vista previa */}
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
                                {sinMapear.length} columna(s) no reconocida(s) — se ignorarán
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

// ── Grilla de nómina ──────────────────────────────────────────────────────────
function NominaGrid({ nominasEnPeriodo, periodo, editingId, obsInput, setObsInput,
                      savingId, setEditingId, onQuitarLicencia, onConfirmarLicencia }) {
    if (nominasEnPeriodo.length === 0) {
        return (
            <div className="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                <p className="text-gray-400 text-sm">
                    La nómina está vacía. Importa un Excel SAPD o agrega académicos manualmente.
                </p>
            </div>
        );
    }

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
                            {['N° Personal','RUT','Nombre','Unidad Superior','Unidad',
                              'Posición','Tipo','Fecha Ctto.','Horas','Categoría','Fecha Categ.','Estado',''].map(h => (
                                <th key={h} className="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {nominasEnPeriodo.map(n => {
                            const isSaving = savingId === n.id;
                            return (
                                <tr key={n.id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 text-gray-400">{n.numero_personal || '—'}</td>
                                    <td className="px-3 py-2 font-mono text-gray-600">{n.rut || '—'}</td>
                                    <td className="px-3 py-2 font-medium text-gray-800 min-w-[160px]">
                                        <Link
                                            href={route('analista.periodos.nominas.detalle', [periodo.id, n.id])}
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
                                    <td className="px-3 py-2">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-medium whitespace-nowrap ${
                                            n.estado === 'pendiente'    ? 'bg-gray-100 text-gray-600' :
                                            n.estado === 'en_carga'     ? 'bg-blue-50 text-blue-700' :
                                            n.estado === 'evaluado'     ? 'bg-green-50 text-green-700' :
                                            'bg-gray-50 text-gray-500'
                                        }`}>
                                            {n.estado.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {n.con_licencia ? (
                                            <button disabled={isSaving} onClick={() => onQuitarLicencia(n.id)}
                                                className="text-[10px] text-gray-400 hover:text-red-500 disabled:opacity-40">
                                                {isSaving ? '...' : 'Quitar caso'}
                                            </button>
                                        ) : (
                                            <button onClick={() => setEditingId(n.id)}
                                                className="text-[10px] text-amber-600 hover:text-amber-800 font-medium">
                                                + Caso especial
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {editingId && (
                <div className="border-t border-amber-200 bg-amber-50 px-5 py-3 flex items-center gap-3">
                    <span className="text-xs text-amber-700 font-medium whitespace-nowrap">Motivo:</span>
                    <input type="text" value={obsInput} onChange={e => setObsInput(e.target.value)}
                        placeholder="Ej: licencia médica, permiso especial..."
                        className="flex-1 border border-amber-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-amber-200"
                        autoFocus />
                    <button disabled={!obsInput.trim() || !!savingId} onClick={() => onConfirmarLicencia(editingId)}
                        className="text-xs bg-amber-500 text-white px-3 py-1.5 rounded-lg hover:bg-amber-600 disabled:opacity-50 whitespace-nowrap">
                        {savingId ? 'Guardando...' : 'Confirmar'}
                    </button>
                    <button onClick={() => setEditingId(null)} className="text-xs text-gray-400 hover:text-gray-600">Cancelar</button>
                </div>
            )}
        </div>
    );
}

// ── Componente principal ──────────────────────────────────────────────────────
export default function NominaCreate({ periodo, facultades, academicos, nominasEnPeriodo }) {
    const { flash } = usePage().props;
    const [editingId, setEditingId] = useState(null);
    const [obsInput, setObsInput]   = useState('');
    const [savingId, setSavingId]   = useState(null);
    const [showModal, setShowModal] = useState(false);

    function confirmarLicencia(nominaId) {
        setSavingId(nominaId);
        router.patch(route('analista.nominas.licencia', nominaId),
            { con_licencia: true, observacion_licencia: obsInput },
            { preserveScroll: true, onFinish: () => setSavingId(null), onSuccess: () => { setEditingId(null); setObsInput(''); } }
        );
    }
    function quitarLicencia(nominaId) {
        setSavingId(nominaId);
        router.patch(route('analista.nominas.licencia', nominaId),
            { con_licencia: false, observacion_licencia: null },
            { preserveScroll: true, onFinish: () => setSavingId(null) }
        );
    }

    const totalLicencias = nominasEnPeriodo.filter(n => n.con_licencia).length;

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
                        <a href={route('analista.nominas.plantilla')}
                            className="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                            ↓ Plantilla UCM
                        </a>
                        <a href={route('analista.periodos.nominas.exportar', periodo.id)}
                            className="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                            ↓ Exportar Excel
                        </a>
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
                                        <span className="text-sm text-gray-500">Casos especiales</span>
                                        <span className="text-sm font-semibold text-amber-600">{totalLicencias}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                        <PanelExcel periodo={periodo} />
                    </div>

                    <div className="lg:col-span-3">
                        <NominaGrid
                            nominasEnPeriodo={nominasEnPeriodo}
                            periodo={periodo}
                            editingId={editingId}
                            obsInput={obsInput}
                            setObsInput={setObsInput}
                            savingId={savingId}
                            setEditingId={setEditingId}
                            onQuitarLicencia={quitarLicencia}
                            onConfirmarLicencia={confirmarLicencia}
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
        </>
    );
}
