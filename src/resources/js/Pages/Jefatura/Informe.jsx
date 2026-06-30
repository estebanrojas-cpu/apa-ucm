import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Informe({ nomina, informe }) {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        observacion: informe?.observacion_general ?? '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/jefe/academicos/${nomina.id}/informe`);
    }

    return (
        <>
            <Head title={`Informe — ${nomina.academico.name}`} />
            <AppLayout title="Informe de Jefatura">

                <div className="-mt-4 mb-6 flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <Link href="/jefe/academicos" className="hover:text-gray-700">Académicos</Link>
                        <span>/</span>
                        <span className="text-gray-700 font-medium">{nomina.academico.name}</span>
                    </div>
                    {informe && (
                        <a
                            href={`/jefe/academicos/${nomina.id}/imprimir`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1B2D6B] text-white text-xs font-medium rounded-lg hover:bg-[#152558]"
                        >
                            Ver / Imprimir PDF
                        </a>
                    )}
                </div>

                {flash?.success && (
                    <div className="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                        {flash.success}
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                    <p className="font-semibold text-gray-900">{nomina.academico.name}</p>
                    <p className="text-sm text-gray-500">{nomina.academico.rut} · {nomina.academico.email}</p>
                    {nomina.academico.departamento && (
                        <p className="text-sm text-gray-400 mt-0.5">{nomina.academico.departamento}</p>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <label className="block text-sm font-semibold text-gray-800 mb-2">
                            Observaciones
                        </label>
                        <p className="text-xs text-gray-500 mb-3">
                            Estas observaciones quedarán registradas en el informe institucional enviado al académico/a.
                        </p>
                        <textarea
                            rows={8}
                            value={data.observacion}
                            onChange={e => setData('observacion', e.target.value)}
                            placeholder="Escriba las observaciones sobre el desempeño del académico/a..."
                            className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0096D6]/30 resize-none"
                        />
                        {errors.observacion && (
                            <p className="text-xs text-red-600 mt-1">{errors.observacion}</p>
                        )}
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-[#1B2D6B] hover:bg-[#152558] disabled:opacity-50 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors"
                        >
                            {processing ? 'Guardando...' : informe ? 'Actualizar informe' : 'Emitir informe'}
                        </button>
                    </div>
                </form>
            </AppLayout>
        </>
    );
}
