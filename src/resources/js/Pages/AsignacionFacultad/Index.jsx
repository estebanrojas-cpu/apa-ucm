import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const ESTADO_BADGE = {
    borrador:   { label: 'Borrador',   cls: 'bg-amber-100 text-amber-800' },
    confirmada: { label: 'Confirmada', cls: 'bg-green-100 text-green-800' },
};

export default function AsignacionFacultadIndex({ periodo, facultades }) {
    const { flash } = usePage().props;

    function confirmar(facultadId) {
        if (!confirm('¿Confirmar la comisión CCA generada desde los cargos? No podrá modificar los cargos después.')) return;
        router.post(`/analista/periodos/${periodo.id}/cargos/${facultadId}/confirmar-comision`);
    }

    return (
        <>
            <Head title={`Cargos facultad — ${periodo.nombre}`} />
            <AppLayout title="Cargos de facultad">

                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                    <span>/</span>
                    <span className="text-gray-700 font-medium">Cargos de facultad</span>
                </div>

                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}

                <p className="text-sm text-gray-600 mb-6">
                    Período <strong>{periodo.nombre}</strong> — asigne secretario/a, decano/a, director/a de escuela
                    y miembros CCA por facultad. La comisión evaluadora se sincroniza automáticamente desde los cargos CCA.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {facultades.map(f => {
                        const comision = f.comision;
                        const badge = comision
                            ? (ESTADO_BADGE[comision.estado] ?? ESTADO_BADGE.borrador)
                            : { label: 'Sin asignar', cls: 'bg-gray-100 text-gray-600' };

                        return (
                            <div key={f.id} className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold text-gray-900">{f.nombre}</h3>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {f.nominas_count} académicos en nómina
                                        </p>
                                    </div>
                                    <span className={`text-xs font-medium px-2.5 py-0.5 rounded-full shrink-0 ${badge.cls}`}>
                                        {comision ? badge.label : (f.completo ? 'Cargos listos' : 'Incompleto')}
                                    </span>
                                </div>

                                {comision?.confirmada_en && (
                                    <p className="text-xs text-gray-400">Comisión confirmada el {comision.confirmada_en}</p>
                                )}

                                <div className="flex flex-wrap gap-2 pt-1">
                                    <Link
                                        href={`/analista/periodos/${periodo.id}/cargos/${f.id}`}
                                        className="text-sm font-medium px-3 py-1.5 rounded-lg bg-[#1B2D6B] text-white hover:bg-[#152558]"
                                    >
                                        {f.completo ? 'Editar cargos' : 'Asignar cargos'}
                                    </Link>
                                    {comision?.estado !== 'confirmada' && f.completo && (
                                        <button
                                            type="button"
                                            onClick={() => confirmar(f.id)}
                                            className="text-sm font-medium px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700"
                                        >
                                            Confirmar comisión CCA
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </AppLayout>
        </>
    );
}
