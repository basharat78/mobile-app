<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app')] class extends Component
{
    public $name;
    public $email;
    public $company_name;
    public $phone;
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';


    public function mount(){ // Load the current user's data into the component properties
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->company_name = $user->company_name;
        $this->phone = $user->phone;
    }
    public function updateProfile(){
        $user = Auth::user();

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'company_name' => 'nullable|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
        ]);
    $user->update([
        'name' => $this->name,
        'email' => $this->email,
        'company_name' => $this->company_name,
        'phone' => $this->phone,
    ]);

    // --- CLOUD SYNC (v92) ---
    try {
        $baseUrl = env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com';
        Http::timeout(10)->post("{$baseUrl}/api/carrier/profile/update", [
            'email' => $this->email,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'phone' => $this->phone,
        ]);
    } catch (\Exception $e) {
        Log::warning("Profile Cloud Sync Failed: " . $e->getMessage());
    }

    session()->flash('message', 'Profile updated successfully.');

    }
    public function updatePassword(){
        $user = Auth::user();
        
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user->update([
            'password' => Hash::make($this->new_password),
        ]);
    
        // --- CLOUD SECURITY SYNC (v92) ---
        try {
            $baseUrl = env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com';
            Http::timeout(10)->post("{$baseUrl}/api/carrier/password/update", [
                'email' => $user->email,
                'current_password' => $this->current_password,
                'new_password' => $this->new_password,
            ]);
        } catch (\Exception $e) {
            Log::warning("Password Cloud Sync Failed: " . $e->getMessage());
        }

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        session()->flash('password-message', 'Security credentials updated successfully.');
        
    }

};

?>
<div class="px-6 py-12 space-y-12 relative z-10">
    <div class="max-w-md mx-auto space-y-12">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Security</h1>
                <p class="text-slate-400 font-medium text-sm">Manage access and identity</p>
            </div>
            <form action="{{ route('logout') }}" method="POST" x-on:submit="loggingOut = true">
                @csrf
                <button type="submit" class="p-4 glass rounded-2xl flex items-center justify-center hover:bg-red-500/10 transition-all border border-red-500/20 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-red-500 group-hover:scale-110 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                </button>
            </form>
        </div>

        @if (session()->has('message'))
            <div class="p-5 glass-morphism border border-green-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <p class="text-green-400 text-sm font-bold">{{ session('message') }}</p>
            </div>
        @endif

        <div class="space-y-8">
            <!-- Identity Form -->
            <div class="p-8 glass-morphism border border-white/5 rounded-[3rem] relative shadow-2xl">
                <div class="flex items-center gap-5 mb-8">
                    <div class="w-14 h-14 bg-blue-gradient rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20 text-white italic font-black">
                         @php
                            $initials = collect(explode(' ', Auth::user()->name ?? ''))
                                ->filter()
                                ->map(fn($n) => $n[0])
                                ->take(2)
                                ->join('') ?: 'U';
                        @endphp
                        {{ $initials }}
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-white italic tracking-tight uppercase">Identity</h3>
                        <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest">Public contact details</p>
                    </div>
                </div>

                <form wire:submit="updateProfile" class="space-y-6">
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
                        <input wire:model="name" type="text" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all" placeholder="John Doe">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Company</label>
                        <input wire:model="company_name" type="text" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all" placeholder="Truck Zap Logistics">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Email Address</label>
                        <input wire:model="email" type="email" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all" placeholder="name@company.com">
                        @error('email')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-2 ml-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Phone Number</label>
                        <input wire:model="phone" type="text" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all" placeholder="+1 (555) 000-0000">
                        @error('phone')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-2 ml-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="w-full bg-blue-gradient text-white font-black italic uppercase tracking-widest py-5 rounded-[2rem] hover:scale-[1.02] active:scale-95 transition-all shadow-2xl shadow-blue-500/40 mt-4">
                        Update Identity
                    </button>
                </form>
            </div>

            <!-- Security / Password -->
            <div class="p-8 glass-morphism border border-white/5 rounded-[3rem] relative shadow-2xl">
                <div class="flex items-center gap-5 mb-8">
                    <div class="w-14 h-14 bg-red-600/10 rounded-2xl flex items-center justify-center border border-red-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-red-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-white italic tracking-tight uppercase">Credentials</h3>
                        <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest">Update security key</p>
                    </div>
                </div>

                @if (session()->has('password-message'))
                    <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-[9px] font-black uppercase tracking-widest mb-6 animate-shake">
                        {{ session('password-message') }}
                    </div>
                @endif

                <form wire:submit="updatePassword" class="space-y-6">
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Current Key</label>
                        <input wire:model="current_password" type="password" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all">
                        @error('current_password')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-2 ml-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">New Key</label>
                        <input wire:model="new_password" type="password" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all">
                        @error('new_password')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-2 ml-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Confirm New Key</label>
                        <input wire:model="new_password_confirmation" type="password" class="w-full bg-slate-900 border border-white/10 rounded-2xl px-6 py-5 text-white text-sm focus:ring-2 focus:ring-blue-500/50 outline-none font-bold transition-all">
                    </div>

                    <button type="submit" class="w-full glass text-slate-300 font-black italic uppercase tracking-widest py-5 rounded-[2rem] hover:text-white hover:bg-white/10 transition-all border border-white/5 active:scale-95 mt-4">
                        Rotate Credentials
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
