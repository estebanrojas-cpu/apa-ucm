import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

const ROLE_LABELS = {
    super_admin:           'Super Administrador',
    analista_ccda:         'Analista CCDA',
    vicerrectora:          'Vicerrectoría',
    director_departamento: 'Director de Departamento',
};

export default function Usuarios({ usuarios, facultades, departamentos, rolesDisponibles }) {
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        name:            '',
        email:           '',
        password:        '',
        role:            'analista_ccda',
        facultad_id:     '',
        departamento_id: '',
        rut:             '',
    });

    const facSeleccionada = facultades.find(f => f.id === form.data.facultad_id);
    const deptsFiltrados = departamentos.filter(
        d => !form.data.facultad_id || d.facultad === facSeleccionada?.nombre
    );

    function submit(e) {
        e.preventDefault();
        form.post('/super-admin/usuarios', {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    }

    function toggleActivo(userId) {
        router.patch(`/super-admin/usuarios/${userId}/activo`);
    }

    return (
        <>
            <Head title="Usuarios institucionales" />
            <AppLayout title="Usuarios institucionales">

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
                    Gestione analistas CCDA, vicerrectoría y directores de departamento.
                    Los cargos de facultad (secretario, decano, CCA) se asignan por período desde el panel del analista.
                </p>

                <div className="flex justify-end mb-4">
                    <button
                        type="button"
                        onClick={() => setShowForm(s => !s)}
                        className="bg-[#1B2D6B] text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-[#152558]"
                    >
                        {showForm ? 'Cancelar' : '+ Nuevo usuario'}
                    </button>
                </div>

                {showForm && (
                    <form onSubmit={submit} className="bg-white rounded-xl border border-gray-200 p-5 mb-6 space-y-4 max-w-xl">
                        <h2 className="font-semibold text-gray-800 text-sm">Crear usuario institucional</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={e => form.setData('name', e.target.value)}
                                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                />
                                {form.errors.name && <p className="text-xs text-red-600 mt-1">{form.errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Email</label>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={e => form.setData('email', e.target.value)}
                                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                />
                                {form.errors.email && <p className="text-xs text-red-600 mt-1">{form.errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Contraseña</label>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={e => form.setData('password', e.target.value)}
                                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                />
                                {form.errors.password && <p className="text-xs text-red-600 mt-1">{form.errors.password}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">RUT (opcional)</label>
                                <input
                                    type="text"
                                    value={form.data.rut}
                                    onChange={e => form.setData('rut', e.target.value)}
                                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Rol</label>
                                <select
                                    value={form.data.role}
                                    onChange={e => form.setData('role', e.target.value)}
                                    className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                >
                                    {rolesDisponibles.map(r => (
                                        <option key={r.value} value={r.value}>{r.label}</option>
                                    ))}
                                </select>
                            </div>

                            {form.data.role === 'director_departamento' && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Facultad</label>
                                        <select
                                            value={form.data.facultad_id}
                                            onChange={e => form.setData({ ...form.data, facultad_id: e.target.value, departamento_id: '' })}
                                            className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                        >
                                            <option value="">Seleccione...</option>
                                            {facultades.map(f => (
                                                <option key={f.id} value={f.id}>{f.nombre}</option>
                                            ))}
                                        </select>
                                        {form.errors.facultad_id && <p className="text-xs text-red-600 mt-1">{form.errors.facultad_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                                        <select
                                            value={form.data.departamento_id}
                                            onChange={e => form.setData('departamento_id', e.target.value)}
                                            className="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"
                                            disabled={!form.data.facultad_id}
                                        >
                                            <option value="">Seleccione...</option>
                                            {deptsFiltrados.map(d => (
                                                <option key={d.id} value={d.id}>{d.nombre}</option>
                                            ))}
                                        </select>
                                        {form.errors.departamento_id && <p className="text-xs text-red-600 mt-1">{form.errors.departamento_id}</p>}
                                    </div>
                                </>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-[#0096D6] text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-[#0080b8] disabled:opacity-50"
                        >
                            {form.processing ? 'Guardando...' : 'Crear usuario'}
                        </button>
                    </form>
                )}

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-5 py-3 font-semibold text-gray-600">Nombre</th>
                                <th className="text-left px-5 py-3 font-semibold text-gray-600">Email</th>
                                <th className="text-left px-5 py-3 font-semibold text-gray-600">Roles</th>
                                <th className="text-left px-5 py-3 font-semibold text-gray-600">Facultad / Depto</th>
                                <th className="text-left px-5 py-3 font-semibold text-gray-600">Estado</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {usuarios.map(u => (
                                <tr key={u.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-3 font-medium text-gray-900">{u.name}</td>
                                    <td className="px-5 py-3 text-gray-600">{u.email}</td>
                                    <td className="px-5 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.map(r => (
                                                <span key={r} className="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                                                    {ROLE_LABELS[r] ?? r}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-5 py-3 text-gray-600 text-xs">
                                        {u.facultad ?? '—'}
                                        {u.departamento && <><br />{u.departamento}</>}
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${u.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {u.activo ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        {!u.roles.includes('super_admin') && (
                                            <button
                                                type="button"
                                                onClick={() => toggleActivo(u.id)}
                                                className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                            >
                                                {u.activo ? 'Desactivar' : 'Activar'}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AppLayout>
        </>
    );
}
