<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Truck Zap') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
        <style>
            [x-cloak] { display: none !important; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .animate-progress { animation: progress 1.5s ease-in-out infinite; width: 30%; }
            @keyframes progress { 0% { transform: translateX(-100%); } 100% { transform: translateX(400%); } }
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </head>
    <body class="font-sans antialiased bg-slate-900 text-white selection:bg-blue-500/30 overflow-x-hidden" x-data="{ splash: true }" x-init="setTimeout(() => splash = false, 2000)">
        <!-- Splash Screen Overlay -->
        <div x-show="splash" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[100] bg-slate-900 flex flex-col items-center justify-center">
            <div class="relative">
                <div class="w-24 h-24 bg-blue-600 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-blue-500/40 animate-bounce">
                    <span class="text-4xl font-black text-white italic tracking-tighter">TZ</span>
                </div>
                <div class="absolute -inset-4 bg-blue-500/20 blur-3xl rounded-full animate-pulse"></div>
            </div>
            <h1 class="mt-8 text-3xl font-black text-white italic tracking-tighter" style="animation: fadeIn 1s forwards 0.5s opacity: 0;">Truck Zap</h1>
            <div class="mt-4 w-48 h-1 bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 animate-progress"></div>
            </div>
        </div>

        <div class="min-h-screen pb-24 flex flex-col md:flex-row" x-cloak x-show="!splash">
            <!-- Global Toast Notifications -->
            <div x-data="{ 
                toasts: [],
                addToast(notification) {
                    const id = Date.now();
                    this.toasts.push({ id, ...notification });
                    setTimeout(() => this.removeToast(id), 5000);
                },
                removeToast(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }
            }" 
            x-init="
                @auth
                    if (window.Echo) {
                        window.Echo.private('notifications.{{ Auth::id() }}')
                            .listen('.NotificationSent', (e) => {
                                addToast(e.notification);
                                $dispatch('notification-received');
                            });
                    }
                @endauth
            "
            class="fixed top-24 right-6 z-[200] space-y-4 w-80 pointer-events-none">
                <template x-for="toast in toasts" :key="toast.id">
                    <div x-show="true" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform translate-x-8"
                         x-transition:enter-end="opacity-100 transform translate-x-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 transform translate-x-0"
                         x-transition:leave-end="opacity-0 transform translate-x-8"
                         class="pointer-events-auto p-4 bg-slate-800/90 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl flex gap-4">
                        <div class="w-10 h-10 bg-blue-600/20 rounded-xl flex items-center justify-center text-blue-400 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-white" x-text="toast.title"></h4>
                            <p class="text-[10px] text-slate-400 mt-1 leading-tight" x-text="toast.message"></p>
                        </div>
                    </div>
                </template>
            </div>
            @auth
                @php
                    $isDashboard = request()->is('dashboard') || request()->is('dispatcher/dashboard');
                    $isCarrier = Auth::user()->role === 'carrier';
                    $unreadCount = $isCarrier ? \App\Models\Notification::where('user_id', Auth::id())->whereNull('read_at')->count() : 0;
                @endphp

                <!-- Mobile Top Header (Always for Carrier, conditionally for Dispatcher) -->
                <header class="fixed top-0 left-0 right-0 z-50 bg-slate-900/80 backdrop-blur-xl border-b border-white/10 px-6 py-4 flex items-center justify-between {{ Auth::user()->role === 'dispatcher' ? 'md:hidden' : '' }}">
                    <div class="flex items-center gap-4">
                        @if(!$isDashboard)
                            <button onclick="history.back()" class="flex items-center gap-2 text-slate-400 hover:text-white transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                            </button>
                        @endif
                        <h2 class="text-sm font-black italic text-white uppercase tracking-tighter">Truck Zap</h2>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($isCarrier)
                            <livewire:layout.notification-count />
                        @endif
                    </div>
                </header>

                @if(Auth::user()->role === 'dispatcher')
                    <!-- Sidebar (Desktop Dispatcher) -->
                    <aside class="hidden md:flex flex-col w-64 bg-slate-900 border-r border-white/10 p-6 space-y-8 fixed top-0 bottom-0 left-0 z-40">
                        <div class="flex items-center gap-3 mt-4">
                            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                                <span class="text-xl font-black italic text-white">TZ</span>
                            </div>
                            <h2 class="text-xl font-black italic text-white tracking-tighter">Truck Zap</h2>
                        </div>

                        <nav class="flex-1 space-y-2">
                            <a href="/dispatcher/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/dashboard') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                <span>Dashboard</span>
                            </a>
                            <a href="/dispatcher/loads" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/loads*') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V3.375c0-.621-.504-1.125-1.125-1.125h-1.5a3.375 3.375 0 0 1-3.375 3.375H9.75m10.5 11.25V3.375m-10.5 4.5a3.375 3.375 0 0 1-3.375-3.375h-1.5a1.125 1.125 0 0 0-1.125 1.125v12.75c0 .621.504 1.125 1.125 1.125H16.5M9.75 8.25h4.875c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H9.75V8.25Z" />
                                </svg>
                                <span>Load Management</span>
                            </a>
                            <a href="/dispatcher/carriers" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/carriers*') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                <span>Carriers</span>
                            </a>
                            <a href="/profile" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('profile') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.113-.94h1.088c.554 0 1.023.398 1.113.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774a1.125 1.125 0 0 1 .12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.894.15c.542.09.94.56.94 1.112v1.088c0 .554-.398 1.023-.94 1.113l-.894.149c-.424.07-.765.383-.93.78-.164.398-.142.854.108 1.204l.527.738a1.125 1.125 0 0 1-.12 1.45l-.773.773a1.125 1.125 0 0 1-1.45.12l-.737-.527c-.35-.25-.806-.272-1.204-.107-.397.165-.71.505-.78.93l-.15.894c-.09.542-.56.94-1.112.94h-1.088c-.554 0-1.023-.398-1.113-.94l-.149-.894c-.07-.424-.383-.765-.78-.93-.398-.164-.854-.142-1.204.108l-.738.527a1.125 1.125 0 0 1-1.45-.12l-.773-.773a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.112v-1.088c0-.554.398-1.023.94-1.113l.894-.149c.424-.07.765-.383.93-.78.164-.398.142-.854-.108-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.806.272 1.204.107.397-.165.71-.505.78-.93l.15-.894Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Settings</span>
                            </a>
                        </nav>

                        <div class="pt-6 border-t border-white/5 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-800 rounded-full flex items-center justify-center font-bold text-slate-300">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{{ Auth::user()->role }}</p>
                                </div>
                            </div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-red-500 hover:bg-red-500/10 transition-all text-xs font-bold uppercase tracking-widest">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                    </svg>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </aside>
                @endif
            @endauth

            <main class="flex-1 min-h-screen pt-20 {{ Auth::check() && Auth::user()->role === 'dispatcher' ? 'md:ml-64 md:pt-0' : 'max-w-md mx-auto md:max-w-none' }}">
                {{ $slot }}
            </main>

            @auth
                <!-- Bottom Navigation (Mobile/Universal) -->
                <nav class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900/80 backdrop-blur-xl border-t border-white/10 px-6 py-3 {{ Auth::user()->role === 'dispatcher' ? 'md:hidden' : '' }}">
                    <div class="max-w-md mx-auto flex items-center justify-between">
                        @if(Auth::user()->role === 'carrier')
                            <a href="/dashboard" class="flex flex-col items-center gap-1 {{ request()->is('dashboard') ? 'text-blue-500' : 'text-slate-400 hover:text-white transition-colors' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-tighter">Home</span>
                            </a>
                            <a href="/loads" class="flex flex-col items-center gap-1 {{ request()->is('loads*') ? 'text-blue-500' : 'text-slate-400 hover:text-white transition-colors' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-tighter">Find</span>
                            </a>
                            <a href="/document-upload" class="flex flex-col items-center gap-1 {{ request()->is('document-upload') ? 'text-blue-500' : 'text-slate-400 hover:text-white transition-colors' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-tighter">Docs</span>
                            </a>
                             <a href="/profile" class="flex flex-col items-center gap-1 {{ request()->is('profile') ? 'text-blue-500' : 'text-slate-400 hover:text-white transition-colors' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-tighter">Profile</span>
                            </a>
                        @else
                            <!-- Dispatcher Mobile Nav (Backup) -->
                            <a href="/dispatcher/dashboard" class="flex flex-col items-center gap-1 {{ request()->is('dispatcher/dashboard') ? 'text-blue-500' : 'text-slate-400' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-tighter">Home</span>
                            </a>
                        @endif
                    </div>
                </nav>
            @endauth
        </div>
        @livewireScripts
    </body>
</html>
