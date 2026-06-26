import { usePage, router } from '@inertiajs/react';

const roleLabels = {
    analista_ccda:  'Analista CCDA',
    secretario:     'Secretario de Facultad',
    miembro_cca:    'Miembro CCA',
    jefe_academico: 'Jefe Académico',
    vicerrectora:   'Vicerrectoría',
    academico:      'Académico',
};

const roleDescriptions = {
    analista_ccda:  'Gestión de períodos, nóminas, comisiones CCA y verificación institucional.',
    secretario:     'Administración de expedientes y plazos de su facultad.',
    miembro_cca:    'Revisión y calificación de expedientes asignados.',
    jefe_academico: 'Emisión de informes de jefatura por académico.',
    vicerrectora:   'Consulta global de calificaciones y comentarios.',
    academico:      'Carga de evidencias y seguimiento de su proceso APA.',
};

const roleIcons = {
    admin: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    ),
    analista_ccda: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
        </svg>
    ),
    secretario: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
        </svg>
    ),
    miembro_cca: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        </svg>
    ),
    jefe_academico: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    ),
    vicerrectora: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    ),
    academico: (
        <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
        </svg>
    ),
};

export default function SeleccionarPerfil({ roles, active_role }) {
    const { auth } = usePage().props;
    const user = auth.user;

    function elegirPerfil(role) {
        router.post('/cambiar-perfil', { role });
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#1B2D6B] to-[#0f1e4a] flex flex-col items-center justify-center p-6">

            {/* Logo */}
            <div className="mb-8 flex flex-col items-center gap-3">
                <div className="bg-white rounded-xl px-4 py-2">
                    <img src="/img/logo_ucm.png" alt="UCM" className="h-10 w-auto" />
                </div>
                <p className="text-blue-200 text-sm opacity-70 tracking-wide uppercase">
                    Sistema de Calificaciones Académicas
                </p>
            </div>

            <div className="w-full max-w-lg">
                <div className="text-center mb-6">
                    <h1 className="text-white text-2xl font-bold">
                        ¿Con qué perfil ingresas?
                    </h1>
                    <p className="text-blue-300 text-sm mt-1 opacity-70">
                        Bienvenido/a, <span className="font-medium text-blue-200">{user.name}</span>.
                        Tienes acceso con múltiples perfiles.
                    </p>
                </div>

                <div className="grid gap-3">
                    {roles.map(role => (
                        <button
                            key={role}
                            onClick={() => elegirPerfil(role)}
                            className={`
                                group flex items-center gap-4 p-4 rounded-xl border text-left transition-all duration-150
                                ${role === active_role
                                    ? 'bg-[#0096D6]/20 border-[#0096D6]/60 ring-1 ring-[#0096D6]/40'
                                    : 'bg-white/5 border-white/10 hover:bg-white/10 hover:border-white/25'}
                            `}
                        >
                            <div className={`
                                flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center
                                ${role === active_role ? 'bg-[#0096D6]/30 text-[#5bc8f5]' : 'bg-white/10 text-blue-300 group-hover:text-white'}
                            `}>
                                {roleIcons[role] ?? (
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                )}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className={`font-semibold text-sm ${role === active_role ? 'text-white' : 'text-blue-100 group-hover:text-white'}`}>
                                    {roleLabels[role] ?? role}
                                </p>
                                <p className="text-blue-400 text-xs mt-0.5 leading-snug opacity-80">
                                    {roleDescriptions[role] ?? ''}
                                </p>
                            </div>
                            {role === active_role && (
                                <span className="flex-shrink-0 text-[10px] font-bold text-[#5bc8f5] bg-[#0096D6]/20 px-2 py-0.5 rounded-full uppercase tracking-wider">
                                    Activo
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                <div className="mt-6 text-center">
                    <button
                        onClick={() => router.post('/logout')}
                        className="text-blue-400 hover:text-blue-200 text-xs transition-colors"
                    >
                        Cerrar sesión
                    </button>
                </div>
            </div>
        </div>
    );
}
