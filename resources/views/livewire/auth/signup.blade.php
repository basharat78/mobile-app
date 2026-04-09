<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Notifications\CarrierRegistered;
use Illuminate\Support\Facades\Notification;

new #[Layout('components.layouts.app')] class extends Component
{
    public $name = '';
    public $company_name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = 'carrier';
    public $terms = false;

    public function signup()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:carrier,dispatcher',
            'terms' => 'accepted',
        ]);

        $user = User::create([
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => $this->role,
        ]);

        if ($this->role === 'carrier') {
            $user->carrier()->create([
                'status' => 'pending',
            ]);

            // Notify all dispatchers
            $dispatchers = User::where('role', 'dispatcher')->get();
            Notification::send($dispatchers, new CarrierRegistered($user->name));
        }

        Auth::login($user);

        if ($this->role === 'carrier') {
            return redirect('/document-upload');
        }

        return redirect('/dispatcher/dashboard');
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-slate-900 selection:bg-blue-500/30">
    <div class="w-full max-w-md space-y-10">
        <div class="text-center space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase">Join Truck Zap</h1>
            <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em]">Next-Gen Logistics Network</p>
        </div>

        <div class="p-8 space-y-8 bg-slate-800/20 border border-white/5 rounded-[2.5rem] backdrop-blur-3xl shadow-2xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl"></div>
            
            <form wire:submit="signup" class="space-y-6 relative z-10">
                <!-- Role Selection -->
                <div class="p-1 bg-slate-900 border border-white/5 rounded-2xl flex items-center">
                    <button type="button" wire:click="$set('role', 'carrier')" class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $role === 'carrier' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-slate-500 hover:text-slate-300' }}">Carrier</button>
                    <button type="button" wire:click="$set('role', 'dispatcher')" class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $role === 'dispatcher' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-slate-500 hover:text-slate-300' }}">Dispatcher</button>
                </div>

                <div class="space-y-4">
                    <div class="relative group">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Account Name</label>
                        <input wire:model="name" type="text" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="John Doe">
                        @error('name') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</span> @enderror
                    </div>

                    <div class="relative group">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Company Entity</label>
                        <input wire:model="company_name" type="text" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="ABC Logistics">
                        @error('company_name') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Email Access</label>
                            <input wire:model="email" type="email" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="john@email.com">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Direct Phone</label>
                            <input wire:model="phone" type="text" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="+12345678">
                        </div>
                    </div>
                    @error('email') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</span> @enderror
                    @error('phone') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</span> @enderror

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Password</label>
                        <input wire:model="password" type="password" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="••••••••">
                        @error('password') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Confirm Password</label>
                        <input wire:model="password_confirmation" type="password" class="block w-full px-6 py-4 text-white bg-slate-900 border border-white/5 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm" placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center gap-3 px-2 group cursor-pointer select-none" wire:click="$toggle('terms')">
                    <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shadow-lg {{ $terms ? 'bg-blue-600 border-blue-600 shadow-blue-500/20' : 'bg-slate-900 border-white/10 group-hover:border-blue-500/50' }}">
                        @if($terms)
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="w-3.5 h-3.5 text-white animate-fade-in">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @endif
                    </div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest cursor-pointer group-hover:text-slate-300 transition-colors">
                        I accept the <a href="#" class="text-blue-500 hover:underline" onclick="event.stopPropagation()">Terms & Conditions</a>
                    </label>
                </div>
                @error('terms') <p class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 ml-4">{{ $message }}</p> @enderror

                <div class="pt-4">
                    <button type="submit" class="w-full py-5 bg-blue-600 text-white rounded-2xl font-black uppercase tracking-[0.2em] text-xs shadow-2xl shadow-blue-500/40 hover:bg-blue-500 active:scale-[0.98] transition-all">
                        Create Account
                    </button>
                </div>
            </form>

            <div class="text-center relative z-10">
                <p class="text-[10px] text-slate-600 font-black uppercase tracking-widest">
                    Existing user? 
                    <a href="/login" class="text-blue-500 hover:text-blue-400">Sign In</a>
                </p>
            </div>
        </div>
    </div>
</div>
