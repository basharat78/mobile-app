<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
    
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        session()->flash('password-message', 'Security credentials updated successfully.');
        
    }

};

?>
<div class="p-8 pb-32 space-y-8 bg-slate-900 min-h-screen">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Account Settings</h1>
            <p class="text-slate-500 font-medium">Manage your personal and company identity</p>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="px-6 py-3 bg-red-500/10 text-red-500 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-red-500/20 transition-all">
                Terminate Session
            </button>
        </form>
    </div>

    @if (session()->has('message'))
        <div class="p-5 bg-green-500/10 border border-green-500/20 rounded-3xl text-green-500 text-xs font-bold flex items-center gap-3 animate-fade-in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Identity Form -->
        <div class="bg-slate-800/50 border border-white/5 rounded-[2.5rem] p-8 space-y-6 shadow-xl">
            <div class="flex items-center gap-4 mb-2">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white italic tracking-tight">Identity Profile</h3>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Public information and contact details</p>
                </div>
            </div>

            
            <form wire:submit="updateProfile" class="space-y-5">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Full Identity Name</label>
                    <input wire:model="name" type="text" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all" placeholder="John Doe">
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Company Entity</label>
                    <input wire:model="company_name" type="text" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all" placeholder="Logistics Corp">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Email Access</label>
                        <input wire:model="email" type="email" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all" placeholder="john@example.com">
                        @error('email')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Direct Phone</label>
                        <input wire:model="phone" type="text" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all" placeholder="+1 234 567 890">
                        @error('phone')
                            <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white font-black uppercase tracking-widest py-4 rounded-2xl hover:bg-blue-500 transition-all shadow-xl shadow-blue-500/20 active:scale-[0.98]">
                    Sync Profile Changes
                </button>
            </form>
        </div>

        <!-- Security / Password -->
        <div class="bg-slate-800/50 border border-white/5 rounded-[2.5rem] p-8 space-y-6 shadow-xl">
            <div class="flex items-center gap-4 mb-2">
                <div class="w-12 h-12 bg-red-600/10 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white italic tracking-tight">Access Security</h3>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Update your master security key</p>
                </div>
            </div>

            @if (session()->has('password-message'))
                <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-[10px] font-black uppercase tracking-widest">
                    {{ session('password-message') }}
                </div>
            @endif

            <form wire:submit="updatePassword" class="space-y-5">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Current Password</label>
                    <input wire:model="current_password" type="password" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all">
                    @error('current_password')
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">New Security Key</label>
                    <input wire:model="new_password" type="password" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all">
                    @error('new_password')
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Re-enter New Key</label>
                    <input wire:model="new_password_confirmation" type="password" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold transition-all">
                      @error('new_password_confirmation')
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-1">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="w-full bg-slate-800 text-slate-400 font-black uppercase tracking-widest py-4 rounded-2xl hover:text-white hover:bg-slate-700 transition-all active:scale-[0.98]">
                    Rotate Security Key
                </button>
            </form>
        </div>
    </div>
</div>
