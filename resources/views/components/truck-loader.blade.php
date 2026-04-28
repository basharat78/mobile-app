@props(['message' => 'Loading...', 'overlay' => false])

<div {{ $attributes->merge(['class' => $overlay 
    ? 'fixed inset-0 z-[1000] bg-slate-900/80 backdrop-blur-md flex flex-col items-center justify-center' 
    : 'flex flex-col items-center justify-center gap-3 py-6']) }}
    id="page-transition-loader"
    @if($overlay) style="display: none;" @endif
>
    
    <div class="flex flex-col items-center gap-6">
        {{-- Premium Truck SVG with Road Animation --}}
        <div class="relative">
            <div class="animate-bounce">
                <svg width="80" height="48" viewBox="0 0 72 44" fill="none" xmlns="http://www.w3.org/2000/svg" class="drop-shadow-[0_0_15px_rgba(59,130,246,0.5)]">
                    <rect x="4" y="12" width="44" height="22" rx="3" fill="#1e3a5f" stroke="#3b82f6" stroke-width="1.2"/>
                    <rect x="48" y="18" width="16" height="16" rx="2" fill="#1e3a5f" stroke="#3b82f6" stroke-width="1.2"/>
                    <path d="M48 18 L56 18 L62 26 L62 34 L48 34 Z" fill="#1e293b" stroke="#3b82f6" stroke-width="1.2"/>
                    <rect x="56" y="22" width="6" height="6" rx="1" fill="#93c5fd" opacity="0.7"/>
                    <circle cx="14" cy="36" r="4.5" fill="#0f172a" stroke="#3b82f6" stroke-width="1.5"/>
                    <circle cx="14" cy="36" r="1.8" fill="#3b82f6"/>
                    <circle cx="54" cy="36" r="4.5" fill="#0f172a" stroke="#3b82f6" stroke-width="1.5"/>
                    <circle cx="54" cy="36" r="1.8" fill="#3b82f6"/>
                    <line x1="4" y1="40" x2="68" y2="40" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"
                          style="stroke-dasharray:8 4; animation: roadScroll 0.4s linear infinite"/>
                </svg>
            </div>
            <!-- Glow effect -->
            <div class="absolute -inset-4 bg-blue-500/10 blur-xl rounded-full animate-pulse"></div>
        </div>
        
        <div class="space-y-2 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-blue-500 animate-pulse">{{ $message }}</p>
            <div class="flex justify-center gap-1.5">
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-bounce"></div>
            </div>
        </div>
    </div>
</div>
