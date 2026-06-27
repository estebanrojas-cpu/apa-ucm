<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Facultad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function index(): Response
    {
        $usuarios = User::with(['userRoles', 'facultad', 'departamento'])
            ->where(function ($q) {
                $q->whereHas('userRoles', fn ($q) => $q->whereIn('role', [
                    'super_admin', 'analista_ccda', 'vicerrectora', 'director_departamento',
                ]))
                ->orWhereIn('role', ['super_admin', 'analista_ccda', 'vicerrectora', 'director_departamento']);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'rut'          => $u->rut,
                'activo'       => $u->activo,
                'roles'        => $u->assignedRoles(),
                'facultad'     => $u->facultad?->nombre,
                'departamento' => $u->departamento?->nombre,
            ]);

        return Inertia::render('SuperAdmin/Usuarios', [
            'usuarios'      => $usuarios,
            'facultades'    => Facultad::orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'departamentos' => Departamento::with('facultad')->orderBy('nombre')->get()->map(fn ($d) => [
                'id'       => $d->id,
                'nombre'   => $d->nombre,
                'facultad' => $d->facultad?->nombre,
            ]),
            'rolesDisponibles' => [
                ['value' => 'analista_ccda', 'label' => 'Analista CCDA'],
                ['value' => 'vicerrectora', 'label' => 'Vicerrectoría'],
                ['value' => 'director_departamento', 'label' => 'Director de Departamento'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:200'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:6'],
            'role'             => ['required', Rule::in(['analista_ccda', 'vicerrectora', 'director_departamento'])],
            'facultad_id'      => ['nullable', 'uuid', 'exists:facultades,id'],
            'departamento_id'  => ['nullable', 'uuid', 'exists:departamentos,id'],
            'rut'              => ['nullable', 'string', 'max:20'],
        ]);

        if ($data['role'] === 'director_departamento') {
            $request->validate([
                'facultad_id'     => ['required', 'uuid', 'exists:facultades,id'],
                'departamento_id' => ['required', 'uuid', 'exists:departamentos,id'],
            ]);
        }

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'role'            => $data['role'],
            'rut'             => $data['rut'] ?? null,
            'facultad_id'     => $data['facultad_id'] ?? null,
            'departamento_id' => $data['departamento_id'] ?? null,
            'activo'          => true,
        ]);

        $user->syncUserRoles([$data['role'], 'academico']);

        return back()->with('success', 'Usuario institucional creado.');
    }

    public function toggleActivo(User $user)
    {
        if ($user->hasAnyAssignedRole('super_admin')) {
            return back()->with('error', 'No puede desactivar al super administrador.');
        }

        $user->update(['activo' => !$user->activo]);

        return back()->with('success', $user->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }
}
