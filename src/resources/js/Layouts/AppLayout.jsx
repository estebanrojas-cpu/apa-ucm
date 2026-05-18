import { usePage, router, Link } from '@inertiajs/react';

const roleLabels = {
    admin:          'Administrador',
    analista_ccda:  'Analista CCDA',
    secretario:     'Secretario',
    miembro_cca:    'Miembro CCA',
    jefe_academico: 'Jefe Académico',
    academico:      'Académico',
};

const navByRole = {
    admin: [
        { label: 'Dashboard', href: '/admin/dashboard',    icon: 'grid' },
    ],
    analista_ccda: [
        { label: 'Dashboard', href: '/analista/dashboard', icon: 'grid' },
        { label: 'Períodos',  href: '/analista/periodos',  icon: 'calendar' },
    ],
    secretario: [
        { label: 'Dashboard', href: '/secretario/dashboard', icon: 'grid' },
    ],
    miembro_cca: [
        { label: 'Dashboard', href: '/cca/dashboard', icon: 'grid' },
    ],
    jefe_academico: [
        { label: 'Dashboard', href: '/jefe/dashboard', icon: 'grid' },
    ],
    academico: [
        { label: 'Dashboard', href: '/academico/dashboard', icon: 'grid' },
    ],
};

function NavIcon({ type }) {
    if (type === 'grid') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
        </svg>
    );
    if (type === 'calendar') return (
        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    );
    return null;
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
    const { auth } = page.props;
    const user = auth.user;
    const currentUrl = page.url;

    const navItems = navByRole[user.role] ?? [];

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    return (
        <div className="min-h-screen flex flex-col bg-gray-50">

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

            <div className="flex flex-1 overflow-hidden">

                {/* Sidebar */}
                <aside className="w-56 bg-[#152558] flex flex-col shrink-0 z-10">
                    <nav className="flex-1 px-3 py-5 space-y-1">
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

                    <div className="px-4 py-4 border-t border-white/10">
                        <p className="text-blue-300 text-[10px] opacity-40 leading-snug">
                            © {new Date().getFullYear()} UCM<br />
                            Vicerrectoría Académica
                        </p>
                    </div>
                </aside>

                {/* Contenido principal */}
                <main className="flex-1 overflow-auto">
                    <div className="p-6 sm:p-8 max-w-6xl">
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
