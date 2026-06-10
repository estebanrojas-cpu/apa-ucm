import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Expediente({ nomina }) {
    return (
        <>
            <Head title={`Expediente — ${nomina.academico.name}`} />
            <AppLayout title="Expediente (solo lectura)">
                <Link href="/vicerrectora/academicos" className="text-sm text-gray-500 hover:text-gray-700 mb-4 inline-block">
                    ← Volver a la lista
                </Link>
                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
                    <p className="font-semibold text-lg">{nomina.academico.name}</p>
                    <p className="text-sm text-gray-500">{nomina.academico.rut}</p>
                    <p className="text-sm text-gray-500">{nomina.academico.facultad} · {nomina.academico.categoria}</p>
                    <p className="text-sm text-gray-600">Estado expediente: {nomina.estado}</p>
                    <p className="text-sm text-gray-600">Evidencias cargadas: {nomina.evidencias_count}</p>
                    <p className="text-xs text-gray-400 mt-4">
                        Vista de solo lectura. La vicerrectoría no modifica calificaciones ni expedientes.
                    </p>
                </div>
            </AppLayout>
        </>
    );
}
