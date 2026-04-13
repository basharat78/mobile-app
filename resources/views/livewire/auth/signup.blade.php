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
    public $db_status = 'checking';
    public $debug_log = [];

    public function fetchLogs()
    {
        $this->debug_log = collect(explode("\n", \Illuminate\Support\Facades\File::get(storage_path('logs/laravel.log'))))
            ->reverse()
            ->take(5)
            ->toArray();
    }

    public function mount()
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $this->db_status = 'online';
        } catch (\Exception $e) {
            $this->db_status = 'offline';
            \Illuminate\Support\Facades\Log::error('Database connection check failed', ['error' => $e->getMessage()]);
        }
    }

    public function signup()
    {
        \Illuminate\Support\Facades\Log::info('Signup attempt started', [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role
        ]);

        try {
            $this->validate([
                'name' => 'required|string|max:255',
                'company_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|unique:users,phone',
                'password' => 'required|min:8|confirmed',
                'role' => 'required|in:carrier,dispatcher',
                'terms' => 'accepted',
            ]);

            // 1. Create user locally (for in-app auth/session)
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
            }

            // 2. Sync to remote Hostinger MySQL via API
            try {
                $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/register';
                $response = \Illuminate\Support\Facades\Http::timeout(15)
                    ->post($apiUrl, [
                        'name' => $this->name,
                        'company_name' => $this->company_name,
                        'email' => $this->email,
                        'phone' => $this->phone,
                        'password' => $this->password,
                        'role' => $this->role,
                    ]);

                if ($response->successful()) {
                    \Illuminate\Support\Facades\Log::info('Remote sync SUCCESS', $response->json());
                    $this->db_status = 'synced';
                } else {
                    \Illuminate\Support\Facades\Log::warning('Remote sync FAILED', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $this->db_status = 'local_only';
                }
            } catch (\Exception $syncError) {
                \Illuminate\Support\Facades\Log::error('Remote sync ERROR', [
                    'error' => $syncError->getMessage(),
                ]);
                $this->db_status = 'local_only';
            }

            \Illuminate\Support\Facades\Log::info('Signup successful', ['user_id' => $user->id]);

            Auth::login($user);

            if ($this->role === 'carrier') {
                return redirect('/document-upload');
            }

            return redirect('/dispatcher/dashboard');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-slate-900 selection:bg-blue-500/30">
    <div class="w-full max-w-md space-y-10">
        <div class="text-center space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase">Join Truck Zap</h1>
            <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em]">Next-Gen Logistics Network</p>
            
            <!-- Connection Status -->
            <div class="flex items-center justify-center gap-2 mt-2">
                <div class="w-2 h-2 rounded-full {{ $db_status === 'online' ? 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.6)]' : ($db_status === 'offline' ? 'bg-red-500' : 'bg-slate-500 animate-pulse') }}"></div>
                <span class="text-[8px] font-black uppercase tracking-widest {{ $db_status === 'online' ? 'text-green-500' : ($db_status === 'offline' ? 'text-red-500' : 'text-slate-500') }}">
                    DB {{ $db_status }}
                </span>
            </div>
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

        <!-- Real-time Debug Audit -->
        <div class="mt-8 p-6 bg-black/40 rounded-3xl border border-white/10 space-y-4" wire:poll.5s="fetchLogs">
            <h3 class="text-[10px] font-black text-blue-400 uppercase tracking-widest flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-ping"></span>
                Connection Diagnostics
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white/5 p-3 rounded-xl border border-white/5">
                    <p class="text-[8px] text-slate-500 uppercase font-black">API Sync</p>
                    <p class="text-[10px] font-bold mt-1
                        @if($db_status === 'synced') text-green-500
                        @elseif($db_status === 'local_only') text-yellow-500
                        @elseif($db_status === 'online') text-blue-500
                        @else text-red-500
                        @endif
                    ">{{ strtoupper($db_status) }}</p>
                </div>
                <div class="bg-white/5 p-3 rounded-xl border border-white/5" wire:ignore>
                    <p class="text-[8px] text-slate-500 uppercase font-black">Bridge Status</p>
                    <p class="text-[10px] font-bold mt-1" id="bridge-status">Detecting...</p>
                </div>
                <div class="bg-white/5 p-3 rounded-xl border border-white/5" wire:ignore>
                    <p class="text-[8px] text-slate-500 uppercase font-black">Runtime Service</p>
                    <p class="text-[10px] font-bold mt-1 text-slate-500" id="runtime-status">Verifying...</p>
                </div>
                <div class="bg-white/5 p-3 rounded-xl border border-white/5 col-span-2">
                    <p class="text-[8px] text-slate-500 uppercase font-black">Remote API URL</p>
                    <p class="text-[10px] font-bold mt-1 text-blue-400 truncate">{{ env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com' }}</p>
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-[8px] text-slate-500 uppercase font-black">Latest Server Event</p>
                <div class="text-[9px] font-mono text-slate-300 bg-black/50 p-3 rounded-xl overflow-x-auto whitespace-pre">
@foreach($debug_log as $log)
{{ $log }}
@endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Local Diagnostic Override (v23)
     * Let the global app.blade.php handle the heavy lifting,
     * but we ensure the local elements are ready.
     */
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Signup Diagnostic Panel Ready');
    });
</script>
