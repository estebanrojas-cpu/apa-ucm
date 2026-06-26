import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function ComisionEdit({ periodo, facultad, comision, candidatos }) {
    const form = useForm({
        integrantes: comision.integrantes ?? [],
    });

    function toggle(userId) {
        const current = form.data.integrantes;
        if (current.includes(userId)) {
            form.setData('integrantes', current.filter(id => id !== userId));
        } else {
            form.setData('integrantes', [...current, userId]);
        }
    }

    function submit(e) {
        e.preventDefault();
        form.put(`/analista/periodos/${periodo.id}/comisiones/${facultad.id}`);
    }

    return (
        <>
            <Head title={`Comisión — ${facultad.nombre}`} />
            <AppLayout title={`Comisión CCA — ${facultad.codigo}`}>

                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                    <span>/</span>
                    <Link href={`/analista/periodos/${periodo.id}/comisiones`} className="hover:text-gray-700">
                        Comisión evaluadora
                    </Link>
                    <span>/</span>
                    <span className="text-gray-700 font-medium">{facultad.nombre}</span>
                </div>

                <form onSubmit={submit} className="max-w-2xl space-y-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-sm font-semibold text-gray-800 mb-1">Integrantes</h2>
                        <p className="text-xs text-gray-500 mb-4">
                            Seleccione al menos 2 académicos evaluables de la nómina. No incluya al decano/a.
                        </p>

                        {form.errors.integrantes && (
                            <p className="text-xs text-red-600 mb-3">{form.errors.integrantes}</p>
                        )}

                        {candidatos.length === 0 ? (
                            <p className="text-sm text-gray-400">No hay candidatos evaluables en la nómina.</p>
                        ) : (
                            <div className="space-y-2">
                                {candidatos.map(c => (
                                    <label key={c.id}
                                        className="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={form.data.integrantes.includes(c.id)}
                                            onChange={() => toggle(c.id)}
                                            className="rounded border-gray-300"
                                        />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-800">{c.name}</p>
                                            <p className="text-xs text-gray-500">{c.rut}{c.email ? ` · ${c.email}` : ' · acceso pendiente'}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex gap-3">
                        <button type="submit" disabled={form.processing || form.data.integrantes.length < 2}
                            className="bg-[#1B2D6B] text-white text-sm px-4 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-50">
                            {form.processing ? 'Guardando...' : 'Guardar integrantes'}
                        </button>
                        <Link href={`/analista/periodos/${periodo.id}/comisiones`}
                            className="text-sm text-gray-500 hover:text-gray-700 py-2">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </AppLayout>
        </>
    );
}
