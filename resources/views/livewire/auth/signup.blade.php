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
    public $isProcessing = false;
    public $showSuccessModal = false;
    public $lastCloudResponse = '';


    public function mount()
    {
        // Ready
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

            $this->isProcessing = true;
            $this->lastCloudResponse = 'Phase 1: Cloud Verification Pulses...';

            // Normalization (v82): Remove spaces/dashes
            $cleanPhone = preg_replace('/[^0-9+]/', '', $this->phone);

            // Pulse 1: SHADOW EMAIL (v83) - Always send both for server context
            try {
                $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/lookup';
                $respEmail = \Illuminate\Support\Facades\Http::timeout(10)->post($apiUrl, [
                    'email' => $this->email,
                    'phone' => $cleanPhone,
                    'role' => $this->role
                ]);
                $this->lastCloudResponse = "Email Pulse: [" . $respEmail->status() . "] " . $respEmail->body();

                if ($respEmail->status() === 409 || $respEmail->status() === 422 || (isset($respEmail->json()['email_match']) && $respEmail->json()['email_match'])) {
                    session()->flash('error', 'THIS EMAIL IS ALREADY REGISTERED. Please login.');
                    $this->isProcessing = false;
                    return;
                }
            } catch (\Exception $e) { /* Catch & Log */ }

            // Pulse 2: SHADOW PHONE (v83) - Force isolated server check
            try {
                $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/lookup';
                $respPhone = \Illuminate\Support\Facades\Http::timeout(10)->post($apiUrl, [
                    'check_phone' => true,
                    'phone' => $cleanPhone,
                    'email' => $this->email,
                    'role' => $this->role
                ]);
                $this->lastCloudResponse .= "\nPhone Pulse: [" . $respPhone->status() . "] " . $respPhone->body();

                if ($respPhone->status() === 409 || $respPhone->status() === 422 || (isset($respPhone->json()['phone_match']) && $respPhone->json()['phone_match'])) {
                    session()->flash('error', 'THIS PHONE NUMBER IS ALREADY REGISTERED. Please login.');
                    $this->isProcessing = false;
                    return;
                }
            } catch (\Exception $e) { /* Catch & Log */ }

            // --- CLOUD-FIRST REGISTRATION (v83 Restored) ---
            $this->lastCloudResponse .= "\nPhase 2: Attempting Cloud Sync...";
            $remoteId = null;

            try {
                $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/register';
                $response = \Illuminate\Support\Facades\Http::timeout(25)->post($apiUrl, [
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'password' => $this->password,
                    'company_name' => $this->company_name,
                    'role' => $this->role,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $remoteId = $data['carrier_id'] ?? null;
                    $this->lastCloudResponse .= "\nCloud Status: SUCCESS";
                } elseif ($response->status() === 409 || $response->status() === 422) {
                    $conflict = $response->json();
                    session()->flash('error', $conflict['message'] ?? 'Identity Conflict. This account already exists on the network.');
                    $this->isProcessing = false;
                    return;
                } else {
                    $this->lastCloudResponse .= "\nCloud Status: OFFLINE (Queued)";
                }
            } catch (\Exception $syncError) {
                $this->lastCloudResponse .= "\nCloud Status: ERROR (Queued)";
                \Illuminate\Support\Facades\Log::warning('Initial Cloud Sync Error', ['error' => $syncError->getMessage()]);
            }

            // --- LOCAL DATA PERSISTENCE ---
            // Now create locally using the remoteId if we have it
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
                    'remote_id' => $remoteId,
                    // Store registration data for recovery if sync was offline
                    'pending_registration_data' => $remoteId ? null : json_encode([
                        'name' => $this->name,
                        'email' => $this->email,
                        'phone' => $this->phone,
                        'password' => $this->password,
                        'company_name' => $this->company_name,
                        'role' => $this->role,
                    ])
                ]);
            }

            $this->showSuccessModal = true;
            $this->isProcessing = false;

            // Wait 2 seconds for the user to see the success pop alert (v80)
            sleep(2); 

            Auth::login($user);

            if ($this->role === 'carrier') {
                return redirect('/document-upload');
            }

            return redirect('/dispatcher/dashboard');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            $this->isProcessing = false;
            throw $ve;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->isProcessing = false;
            session()->flash('error', 'An unexpected error occurred. Please try again.');
            throw $e;
        }
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-slate-900 selection:bg-blue-500/30">
    <div class="w-full max-w-md space-y-10">
        <div class="flex flex-col items-center space-y-4">
            <img src="/logo.png" class="h-20 w-auto object-contain" alt="Truckerz App">
            <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Join Truckerz App</h1>
        </div>
            <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em]">Next-Gen Logistics Network</p>
            
        </div>

        <div class="p-10 space-y-10 bg-slate-800/20 border border-white/5 rounded-[3rem] shadow-2xl relative overflow-hidden">
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-blue-600/5 rounded-full"></div>
            
            <!-- v80 Visible Error Flash -->
            @if (session()->has('error'))
                <div class="relative z-20 mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl flex items-start gap-3 animate-shake">
                    <div class="w-8 h-8 rounded-xl bg-red-500/20 flex items-center justify-center text-red-500 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-red-500 uppercase tracking-widest leading-none mb-1">Signup Blocked</p>
                        <p class="text-[9px] font-bold text-red-300/80 uppercase tracking-tight">{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            <form wire:submit="signup" class="space-y-6 relative z-10">
                <!-- Role Selection: Hidden on mobile, visible on web panel -->
                <div class="hidden md:flex p-1 bg-slate-900 border border-white/5 rounded-2xl items-center">
                    <button type="button" wire:click="$set('role', 'carrier')" class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $role === 'carrier' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-slate-500 hover:text-slate-300' }}">Carrier</button>
                    <button type="button" wire:click="$set('role', 'dispatcher')" class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $role === 'dispatcher' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-slate-500 hover:text-slate-300' }}">Dispatcher</button>
                </div>
                <!-- Mobile: Carrier-only badge -->
                <div class="md:hidden p-1 bg-slate-900 border border-white/5 rounded-2xl flex items-center">
                    <div class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest bg-blue-600 text-white shadow-lg shadow-blue-500/20 text-center">Carrier Account</div>
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
                    <button type="submit" wire:loading.attr="disabled" class="w-full py-5 bg-blue-600 text-white rounded-2xl font-black uppercase tracking-[0.2em] text-xs shadow-2xl shadow-blue-500/40 hover:bg-blue-500 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="signup" x-show="!$wire.isProcessing">Create Account</span>
                        <span wire:loading wire:target="signup" class="flex items-center gap-2">
                             <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                             </svg>
                             Processing...
                        </span>
                        <template x-if="$wire.isProcessing">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Securing Account...
                            </span>
                        </template>
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


    <!-- Login Pop Alerts (v84 Restored & Updated) -->
    <div x-data="{ showProcessing: @entangle('isProcessing'), showSuccess: @entangle('showSuccessModal') }" 
         class="relative z-[500]">
        
        <!-- Processing Modal -->
        <template x-if="showProcessing && !showSuccess">
            <div class="fixed inset-0 bg-slate-900/90 backdrop-blur-xl flex flex-col items-center justify-center p-10 text-center animate-fade-in">
                <div class="relative mb-8">
                    <div class="w-24 h-24 bg-blue-600/20 rounded-[2rem] flex items-center justify-center border border-blue-500/30">
                        <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="absolute -inset-4 bg-blue-500/10 blur-2xl rounded-full animate-pulse"></div>
                </div>
                <h2 class="text-2xl font-black text-white italic uppercase tracking-tighter mb-2">Creating Account</h2>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">Synchronizing with global freight network...</p>
            </div>
        </template>

        <!-- Success Modal -->
        <template x-if="showSuccess">
            <div class="fixed inset-0 bg-slate-900/95 backdrop-blur-2xl flex flex-col items-center justify-center p-10 text-center animate-bounce-in">
                <div class="w-24 h-24 bg-green-500/20 rounded-[2rem] flex items-center justify-center border border-green-500/30 mb-8 shadow-[0_0_50px_rgba(34,197,94,0.3)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-10 h-10 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <h2 class="text-3xl font-black text-white italic uppercase tracking-tighter mb-2">Signing you in</h2>
                <p class="text-[10px] text-blue-400 font-black uppercase tracking-[0.3em] mb-8">Registration Complete</p>
                <div class="w-48 h-1 bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 animate-progress"></div>
                </div>
                <p class="mt-8 text-[9px] text-slate-500 font-bold uppercase tracking-widest">Entering Hub...</p>
            </div>
        </template>
    </div>
</div>

