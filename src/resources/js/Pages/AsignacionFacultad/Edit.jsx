import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState, useEffect } from 'react';

function SlotSelect({ slot, label, value, candidatos, onChange, disabled, periodoId, facultadId }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);

    const selected = candidatos.find(c => c.id === value);

    useEffect(() => {
        if (!open || q.length < 2) {
            setResults([]);
            return;
        }
        const t = setTimeout(() => {
            fetch(`/analista/periodos/${periodoId}/cargos/${facultadId}/buscar?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(setResults)
                .catch(() => setResults([]));
        }, 250);
        return () => clearTimeout(t);
    }, [q, open, periodoId, facultadId]);

    return (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            {selected && (
                <p className="text-xs text-gray-500 mb-1">
                    Actual: <span className="font-medium text-gray-700">{selected.name}</span> — {selected.rut}
                </p>
            )}
            <div className="relative">
                <input
                    type="text"
                    placeholder="Buscar por RUT o nombre..."
                    value={q}
                    onChange={e => { setQ(e.target.value); setOpen(true); }}
                    onFocus={() => setOpen(true)}
                    disabled={disabled}
                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 disabled:bg-gray-50"
                />
                {open && results.length > 0 && (
                    <ul className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        {results.map(r => (
                            <li key={r.id}>
                                <button
                                    type="button"
                                    className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50"
                                    onClick={() => { onChange(slot.key, r.id); setQ(''); setOpen(false); }}
                                >
                                    {r.label}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <select
                value={value ?? ''}
                onChange={e => onChange(slot.key, e.target.value || null)}
                disabled={disabled}
                className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 disabled:bg-gray-50"
            >
                <option value="">— Sin asignar —</option>
                {candidatos.map(c => (
                    <option key={c.id} value={c.id}>{c.name} — {c.rut}</option>
                ))}
            </select>
        </div>
    );
}

export default function AsignacionFacultadEdit({
    periodo, facultad, cargos, slots, candidatos, comision_estado, comision_bloqueada,
}) {
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
                                onChange={setCargo}
                                disabled={comision_bloqueada}
                                periodoId={periodo.id}
                                facultadId={facultad.id}
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
