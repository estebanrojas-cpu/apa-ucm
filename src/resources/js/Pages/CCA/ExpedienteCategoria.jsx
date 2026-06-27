import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const MIME_PREVIEWABLE = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

export default function ExpedienteCategoria({
    nomina, categoria, esApelacion, evidenciasNormales = [], evidenciasApelacion = [],
}) {
    return (
        <>
            <Head title={`${categoria.nombre} — ${nomina.nombre}`} />
            <AppLayout title={categoria.nombre}>

                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/cca/expedientes" className="hover:text-[#1B2D6B] transition-colors">
                        Expedientes
                    </Link>
                    <span>/</span>
                    <Link href={`/cca/expedientes/${nomina.id}`} className="hover:text-[#1B2D6B] transition-colors">
                        {nomina.nombre}
                    </Link>
                    <span>/</span>
                    <span className="text-gray-800 font-medium">{categoria.nombre}</span>
                </div>

                {esApelacion && (
                    <div className="mb-5 rounded-lg bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-800">
                        Re-evaluación por apelación: revise las evidencias originales del período y las nuevas de la apelación.
                    </div>
                )}

                {categoria.descripcion && (
                    <p className="text-sm text-gray-500 mb-6">{categoria.descripcion}</p>
                )}

                <section className="mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                            Evidencias del período
                        </h2>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                            {evidenciasNormales.length} {evidenciasNormales.length === 1 ? 'archivo' : 'archivos'}
                        </span>
                    </div>
                    <FileList
                        evidencias={evidenciasNormales}
                        emptyMsg="El académico no ha cargado archivos en esta categoría."
                    />
                </section>

                {esApelacion && (
                    <section>
                        <div className="flex items-center gap-3 mb-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                                Evidencias de apelación
                            </h2>
                            <span className="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-medium">
                                {evidenciasApelacion.length} {evidenciasApelacion.length === 1 ? 'archivo' : 'archivos'}
                            </span>
                        </div>
                        <FileList
                            evidencias={evidenciasApelacion}
                            emptyMsg="No hay archivos nuevos de apelación en esta categoría."
                        />
                    </section>
                )}

            </AppLayout>
        </>
    );
}

function FileList({ evidencias, emptyMsg = 'Sin archivos.' }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {evidencias.length > 0 ? (
                <ul className="divide-y divide-gray-100">
                    {evidencias.map(ev => (
                        <li key={ev.id} className="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                            <FileTypeIcon mime={ev.mime_type} />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-800 truncate">{ev.nombre_archivo}</p>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {ev.tamano} · {ev.created_at}
                                    {ev.descripcion && ` · ${ev.descripcion}`}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {MIME_PREVIEWABLE.includes(ev.mime_type) && (
                                    <a href={ev.url_preview} target="_blank" rel="noopener noreferrer"
                                        className="text-xs px-2.5 py-1 rounded-lg bg-[#1B2D6B] text-white hover:bg-[#152558] transition-colors">
                                        Ver
                                    </a>
                                )}
                                <a href={ev.url_descarga} download
                                    className="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                                    Descargar
                                </a>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="px-5 py-8 text-center text-gray-400 text-sm italic">{emptyMsg}</div>
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
