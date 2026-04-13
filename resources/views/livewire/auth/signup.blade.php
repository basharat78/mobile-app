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
                    $remoteData = $response->json();
                    \Illuminate\Support\Facades\Log::info('Remote sync SUCCESS', $remoteData);
                    
                    // Store the remote carrier ID locally for future syncing
                    if ($this->role === 'carrier' && isset($remoteData['carrier_id'])) {
                        $user->carrier()->update(['remote_id' => $remoteData['carrier_id']]);
                    }
                    
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

        <div class="p-10 space-y-10 bg-slate-800/20 border border-white/5 rounded-[3rem] shadow-2xl relative overflow-hidden">
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-blue-600/5 rounded-full"></div>
            
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

                <!-- Terms & Conditions (v29 Optimized) -->
                <div class="mt-8">
                    <div wire:click="$toggle('terms')" class="p-5 flex items-start gap-4 bg-slate-800/40 border {{ $terms ? 'border-blue-500/50' : 'border-white/5' }} rounded-[1.5rem] cursor-pointer group transition-all duration-300 hover:bg-slate-800/60 select-none">
                        <div class="mt-1 flex-shrink-0">
                            <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shadow-lg {{ $terms ? 'bg-blue-600 border-blue-600 shadow-blue-500/40' : 'bg-slate-900 border-white/10 group-hover:border-blue-500/30' }}">
                                @if($terms)
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="w-3.5 h-3.5 text-white animate-fade-in">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <p class="text-[10px] font-black uppercase tracking-widest leading-none {{ $terms ? 'text-white' : 'text-slate-500' }}">Accept Terms</p>
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tight">I agree to the <span class="text-blue-500 hover:underline">Terms & Conditions</span></p>
                        </div>
                    </div>
                    @error('terms') <p class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-3 ml-4 animate-shake">{{ $message }}</p> @enderror
                </div>

                <div class="pt-4">
                    <button type="submit" wire:loading.attr="disabled" class="w-full py-5 bg-blue-600 text-white rounded-2xl font-black uppercase tracking-[0.2em] text-xs shadow-2xl shadow-blue-500/40 hover:bg-blue-500 active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                        <span wire:loading.remove wire:target="signup">Create Account</span>
                        <span wire:loading wire:target="signup" class="flex items-center gap-2">
                             <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                             </svg>
                             Processing...
                        </span>
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
        <div class="mt-8 p-6 bg-black/40 rounded-3xl border border-white/10 space-y-4" wire:poll.20s="fetchLogs">
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
