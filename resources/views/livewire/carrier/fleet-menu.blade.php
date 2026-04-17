<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public function mount()
    {
        // Require approved or at least existing carrier
        if (!Auth::user()->carrier) {
            return redirect('/dashboard');
        }
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10">
    <div class="max-w-md mx-auto space-y-10">
        <div class="space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none text-center">Fleet Hub</h1>
            <p class="text-slate-400 font-medium text-sm text-center">Manage your business & compliance</p>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- Documents Button -->
            <a href="/document-upload" wire:navigate class="group relative overflow-hidden p-8 glass-morphism border border-white/10 rounded-[2.5rem] transition-all duration-500 hover:border-blue-500/40 hover:shadow-[0_20px_50px_-15px_rgba(59,130,246,0.2)]">
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-blue-600/20 flex items-center justify-center border border-blue-500/20 group-hover:scale-110 transition-transform duration-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                    </div>
                    <div class="flex-1 space-y-1">
                        <h3 class="text-xl font-black text-white italic uppercase tracking-tight group-hover:text-blue-400 transition-colors">Document Vault</h3>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest">MC, W9 & Insurance Status</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-slate-700 group-hover:text-blue-500 transition-all group-hover:translate-x-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
                <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-blue-500/5 rounded-full blur-3xl transition-all group-hover:bg-blue-500/10"></div>
            </a>

            <!-- Preferences Button -->
            <a href="/preferences" wire:navigate class="group relative overflow-hidden p-8 glass-morphism border border-white/10 rounded-[2.5rem] transition-all duration-500 hover:border-purple-500/40 hover:shadow-[0_20px_50px_-15px_rgba(168,85,247,0.2)]">
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-purple-600/20 flex items-center justify-center border border-purple-500/20 group-hover:scale-110 transition-transform duration-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-purple-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                    </div>
                    <div class="flex-1 space-y-1">
                        <h3 class="text-xl font-black text-white italic uppercase tracking-tight group-hover:text-purple-400 transition-colors">Operating Prefs</h3>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest">Equipment & Service Areas</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-slate-700 group-hover:text-purple-500 transition-all group-hover:translate-x-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
                <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-purple-500/5 rounded-full blur-3xl transition-all group-hover:bg-purple-500/10"></div>
            </a>
        </div>

        <!-- Descriptive Note -->
        <div class="p-6 glass-morphism border border-white/5 rounded-3xl text-center">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-relaxed">Ensure all your details are up to date to receive the best load matches from our dispatchers.</p>
        </div>
    </div>
</div>
