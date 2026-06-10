import { usePage, router, Link, Head } from '@inertiajs/react';

const roleLabels = {
    admin:          'Administrador',
    analista_ccda:  'Analista CCDA',
    secretario:     'Secretario',
    miembro_cca:    'Miembro CCA',
    jefe_academico: 'Jefe Académico',
    vicerrectora:   'Vicerrectoría',
    academico:      'Académico',
};

const navByRole = {
    admin: [
        { label: 'Dashboard', href: '/admin/dashboard',    icon: 'grid' },
    ],
    analista_ccda: [
        { label: 'Dashboard',      href: '/analista/dashboard',      icon: 'grid' },
        { label: 'Períodos',       href: '/analista/periodos',       icon: 'calendar' },
        { label: 'Solicitudes',    href: '/analista/solicitudes',    icon: 'folder' },
        { label: 'Estado proceso', href: '/analista/estado-proceso', icon: 'chart' },
    ],
    secretario: [
        { label: 'Dashboard',   href: '/secretario/dashboard',   icon: 'grid' },
        { label: 'Expedientes', href: '/secretario/expedientes', icon: 'folder' },
        { label: 'Solicitudes', href: '/secretario/solicitudes', icon: 'folder' },
    ],
    miembro_cca: [
        { label: 'Dashboard',   href: '/cca/dashboard',   icon: 'grid' },
        { label: 'Expedientes', href: '/cca/expedientes', icon: 'folder' },
    ],
    jefe_academico: [
        { label: 'Dashboard',  href: '/jefe/dashboard',  icon: 'grid' },
        { label: 'Académicos', href: '/jefe/academicos', icon: 'folder' },
    ],
    vicerrectora: [
        { label: 'Dashboard',   href: '/vicerrectora/dashboard',   icon: 'grid' },
        { label: 'Calificaciones', href: '/vicerrectora/academicos', icon: 'folder' },
    ],
    academico: [
        { label: 'Dashboard',  href: '/academico/dashboard',  icon: 'grid' },
        { label: 'Evidencias', href: '/academico/evidencias', icon: 'upload' },
    ],
};

function NavIcon({ type }) {
    if (type === 'grid') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
        </svg>
    );
    if (type === 'folder') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
        </svg>
    );
    if (type === 'calendar') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    );
    if (type === 'upload') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>
    );
    if (type === 'chart') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
    );
    return null;
}

function BellIcon() {
    return (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
    );
}

function LogoutIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
        </svg>
    );
}

export default function AppLayout({ title, children }) {
    const page = usePage();
    const { auth, notificaciones_no_leidas: nNotif } = page.props;
    const user = auth.user;
    const currentUrl = page.url;

    const navItems = navByRole[user.role] ?? [];

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    return (
        <div className="fixed inset-0 flex flex-col overflow-hidden bg-gray-50">

            {/* Header */}
            <header className="bg-[#1B2D6B] shadow-md z-20 shrink-0">
                <div className="flex items-center justify-between h-16 px-4 sm:px-6">

                    <div className="flex items-center gap-3">
                        <div className="bg-white rounded-lg px-2 py-1 flex items-center shrink-0">
                            <img
                                src="/img/logo_ucm.png"
                                alt="UCM"
                                className="h-7 w-auto"
                            />
                        </div>
                        <div className="hidden sm:block border-l border-white/20 pl-3">
                            <p className="text-white text-xs font-semibold leading-tight tracking-wide uppercase opacity-90">
                                Calificaciones Académicas
                            </p>
                            <p className="text-blue-200 text-[11px] leading-tight opacity-60">
                                Sistema de Gestión · UCM
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="text-right hidden sm:block">
                            <p className="text-white text-sm font-medium leading-tight">
                                {user.name}
                            </p>
                            <p className="text-blue-200 text-xs opacity-70">
                                {roleLabels[user.role] ?? user.role}
                            </p>
                        </div>
                        <div className="w-8 h-8 rounded-full bg-[#0096D6] flex items-center justify-center text-white text-sm font-bold shrink-0">
                            {user.name?.charAt(0).toUpperCase()}
                        </div>
                        <Link
                            href="/notificaciones"
                            title="Notificaciones"
                            className="relative text-blue-200 hover:text-white transition-colors"
                        >
                            <BellIcon />
                            {nNotif > 0 && (
                                <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white leading-none">
                                    {nNotif > 9 ? '9+' : nNotif}
                                </span>
                            )}
                        </Link>
                        <button
                            onClick={logout}
                            title="Cerrar sesión"
                            className="flex items-center gap-1.5 text-blue-200 hover:text-white text-xs transition-colors"
                        >
                            <LogoutIcon />
                            <span className="hidden sm:inline">Salir</span>
                        </button>
                    </div>
                </div>
            </header>

            <div className="flex min-h-0 flex-1 overflow-hidden">

                {/* Sidebar — altura fija al viewport; sin scroll propio salvo menú largo */}
                <aside className="flex h-full w-56 shrink-0 flex-col bg-[#152558] z-10">
                    <nav className="flex-1 overflow-y-auto overscroll-contain px-3 py-5 space-y-1">
                        <p className="text-blue-300 text-[10px] font-semibold uppercase tracking-widest px-3 mb-3 opacity-50">
                            Menú
                        </p>
                        {navItems.map(item => {
                            const active = currentUrl.startsWith(item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`
                                        flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                        ${active
                                            ? 'bg-[#0096D6]/20 text-white'
                                            : 'text-blue-200 hover:bg-white/5 hover:text-white'}
                                    `}
                                >
                                    <NavIcon type={item.icon} />
                                    {item.label}
                                    {active && (
                                        <span className="ml-auto w-1.5 h-1.5 rounded-full bg-[#0096D6]" />
                                    )}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="shrink-0 px-4 py-4 border-t border-white/10">
                        <p className="text-blue-300 text-[10px] opacity-40 leading-snug">
                            © {new Date().getFullYear()} UCM<br />
                            Vicerrectoría Académica
                        </p>
                    </div>
                </aside>

                {/* Contenido principal — único scroll de la página */}
                <main className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-gray-50">
                    <div className="min-h-full w-full p-6 sm:p-8">
                        {title && (
                            <h1 className="text-xl font-bold text-gray-900 mb-6 tracking-tight">
                                {title}
                            </h1>
                        )}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
