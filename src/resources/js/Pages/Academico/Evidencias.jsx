import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Evidencias({
    periodo, nomina, plazo, puedeCargar, puedeCargarApelacion,
    apelacionEtapaVigente, apelacion, categorias, conteoEvidencias,
}) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Mis Evidencias" />
            <AppLayout title="Carga de Evidencias">

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

                <EstadoBanner periodo={periodo} nomina={nomina} plazo={plazo} puedeCargar={puedeCargar} />

                {!periodo && (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center text-yellow-800 text-sm">
                        No hay un período activo en este momento.
                    </div>
                )}

                {periodo && !nomina && (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center text-yellow-800 text-sm">
                        No está en la nómina del período activo. Contacte a su secretario de facultad.
                    </div>
                )}

                {nomina?.observacion_secretario && (
                    <div className="mb-5 bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p className="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                            Observaciones del secretario
                        </p>
                        <p className="text-sm text-amber-800">{nomina.observacion_secretario}</p>
                    </div>
                )}

                {/* Aviso apelaciones */}
                {puedeCargarApelacion && (
                    <div className="mb-5 bg-orange-50 border border-orange-200 rounded-xl px-5 py-4">
                        <p className="text-sm font-semibold text-orange-800 mb-1">
                            Período de apelaciones abierto
                        </p>
                        <p className="text-sm text-orange-700">
                            {apelacion?.estado === 'en_revision'
                                ? 'Tu apelación está en revisión. Puedes agregar más evidencias de respaldo en cada categoría.'
                                : 'Puedes adjuntar evidencias adicionales de respaldo. Abre una categoría para subir documentos.'
                            }
                        </p>
                    </div>
                )}

                {/* Grid de categorías */}
                {nomina && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {categorias.map(cat => {
                            const conteo = conteoEvidencias?.[cat.id] ?? { normales: 0, apelacion: 0 };
                            return (
                                <Link
                                    key={cat.id}
                                    href={`/academico/evidencias/categoria/${cat.id}`}
                                    className="bg-white rounded-xl border border-gray-200 p-5 hover:border-[#1B2D6B] hover:shadow-sm transition-all group"
                                >
                                    <div className="flex items-start justify-between mb-3">
                                        <h3 className="font-semibold text-gray-800 text-sm group-hover:text-[#1B2D6B] transition-colors">
                                            {cat.nombre}
                                        </h3>
                                        <span className="text-[#1B2D6B] text-sm font-bold ml-2 shrink-0">→</span>
                                    </div>
                                    {cat.descripcion && (
                                        <p className="text-xs text-gray-500 mb-3">{cat.descripcion}</p>
                                    )}
                                    <div className="flex items-center gap-3">
                                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${
                                            conteo.normales > 0
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-gray-100 text-gray-500'
                                        }`}>
                                            {conteo.normales} {conteo.normales === 1 ? 'archivo' : 'archivos'}
                                        </span>
                                        {conteo.apelacion > 0 && (
                                            <span className="text-xs font-medium px-2.5 py-1 rounded-full bg-orange-100 text-orange-700">
                                                +{conteo.apelacion} apelación
                                            </span>
                                        )}
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}

            </AppLayout>
        </>
    );
}

function EstadoBanner({ periodo, nomina, plazo, puedeCargar }) {
    if (!periodo) return null;

    const estadoLabels = {
        pendiente:      { label: 'Pendiente',       color: 'text-yellow-700 bg-yellow-100' },
        en_carga:       { label: 'En carga',         color: 'text-blue-700 bg-blue-100' },
        en_evaluacion:  { label: 'En evaluación',    color: 'text-purple-700 bg-purple-100' },
        evaluado:       { label: 'Evaluado',         color: 'text-green-700 bg-green-100' },
        apelado:        { label: 'En apelación',     color: 'text-orange-700 bg-orange-100' },
        cerrado:        { label: 'Cerrado',          color: 'text-gray-700 bg-gray-100' },
    };

    const estadoInfo = estadoLabels[nomina?.estado] ?? { label: nomina?.estado, color: 'text-gray-700 bg-gray-100' };

    const formatDate = (dateStr) => {
        if (!dateStr) return null;
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    };

    const plazoLicenciaVigente = nomina?.plazo_licencia
        ? new Date(nomina.plazo_licencia) >= new Date(new Date().toDateString())
        : false;

    return (
        <>
            {!nomina?.con_licencia && nomina?.plazo_licencia && (
                <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
                    <p className="text-sm font-semibold text-blue-800">Plazo especial de carga de evidencias</p>
                    <p className={`text-sm font-medium mt-1 ${plazoLicenciaVigente ? 'text-green-700' : 'text-red-700'}`}>
                        Fecha límite: <span className="font-bold">{formatDate(nomina.plazo_licencia)}</span>
                        <span className="ml-1.5 text-xs font-normal">({plazoLicenciaVigente ? 'vigente' : 'vencido'})</span>
                    </p>
                </div>
            )}

            {nomina?.con_licencia && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                    <p className="text-sm font-semibold text-amber-800">Estás marcado/a con licencia médica</p>
                    {nomina.observacion_licencia && (
                        <p className="text-xs text-amber-700 mt-0.5">{nomina.observacion_licencia}</p>
                    )}
                    <div className="mt-2">
                        {nomina.plazo_licencia ? (
                            <p className={`text-sm font-medium ${plazoLicenciaVigente ? 'text-green-700' : 'text-red-700'}`}>
                                Plazo especial: <span className="font-bold">{formatDate(nomina.plazo_licencia)}</span>
                                <span className="ml-1.5 text-xs font-normal">({plazoLicenciaVigente ? 'vigente' : 'vencido'})</span>
                            </p>
                        ) : (
                            <p className="text-sm text-amber-700">
                                El secretario de su facultad debe asignarle un plazo especial de entrega.
                            </p>
                        )}
                    </div>
                </div>
            )}

            <div className="mb-6 bg-white border border-gray-200 rounded-xl p-4 flex flex-wrap items-center gap-x-6 gap-y-3">
                <div>
                    <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Período</p>
                    <p className="font-semibold text-gray-800 text-sm">{periodo.nombre}</p>
                </div>

                {!nomina?.con_licencia && plazo && (
                    <div className="border-l border-gray-200 pl-6">
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Plazo de carga</p>
                        {plazo.cerrado ? (
                            <p className="font-semibold text-sm text-red-700">
                                Recepción cerrada
                                <span className="ml-1.5 font-normal text-xs opacity-80">({plazo.cerrado_en})</span>
                            </p>
                        ) : (
                            <p className={`font-semibold text-sm ${plazo.vigente ? 'text-green-700' : 'text-red-700'}`}>
                                {plazo.fecha_limite}
                                <span className="ml-1.5 font-normal text-xs opacity-80">
                                    ({plazo.vigente ? 'vigente' : 'vencido'})
                                </span>
                            </p>
                        )}
                    </div>
                )}

                {nomina && (
                    <div className="border-l border-gray-200 pl-6">
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium">Estado expediente</p>
                        <span className={`inline-block mt-0.5 px-2 py-0.5 rounded-full text-xs font-semibold ${estadoInfo.color}`}>
                            {estadoInfo.label}
                        </span>
                    </div>
                )}

                <div className="ml-auto">
                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                        puedeCargar ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                    }`}>
                        {puedeCargar ? 'Carga habilitada' : 'Carga no disponible'}
                    </span>
                </div>
            </div>
        </>
    );
}
