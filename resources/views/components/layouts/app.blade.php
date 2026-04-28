@php
    // v93: Optimized boot (Diagnostics Removed)
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TruckZap') }}</title>

        <!-- Status Bar Theming -->
        <meta name="color-scheme" content="dark">
        <meta name="theme-color" content="#0f172a">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- NativePHP Mobile Bridge -->
        <script src="/native.js?v=1.0.0"></script>

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
            
            /* High-Performance Glass Replacements (v26) */
            .glass-morphism {
                background: rgba(30, 41, 59, 0.8) !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            .glass {
                background: rgba(255, 255, 255, 0.05) !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }

            /* v89: Mobile Safe Area Hardening */
            :root {
                --sat: env(safe-area-inset-top);
                --sab: env(safe-area-inset-bottom);
            }
            .safe-top { top: calc(2.5rem + var(--sat, 0px)); }
            .safe-bottom { bottom: calc(2.2rem + var(--sab, 0px)); }
            .safe-pt { padding-top: calc(5.5rem + var(--sat, 0px)); }
            @keyframes roadScroll {
                from { stroke-dashoffset: 0; }
                to   { stroke-dashoffset: -40; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-slate-900 text-white selection:bg-blue-500/30 overflow-x-hidden relative" 
          x-data="{ splash: !sessionStorage.getItem('splash_shown'), isOnline: navigator.onLine, loggingOut: false }" 
          x-init="
        if (splash) {
            setTimeout(() => {
                splash = false;
                sessionStorage.setItem('splash_shown', 'true');
            }, 1200);
        }
        window.addEventListener('online', () => isOnline = true);
        window.addEventListener('offline', () => isOnline = false);
    ">
        <!-- Global Background Decorative Elements (Carrier only) - v27 Optimized -->
        @auth @if(Auth::user()->role === 'carrier')
            <div class="fixed inset-0 z-0 pointer-events-none overflow-hidden bg-slate-900">
                <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-blue-600/5 rounded-full"></div>
                <div class="absolute -bottom-[10%] right-[10%] w-[40%] h-[40%] bg-indigo-600/5 rounded-full"></div>
            </div>
            
        @endif @endauth

        <!-- Splash Screen Overlay -->
        <div x-show="splash" x-cloak x-transition:leave="transition ease-in duration-700" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-110" class="fixed inset-0 z-[100] bg-slate-900 flex flex-col items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 via-transparent to-slate-900/50"></div>
            <div class="relative z-10 scale-125">
                <div class="w-24 h-24 bg-blue-gradient rounded-[2rem] flex items-center justify-center shadow-[0_0_50px_rgba(37,99,235,0.4)] animate-float overflow-hidden">
                    <img src="/logo-icon.png" class="w-full h-full object-cover p-4" alt="Truckerz App Icon">
                </div>
                <div class="absolute -inset-8 bg-blue-500/20 blur-3xl rounded-full animate-pulse"></div>
            </div>
            <div class="mt-12 flex flex-col items-center gap-4 relative z-10 animate-fadeIn" style="animation-delay: 0.5s; opacity: 0;">
                <img src="/logo-icon.png" class="h-20 w-auto object-contain" alt="">
                <div class="flex items-baseline">
                    <span class="text-4xl font-black italic text-blue-500 tracking-tight uppercase leading-none">Truckerz</span>
                    <span class="text-4xl font-black italic text-white tracking-tight uppercase leading-none">App</span>
                </div>
            </div>
            <div class="mt-6 w-48 h-1 bg-white/5 rounded-full overflow-hidden relative z-10">
                <div class="h-full bg-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.8)] animate-progress"></div>
            </div>
        </div>

        <!-- Global Connectivity Guard (v79) -->
        <div x-show="!isOnline && !splash" x-cloak class="fixed inset-0 z-[300] bg-slate-900/95 backdrop-blur-2xl flex flex-col items-center justify-center px-10 text-center animate-fadeIn">
            <div class="w-20 h-20 bg-red-500/10 rounded-3xl flex items-center justify-center mb-8 border border-red-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-red-500 animate-pulse">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h2 class="text-3xl font-black text-white italic uppercase tracking-tighter mb-4">Signal Lost</h2>
            <p class="text-slate-400 font-bold uppercase text-[10px] tracking-widest leading-relaxed">Please connect to the internet to continue syncing your loads.</p>
            <button onclick="window.location.reload()" class="mt-10 px-8 py-4 bg-white/5 border border-white/10 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white hover:bg-white/10 transition-all active:scale-95 shadow-2xl">
                Manual Retry
            </button>
        </div>

        <!-- Global Logout Overlay (v92) -->
        <div x-show="loggingOut" x-cloak class="fixed inset-0 z-[400] bg-slate-900/90 backdrop-blur-2xl flex flex-col items-center justify-center animate-fadeIn">
            <div class="relative">
                <div class="w-24 h-24 bg-blue-gradient rounded-[2rem] flex items-center justify-center shadow-[0_0_50px_rgba(37,99,235,0.4)] animate-pulse">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                    </svg>
                </div>
                <div class="absolute -inset-4 bg-blue-500/20 blur-2xl rounded-full animate-ping"></div>
            </div>
            <h2 class="mt-12 text-3xl font-black text-white italic uppercase tracking-tighter">Logging Out</h2>
            <p class="mt-2 text-slate-400 font-bold uppercase text-[10px] tracking-widest">Securing your session...</p>
            
            <div class="mt-8 flex gap-1">
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce"></div>
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
                    // Original Echo listener
                    if (window.Echo) {
                        window.Echo.private('notifications.{{ Auth::id() }}')
                            .listen('.NotificationSent', (e) => {
                                addToast(e.notification);
                                $dispatch('notification-received');
                            });
                    }
                @endauth
            "
            @new-notification.window="addToast($event.detail)"
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
                    // $unreadCount = $isCarrier ? \App\Models\Notification::where('user_id', Auth::id())->whereNull('read_at')->count() : 0;
                @endphp

                <!-- Mobile Top Header (Carrier Redesign) -->
                @if($isCarrier)
                <header class="fixed safe-top left-4 right-4 z-50 glass-morphism rounded-2xl px-5 py-3.5 flex items-center justify-between border border-white/10 glow-blue">
                    <div class="flex items-center gap-4">
                        @if(!$isDashboard)
                            <button onclick="history.back()" wire:navigate class="p-2 bg-white/5 rounded-xl text-slate-400 hover:text-white transition-all active:scale-90 overflow-hidden relative group">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                            </button>
                        @endif
                        <div class="flex items-center gap-1.5">
                            <img src="/logo-icon.png" class="h-4 w-auto object-contain" alt="">
                            <div class="flex items-baseline whitespace-nowrap">
                                <span class="text-[12px] font-black italic text-blue-500 tracking-tight uppercase leading-none">Truckerz</span>
                                <span class="text-[12px] font-black italic text-white tracking-tight uppercase leading-none">App</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">

                        <div class="w-8 h-8 rounded-full bg-blue-gradient p-[1px] shadow-lg shadow-blue-500/20">
                        <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-[10px] font-black italic text-white uppercase">
                            {{ substr(trim(Auth::user()->name ?? 'U'), 0, 1) }}
                        </div>
                    </div>
                    </div>
                </header>
                @else
                <!-- Original Mobile Top Header (Dispatcher) -->
                <header class="fixed top-0 left-0 right-0 z-50 bg-slate-900/80 backdrop-blur-xl border-b border-white/10 px-6 pt-[calc(1rem+var(--sat,0px))] pb-4 flex items-center justify-between md:hidden">
                    <div class="flex items-center gap-4">
                        @if(!$isDashboard)
                            <button onclick="history.back()" wire:navigate class="flex items-center gap-2 text-slate-400 hover:text-white transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                            </button>
                        @endif
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-black italic text-white uppercase tracking-tighter">Truckerz App</h2>
                        </div>
                    </div>
                </header>
                @endif

                @if(Auth::user()->role === 'dispatcher')
                    <!-- Sidebar (Desktop Dispatcher) -->
                    <aside class="flex flex-col w-20 lg:w-64 bg-slate-900 border-r border-white/10 p-4 lg:p-6 space-y-8 fixed top-0 bottom-0 left-0 z-40">
                        <div class="flex items-center gap-3 mt-4">
                            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
                                <span class="text-xl font-black italic text-white">TZ</span>
                            </div>
                            <h2 class="text-xl font-black italic text-white tracking-tighter hidden lg:block">Truckerz App</h2>
                        </div>

                        <nav class="flex-1 space-y-2">
                            <a href="/dispatcher/dashboard" wire:navigate class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/dashboard') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                <span class="hidden lg:block">Dashboard</span>
                            </a>
                            <a href="/dispatcher/loads" wire:navigate class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/loads*') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0 m3 0a1.5 1.5 0 0 0-3 0 m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0 m3 0a1.5 1.5 0 0 0-3 0 m3 0h1.125c.621 0 1.125-.504 1.125-1.125V3.375c0-.621-.504-1.125-1.125-1.125h-1.5a3.375 3.375 0 0 1-3.375 3.375H9.75 m10.5 11.25V3.375 m-10.5 4.5a3.375 3.375 0 0 1-3.375-3.375h-1.5a1.125 1.125 0 0 0-1.125 1.125v12.75c0 .621.504 1.125 1.125 1.125H16.5M9.75 8.25h4.875c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H9.75V8.25Z" />
                                </svg>
                                <span class="hidden lg:block">Load Management</span>
                            </a>
                            <a href="/dispatcher/carriers" wire:navigate class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('dispatcher/carriers*') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                <span class="hidden lg:block">Carriers</span>
                            </a>
                            <a href="/profile" wire:navigate class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all {{ request()->is('profile') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.113-.94h1.088c.554 0 1.023.398 1.113.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774a1.125 1.125 0 0 1 .12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.894.15c.542.09.94.56.94 1.112v1.088c0 .554-.398 1.023-.94 1.113l-.894.149c-.424.07-.765.383-.93.78-.164.398-.142.854.108 1.204l.527.738a1.125 1.125 0 0 1-.12 1.45l-.773.773a1.125 1.125 0 0 1-1.45.12l-.737-.527c-.35-.25-.806-.272-1.204-.107-.397.165-.71.505-.78.93l-.15.894c-.09.542-.56.94-1.112.94h-1.088c-.554 0-1.023-.398-1.113-.94l-.149-.894c-.07-.424-.383-.765-.78-.93-.398-.164-.854-.142-1.204.108l-.738.527a1.125 1.125 0 0 1-1.45-.12l-.773-.773a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.112v-1.088c0-.554.398-1.023.94-1.113l.894-.149c.424-.07.765-.383.93-.78.164-.398.142-.854-.108-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.806.272 1.204.107.397-.165.71-.505.78-.93l.15-.894Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span class="hidden lg:block">Settings</span>
                            </a>
                        </nav>

                        <div class="pt-6 border-t border-white/5 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-800 rounded-full flex items-center justify-center font-bold text-slate-300 uppercase">
                                    {{ substr(trim(Auth::user()->name ?? 'U'), 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{{ Auth::user()->role }}</p>
                                </div>
                            </div>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" x-on:submit="loggingOut = true">
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

            <main class="flex-1 min-h-screen {{ Auth::check() && Auth::user()->role === 'carrier' ? 'safe-pt' : 'pt-20' }} {{ Auth::check() && Auth::user()->role === 'dispatcher' ? 'md:ml-64 md:pt-0' : 'max-w-md mx-auto md:max-w-none' }}">
                <livewire:notification-manager />
                {{ $slot }}
            </main>

            @auth
                <!-- Bottom Navigation (Carrier Redesign) -->
                @if($isCarrier)
                <nav class="fixed safe-bottom left-6 right-6 z-50">
                    <div class="max-w-md mx-auto glass-morphism rounded-[2rem] px-2 py-2 border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.5)] flex items-center justify-between">
                        <a href="/dashboard" wire:navigate class="relative group flex-1 py-3 flex flex-col items-center gap-1 transition-all duration-300 {{ request()->is('dashboard') ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}">
                            @if(request()->is('dashboard'))
                                <div class="absolute inset-0 bg-blue-gradient rounded-2xl opacity-100 scale-100 transition-all duration-300 shadow-lg shadow-blue-500/40"></div>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10 transition-transform duration-300 group-active:scale-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-tighter relative z-10">Home</span>
                        </a>
                        
                        <a href="/loads" wire:navigate class="relative group flex-1 py-3 flex flex-col items-center gap-1 transition-all duration-300 {{ request()->is('loads*') ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}">
                            @if(request()->is('loads*'))
                                <div class="absolute inset-0 bg-blue-gradient rounded-2xl opacity-100 scale-100 transition-all duration-300 shadow-lg shadow-blue-500/40"></div>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10 transition-transform duration-300 group-active:scale-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-tighter relative z-10">Find</span>
                        </a>

                        <a href="/my-requests" wire:navigate class="relative group flex-1 py-3 flex flex-col items-center gap-1 transition-all duration-300 {{ request()->is('my-requests') ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}">
                            @if(request()->is('my-requests'))
                                <div class="absolute inset-0 bg-blue-gradient rounded-2xl opacity-100 scale-100 transition-all duration-300 shadow-lg shadow-blue-500/40"></div>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10 transition-transform duration-300 group-active:scale-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-tighter relative z-10">Bids</span>
                        </a>

                        <a href="/fleet" wire:navigate class="relative group flex-1 py-3 flex flex-col items-center gap-1 transition-all duration-300 {{ request()->is('fleet*') ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}">
                            @if(request()->is('fleet*'))
                                <div class="absolute inset-0 bg-blue-gradient rounded-2xl opacity-100 scale-100 transition-all duration-300 shadow-lg shadow-blue-500/40"></div>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10 transition-transform duration-300 group-active:scale-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-tighter relative z-10">Hub</span>
                        </a>

                        <a href="/profile" wire:navigate class="relative group flex-1 py-3 flex flex-col items-center gap-1 transition-all duration-300 {{ request()->is('profile') ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}">
                            @if(request()->is('profile'))
                                <div class="absolute inset-0 bg-blue-gradient rounded-2xl opacity-100 scale-100 transition-all duration-300 shadow-lg shadow-blue-500/40"></div>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10 transition-transform duration-300 group-active:scale-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-tighter relative z-10">Profile</span>
                        </a>
                    </div>
                </nav>
                @else
                <!-- Original Bottom Navigation (Dispatcher) -->
                <nav class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900/80 backdrop-blur-xl border-t border-white/10 px-6 py-3 md:hidden">
                    <div class="max-w-md mx-auto flex items-center justify-between">
                        <a href="/dispatcher/dashboard" class="flex flex-col items-center gap-1 {{ request()->is('dispatcher/dashboard') ? 'text-blue-500' : 'text-slate-400' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            <span class="text-[10px] font-black uppercase tracking-tighter">Home</span>
                        </a>
                    </div>
                </nav>
                @endif
            @endauth
        </div>
        @livewireScripts
        <script type="module">
            /**
             * Physical Bridge Globalization (v23)
             * Loading the manually extracted bridge from /public/_native/native.js
             */
            import * as Native from '/_native/native.js';
            
            window.Native = Native;
            console.log('Physical Native Bridge Initialized:', window.Native);
            
            // Dispatch event for any late-loading components
            document.dispatchEvent(new CustomEvent('native-ready'));
            
            // Update UI Status
            const bridgeElement = document.getElementById('bridge-status');
            if (bridgeElement) {
                bridgeElement.innerText = 'BRIDGE: ACTIVE';
                bridgeElement.className = 'text-[10px] font-bold mt-1 text-green-500';
            }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // v103: Handle Native Notification Clicks
                if (window.Native && typeof window.Native.onNotificationClick === 'function') {
                    window.Native.onNotificationClick((data) => {
                        console.log('Notification Clicked:', data);
                        if (data && data.url) {
                            // Use window.location instead of wire:navigate for external/bridge-triggered jumps
                            window.location.href = data.url;
                        } else if (data && data.load_id) {
                            window.location.href = '/loads';
                        }
                    });
                } else {
                    // Fallback: Listen for generic events from the bridge if the callback isn't registered
                    window.addEventListener('notification-clicked', (event) => {
                        const data = event.detail;
                        if (data && data.url) window.location.href = data.url;
                    });
                }

                // Handle Page Expired (419) errors gracefully
                if (window.Livewire) {
                    Livewire.hook('request', ({ fail }) => {
                        fail(({ status, preventDefault }) => {
                            if (status === 419) {
                                preventDefault();
                                console.warn('CSRF Expired. Refreshing session...');
                                window.location.reload();
                            }
                        });
                    });
                }
            });
        </script>
        <script data-navigate-once>
            // Ultimate Persistent Loader Logic
            (function() {
                const show = () => {
                    const el = document.getElementById('global-truck-loader');
                    if (el) el.style.display = 'flex';
                };
                const hide = () => {
                    const el = document.getElementById('global-truck-loader');
                    if (el) el.style.display = 'none';
                };

                // Listen on document (Livewire 3 events often don't bubble to window)
                document.addEventListener('livewire:navigating', show);
                document.addEventListener('livewire:navigated', hide);
                
                // Proactive click trigger for wire:navigate links
                document.addEventListener('click', (e) => {
                    const link = e.target.closest('a[wire\\:navigate], button[wire\\:navigate]');
                    if (link) show();
                });
            })();
        </script>

        <!-- Persistent Global Loader (Survived Swaps via wire:persist) -->
        <div wire:persist="global-loader">
            <div id="global-truck-loader" 
                 style="display: none;" 
                 class="fixed inset-0 z-[2000] bg-slate-900/80 backdrop-blur-md flex flex-col items-center justify-center">
                <x-truck-loader message="Driving..." />
            </div>
        </div>
</body>
</html>
