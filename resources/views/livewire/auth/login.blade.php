<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $login = '';
    public $password = '';
    public $remember = false;
    public $showSuccess = false;
    public $isProcessing = false;

    public function authenticate()
    {
        $this->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        $fieldType = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $this->isProcessing = true;

        // 1. Try Local Login
        if (Auth::attempt([$fieldType => $this->login, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            $this->showSuccess = true;
            sleep(1);
            return redirect()->intended('/dashboard');
        }

        // 2. Local Login Failed - Try Cloud Recovery (v42)
        try {
            $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/authenticate';
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($apiUrl, [
                'login' => $this->login,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Cloud Auth Success! SYNC profile to local DB
                $user = \App\Models\User::updateOrCreate(
                    ['email' => $data['user']['email']],
                    [
                        'name' => $data['user']['name'],
                        'phone' => $data['user']['phone'],
                        'password' => \Illuminate\Support\Facades\Hash::make($this->password),
                        'role' => $data['user']['role'],
                        'company_name' => $data['user']['company_name'],
                    ]
                );

                if ($user->role === 'carrier') {
                    $user->carrier()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'status' => $data['carrier']['status'],
                            'remote_id' => $data['carrier']['id'],
                        ]
                    );
                }

                Auth::login($user, $this->remember);
                session()->regenerate();
                $this->showSuccess = true;
                sleep(1);
                return redirect()->intended('/dashboard');
            } else {
                // Return server-provided error if available
                $serverMessage = $response->json()['message'] ?? 'Identity not found on cloud.';
                $this->addError('login', 'Cloud Error: ' . $serverMessage);
                return;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cloud auth recovery failed', ['error' => $e->getMessage()]);
            $this->addError('login', 'Network Error: Check internet connection for identity recovery.');
            $this->isProcessing = false;
            return;
        }
    }

    // public function biometricLogin($status)
    // {
    //     if ($status === 'success') {
    //         // In a real application, you would verify a stored token.
    //         // For this integration, we'll authenticate the first available user to demonstrate the flow.
    //         $user = \App\Models\User::where('role', 'carrier')->first();
    //         if ($user) {
    //             Auth::login($user);
    //             return redirect()->intended('/dashboard');
    //         }
    //     }
    // }
};
?>

<div class="flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-gradient-to-b from-slate-900 to-slate-800"
     x-data="{ 
        isNative: typeof window.__nativephp_bridge !== 'undefined',
        async triggerBiometrics() {
            if (!this.isNative) return;
            
            // Start biometric prompt
            if (typeof window.__nativephp_dispatch === 'function') {
                window.__nativephp_bridge.send('biometric:prompt', {
                    id: 'login_auth',
                    reason: 'Authenticate to access your Truck Zap dashboard'
                });
            }
        }
     }">
    <div class="w-full max-w-md space-y-8">
        <div class="text-center">
            <h1 class="text-5xl font-extrabold tracking-tight text-white italic">Truck Zap</h1>
            <p class="mt-4 text-lg text-slate-400">Reliable Logistics at your fingertips</p>
        </div>

        <div class="p-8 mt-10 space-y-6 bg-slate-800/40 border border-white/10 rounded-[2.5rem] backdrop-blur-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            <form wire:submit="authenticate" class="space-y-6">
                <!-- ... existing fields ... -->
                <div>
                    <label for="login" class="block text-sm font-semibold text-slate-300 ml-2 mb-1">Email or Phone</label>
                    <input wire:model="login" type="text" id="login" class="block w-full px-5 py-4 text-white bg-slate-900/60 border border-slate-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 placeholder-slate-500 transition-all outline-none" placeholder="Enter your email or phone">
                    @error('login') <span class="text-xs text-red-400 mt-1 ml-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-300 ml-2 mb-1">Password</label>
                    <input wire:model="password" type="password" id="password" class="block w-full px-5 py-4 text-white bg-slate-900/60 border border-slate-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 placeholder-slate-500 transition-all outline-none" placeholder="••••••••">
                    @error('password') <span class="text-xs text-red-400 mt-1 ml-2 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-between px-2">
                    <div class="flex items-center">
                        <input wire:model="remember" id="remember" type="checkbox" class="w-5 h-5 text-blue-600 bg-slate-900 border-slate-700 rounded-lg focus:ring-blue-500/50">
                        <label for="remember" class="ml-2 text-sm font-medium text-slate-400">Remember me</label>
                    </div>
                    <a href="/forgot-password" class="text-sm font-semibold text-blue-400 hover:text-blue-300 transition-colors">Forgot?</a>
                </div>

                <div class="pt-2">
                    <button type="submit" class="flex justify-center w-full px-6 py-4 text-base font-bold text-white bg-blue-600 rounded-2xl hover:bg-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/30 transition-all active:scale-[0.98] shadow-xl shadow-blue-500/25">
                        <span wire:loading.remove>Login</span>
                        <span wire:loading>Authenticating...</span>
                    </button>
                </div>
            </form>

            <!-- Biometric Login Button (Visible on NativePHP Mobile) -->
            {{-- <div x-show="isNative" x-cloak class="pt-2 animate-fade-in">
                <button @click="triggerBiometrics()" class="flex items-center justify-center w-full px-6 py-4 text-base font-bold text-blue-400 bg-blue-500/10 border border-blue-500/20 rounded-2xl hover:bg-blue-500/20 transition-all active:scale-[0.98] gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75s.168-.75.375-.75.375.336.375.75Zm6 0c0 .414-.168.75-.375.75S15 10.164 15 9.75s.168-.75.375-.75.375.336.375.75Z" />
                    </svg>
                    FaceLock Login
                </button>
            </div> --}}

            <div class="pt-4 text-center">
                <a href="/signup" class="flex justify-center w-full py-4 text-base font-bold text-white bg-slate-700/50 rounded-2xl hover:bg-slate-700 transition-all active:scale-[0.98]">
                    Sign Up
                </a>
            </div>
        </div>
    </div>

    <!-- Login Pop Alerts (v84) -->
    <div x-data="{ showProcessing: @entangle('isProcessing'), showSuccess: @entangle('showSuccess') }" class="relative z-[500]">
        <!-- Processing Modal -->
        <template x-if="showProcessing && !showSuccess">
            <div class="fixed inset-0 bg-slate-900/90 backdrop-blur-xl flex flex-col items-center justify-center p-10 text-center">
                <div class="relative mb-8">
                    <div class="w-24 h-24 bg-blue-600/20 rounded-[2rem] flex items-center justify-center border border-blue-500/30">
                        <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="absolute -inset-4 bg-blue-500/10 blur-2xl rounded-full animate-pulse"></div>
                </div>
                <h2 class="text-2xl font-black text-white italic uppercase tracking-tighter mb-2">Authenticating</h2>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">Verifying credentials...</p>
            </div>
        </template>

        <!-- Success Modal -->
        <template x-if="showSuccess">
            <div class="fixed inset-0 bg-slate-900/95 backdrop-blur-2xl flex flex-col items-center justify-center p-10 text-center">
                <div class="w-24 h-24 bg-green-500/20 rounded-[2rem] flex items-center justify-center border border-green-500/30 mb-8 shadow-[0_0_50px_rgba(34,197,94,0.3)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-10 h-10 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <h2 class="text-3xl font-black text-white italic uppercase tracking-tighter mb-2">Welcome Back</h2>
                <p class="text-[10px] text-blue-400 font-black uppercase tracking-[0.3em] mb-8">Login Successful</p>
                <div class="w-48 h-1 bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 animate-progress"></div>
                </div>
                <p class="mt-8 text-[9px] text-slate-500 font-bold uppercase tracking-widest">Loading Dashboard...</p>
            </div>
        </template>
    </div>
</div>

