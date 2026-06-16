import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const MIME_PREVIEWABLE = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

export default function EvidenciaCategoria({
    periodo, categoria, evidenciasNormales, evidenciasApelacion,
    puedeCargar, puedeCargarApelacion, nominaEstado,
}) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title={`Evidencias — ${categoria.nombre}`} />
            <AppLayout title={categoria.nombre}>

                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/academico/evidencias" className="hover:text-[#1B2D6B] transition-colors">
                        Evidencias
                    </Link>
                    <span>/</span>
                    <span className="text-gray-800 font-medium">{categoria.nombre}</span>
                </div>

                {flash?.success && (
                    <div className="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
                        {flash.error}
                    </div>
                )}

                {categoria.descripcion && (
                    <p className="text-sm text-gray-500 mb-6">{categoria.descripcion}</p>
                )}

                {/* Evidencias normales */}
                <section className="mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                            Evidencias del período
                        </h2>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                            {evidenciasNormales.length} {evidenciasNormales.length === 1 ? 'archivo' : 'archivos'}
                        </span>
                    </div>

                    <FileManager
                        evidencias={evidenciasNormales}
                        puedeCargar={puedeCargar}
                        categoriaId={categoria.id}
                        rutaStore="/academico/evidencias"
                        rutaDelete={(id) => `/academico/evidencias/${id}`}
                        esApelacion={false}
                    />
                </section>

                {/* Evidencias de apelación */}
                {puedeCargarApelacion && (
                    <section>
                        <div className="flex items-center gap-3 mb-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                                Evidencias de apelación
                            </h2>
                            <span className="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-medium">
                                {evidenciasApelacion.length} {evidenciasApelacion.length === 1 ? 'archivo' : 'archivos'}
                            </span>
                            <span className="text-xs text-gray-400">Período de apelaciones abierto</span>
                        </div>

                        <div className="mb-4 bg-orange-50 border border-orange-200 rounded-xl px-4 py-3 text-sm text-orange-800">
                            Adjunta aquí documentación adicional de respaldo. No reemplaza tus evidencias originales.
                        </div>

                        <FileManager
                            evidencias={evidenciasApelacion}
                            puedeCargar={true}
                            categoriaId={categoria.id}
                            rutaStore="/academico/evidencias-apelacion"
                            rutaDelete={(id) => `/academico/evidencias-apelacion/${id}`}
                            esApelacion={true}
                        />
                    </section>
                )}

                {/* Si no hay nada que hacer */}
                {!puedeCargar && !puedeCargarApelacion && evidenciasNormales.length === 0 && (
                    <div className="text-center py-12 text-gray-400 text-sm">
                        No hay evidencias en esta categoría y la carga no está habilitada.
                    </div>
                )}

            </AppLayout>
        </>
    );
}

function FileManager({ evidencias, puedeCargar, categoriaId, rutaStore, rutaDelete, esApelacion }) {
    const fileRef = useRef(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        categoria_id: categoriaId,
        archivo:      null,
        descripcion:  '',
    });

    function submit(e) {
        e.preventDefault();
        post(rutaStore, {
            forceFormData: true,
            onSuccess: () => {
                reset('archivo', 'descripcion');
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    }

    function eliminar(id) {
        if (!confirm('¿Está seguro de eliminar esta evidencia?')) return;
        router.delete(rutaDelete(id));
    }

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {/* Lista de archivos */}
            {evidencias.length > 0 ? (
                <ul className="divide-y divide-gray-100">
                    {evidencias.map(ev => (
                        <li key={ev.id} className="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                            <FileTypeIcon mime={ev.mime_type} />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-800 truncate">{ev.nombre_archivo}</p>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {ev.tamano}
                                    {ev.descripcion && <span> · {ev.descripcion}</span>}
                                    <span> · {ev.created_at}</span>
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {MIME_PREVIEWABLE.includes(ev.mime_type) ? (
                                    <a
                                        href={ev.url_preview}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs px-2.5 py-1 rounded-lg bg-[#1B2D6B] text-white hover:bg-[#152558] transition-colors"
                                        title="Ver en el navegador"
                                    >
                                        Ver
                                    </a>
                                ) : null}
                                <a
                                    href={ev.url_descarga}
                                    className="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
                                    download
                                    title="Descargar archivo"
                                >
                                    Descargar
                                </a>
                                {puedeCargar && (
                                    <button
                                        onClick={() => eliminar(ev.id)}
                                        className="text-red-400 hover:text-red-600 transition-colors p-1"
                                        title="Eliminar"
                                    >
                                        <TrashIcon />
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="px-5 py-8 text-center text-gray-400 text-sm">
                    {puedeCargar ? 'No hay archivos aún. Sube el primero abajo.' : 'Sin archivos en esta categoría.'}
                </div>
            )}

            {/* Zona de subida */}
            {puedeCargar && (
                <div className="border-t border-gray-100 px-5 py-4 bg-gray-50">
                    <form onSubmit={submit} className="space-y-3">
                        <div
                            onClick={() => fileRef.current?.click()}
                            className={`border-2 border-dashed rounded-xl px-5 py-6 text-center cursor-pointer transition-colors ${
                                data.archivo
                                    ? 'border-[#1B2D6B] bg-[#1B2D6B]/5'
                                    : 'border-gray-300 hover:border-[#1B2D6B] hover:bg-[#1B2D6B]/5'
                            }`}
                        >
                            <UploadIcon className="mx-auto mb-2 text-gray-400" />
                            {data.archivo ? (
                                <p className="text-sm font-medium text-[#1B2D6B]">{data.archivo.name}</p>
                            ) : (
                                <>
                                    <p className="text-sm text-gray-500">Haz clic para seleccionar un archivo</p>
                                    <p className="text-xs text-gray-400 mt-1">PDF, Word, JPG o PNG — máx. 10 MB</p>
                                </>
                            )}
                        </div>
                        <input
                            ref={fileRef}
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            className="sr-only"
                            onChange={e => setData('archivo', e.target.files[0] ?? null)}
                        />
                        {errors.archivo && <p className="text-xs text-red-600">{errors.archivo}</p>}

                        <div className="flex items-center gap-3">
                            <input
                                type="text"
                                value={data.descripcion}
                                onChange={e => setData('descripcion', e.target.value)}
                                placeholder="Descripción opcional"
                                maxLength={500}
                                className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B2D6B]/30"
                            />
                            <button
                                type="submit"
                                disabled={!data.archivo || processing}
                                className="px-5 py-2 bg-[#1B2D6B] text-white text-sm font-medium rounded-lg hover:bg-[#152558] disabled:opacity-40 disabled:cursor-not-allowed transition-colors shrink-0"
                            >
                                {processing ? 'Subiendo…' : 'Subir'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}

function FileTypeIcon({ mime }) {
    const isPdf   = mime === 'application/pdf';
    const isImage = mime?.startsWith('image/');
    const color   = isPdf ? 'text-red-500' : isImage ? 'text-green-500' : 'text-blue-500';
    return (
        <svg className={`w-8 h-8 shrink-0 ${color}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    );
}

function UploadIcon({ className = '' }) {
    return (
        <svg className={`w-8 h-8 ${className}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>
    );
}

function TrashIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
        </svg>
    );
}
