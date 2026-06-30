import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

function SlotSelect({ slot, label, value, candidatos, candidatosExternos, onChange, disabled }) {
    const lista = slot.es_externo ? candidatosExternos : candidatos;
    const selected = lista.find(c => c.id === value);

    return (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-gray-700">
                {label}
                {slot.es_externo && (
                    <span className="ml-2 text-xs font-normal text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                        Externo — otra facultad
                    </span>
                )}
            </label>
            {selected && (
                <p className="text-xs text-gray-500 mb-1">
                    Actual: <span className="font-medium text-gray-700">{selected.name}</span> — {selected.rut}
                </p>
            )}
            <select
                value={value ?? ''}
                onChange={e => onChange(slot.key, e.target.value || null)}
                disabled={disabled}
                className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 disabled:bg-gray-50"
            >
                <option value="">— Sin asignar —</option>
                {lista.map(c => (
                    <option key={c.id} value={c.id}>{c.name} — {c.rut}</option>
                ))}
            </select>
        </div>
    );
}

export default function AsignacionFacultadEdit({
    periodo, facultad, cargos, slots, candidatos, candidatos_externos, comision_estado, comision_bloqueada,
}) {
    const { flash } = usePage().props;
    const form = useForm({ ...cargos });

    function setCargo(key, nominaId) {
        form.setData(key, nominaId);
    }

    function submit(e) {
        e.preventDefault();
        form.put(`/analista/periodos/${periodo.id}/cargos/${facultad.id}`);
    }

    return (
        <>
            <Head title={`Cargos — ${facultad.nombre}`} />
            <AppLayout title={`Cargos — ${facultad.codigo}`}>

                <div className="flex items-center gap-2 text-sm text-gray-500 -mt-4 mb-6">
                    <Link href="/analista/periodos" className="hover:text-gray-700">Períodos</Link>
                    <span>/</span>
                    <Link href={`/analista/periodos/${periodo.id}/cargos`} className="hover:text-gray-700">
                        Cargos de facultad
                    </Link>
                    <span>/</span>
                    <span className="text-gray-700 font-medium">{facultad.nombre}</span>
                </div>

                {flash?.error && (
                    <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}
                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {comision_bloqueada && (
                    <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        La comisión CCA está confirmada. Los cargos no pueden modificarse.
                    </div>
                )}

                <form onSubmit={submit} className="max-w-2xl space-y-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
                        <p className="text-xs text-gray-500">
                            Una persona puede ocupar varios cargos (ej. secretario y director de escuela).
                            Los miembros CCA deben ser académicos evaluables de la nómina (mínimo 2).
                        </p>

                        {slots.map(slot => (
                            <SlotSelect
                                key={slot.key}
                                slot={slot}
                                label={slot.label}
                                value={form.data[slot.key]}
                                candidatos={candidatos}
                                candidatosExternos={candidatos_externos}
                                onChange={setCargo}
                                disabled={comision_bloqueada}
                            />
                        ))}
                    </div>

                    {!comision_bloqueada && (
                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="bg-[#1B2D6B] text-white text-sm px-4 py-2 rounded-lg hover:bg-[#152558] disabled:opacity-50"
                            >
                                {form.processing ? 'Guardando...' : 'Guardar cargos'}
                            </button>
                            <Link
                                href={`/analista/periodos/${periodo.id}/cargos`}
                                className="text-sm text-gray-500 hover:text-gray-700 py-2"
                            >
                                Cancelar
                            </Link>
                        </div>
                    )}
                </form>
            </AppLayout>
        </>
    );
}
