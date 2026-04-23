<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $preferred_origin = '';
    public $preferred_destination = '';
    public $preferred_equipment = 'Dry Van';
    public $min_rate = '';
    public $signature = '';
    public $agreed = false;
    public $isLocked = false;

    public function mount()
    {
        $carrier = Auth::user()->carrier;
        $this->preferred_origin = $carrier->preferred_origin;
        $this->preferred_destination = $carrier->preferred_destination;
        $this->preferred_equipment = $carrier->preferred_equipment ?? 'Dry Van';
        $this->min_rate = $carrier->min_rate;
        $this->isLocked = !empty($carrier->signature_path);
    }

    public function save()
    {
        $this->validate([
            'preferred_origin' => 'nullable|string|max:255',
            'preferred_destination' => 'nullable|string|max:255',
            'preferred_equipment' => 'required|string',
            'min_rate' => 'nullable|numeric|min:0',
            'signature' => 'required|string|min:100', // Ensure something was drawn
            'agreed' => 'accepted',
        ], [
            'signature.required' => 'Please sign the agreement to proceed.',
            'signature.min' => 'Signature is too small. Please sign clearly.',
            'agreed.accepted' => 'You must agree to the terms and conditions.',
        ]);

        $carrier = Auth::user()->carrier;
        $carrier->update([
            'preferred_origin' => $this->preferred_origin,
            'preferred_destination' => $this->preferred_destination,
            'preferred_equipment' => $this->preferred_equipment,
            'min_rate' => $this->min_rate,
            'signature_path' => $this->signature,
        ]);

        // Sync to remote Dispatcher Web UI via API (v27)
        try {
            $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/preferences';
            \Illuminate\Support\Facades\Http::timeout(15)
                ->post($apiUrl, [
                    'email' => Auth::user()->email,
                    'preferred_origin' => $this->preferred_origin,
                    'preferred_destination' => $this->preferred_destination,
                    'preferred_equipment' => $this->preferred_equipment,
                    'min_rate' => $this->min_rate,
                    'signature_path' => $this->signature,
                ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Remote preferences sync failed', ['error' => $e->getMessage()]);
        }

        session()->flash('message', 'Preferences saved successfully.');
        return redirect('/dashboard');
    }
};
?>

<div class="px-6 py-12 space-y-12 relative z-10 flex flex-col items-center">
    <div class="w-full max-w-md space-y-12">
        <div class="space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none text-center">Set Prefs</h1>
            <p class="text-slate-400 font-medium text-sm text-center">Route Optimization & Agreement</p>
        </div>

        @if (session()->has('message'))
            <div class="p-5 glass-morphism border border-green-500/30 rounded-[2rem] text-green-500 text-sm font-bold flex items-center gap-3 animate-fadeIn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ session('message') }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-8">
            <!-- Route Preferences -->
            <div class="glass-morphism border border-white/5 rounded-[3rem] p-8 space-y-6">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-slate-600 group-focus-within:text-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>
                    <input wire:model="preferred_origin" type="text" placeholder="Preferred Origin" {{ $isLocked ? 'disabled' : '' }} class="block w-full pl-14 pr-6 py-5 bg-slate-900 border border-white/10 rounded-2xl text-white placeholder:text-slate-700 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all font-bold text-sm disabled:opacity-50">
                </div>

                <div class="relative group">
                    <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-slate-600 group-focus-within:text-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>
                    <input wire:model="preferred_destination" type="text" placeholder="Preferred Destination" {{ $isLocked ? 'disabled' : '' }} class="block w-full pl-14 pr-6 py-5 bg-slate-900 border border-white/10 rounded-2xl text-white placeholder:text-slate-700 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all font-bold text-sm disabled:opacity-50">
                </div>
            </div>

            <!-- Equipment & Rate -->
            <div class="glass-morphism border border-white/5 rounded-[3rem] p-8 space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-3">Equipment Type</label>
                    <div class="relative group">
                        <select wire:model="preferred_equipment" {{ $isLocked ? 'disabled' : '' }} class="block w-full px-6 py-5 bg-slate-900 border border-white/10 rounded-2xl text-white focus:ring-2 focus:ring-blue-500/50 outline-none font-bold text-sm appearance-none cursor-pointer disabled:opacity-50">
                            <option value="Dry Van">Dry Van</option>
                            <option value="Reefer">Reefer</option>
                            <option value="Flatbed">Flatbed</option>
                            <option value="Step Deck">Step Deck</option>
                            <option value="Box Truck">Box Truck</option>
                        </select>
                        <div class="absolute inset-y-0 right-6 flex items-center pointer-events-none text-slate-600 transition-transform group-hover:translate-y-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-blue-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-3">Min Rate ($/mile)</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-blue-500 font-black italic">$</div>
                        <input wire:model="min_rate" type="number" step="0.01" placeholder="0.00" {{ $isLocked ? 'disabled' : '' }} class="block w-full pl-12 pr-6 py-5 bg-slate-900 border border-white/10 rounded-2xl text-white focus:ring-2 focus:ring-blue-500/50 outline-none font-bold text-sm disabled:opacity-50">
                    </div>
                </div>
            </div>

            <!-- Signature Pad -->
            <div class="glass-morphism border border-white/5 rounded-[3rem] p-8 space-y-6" 
                 x-data="{ 
                    signaturePad: null,
                    isEmpty: true,
                    init() {
                        this.$nextTick(() => {
                            setTimeout(() => {
                                const canvas = this.$refs.canvas;
                                if (!canvas) return;
                                
                                this.signaturePad = new SignaturePad(canvas, {
                                    backgroundColor: 'rgba(0,0,0,0)',
                                    penColor: '#3b82f6',
                                    onBegin: () => { this.isEmpty = false; }
                                });
                                
                                const resizeCanvas = () => {
                                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                    const rect = canvas.getBoundingClientRect();
                                    if (rect.width === 0) return;
                                    
                                    canvas.width = rect.width * ratio;
                                    canvas.height = rect.height * ratio;
                                    canvas.getContext('2d').scale(ratio, ratio);
                                    this.signaturePad.clear();
                                };

                                window.addEventListener('resize', resizeCanvas);
                                resizeCanvas();
                                setTimeout(resizeCanvas, 1000);
                            }, 500);
                        });
                    },
                    clear() {
                        this.signaturePad.clear();
                        this.isEmpty = true;
                        $wire.set('signature', '');
                    },
                    submit() {
                        if (!this.signaturePad.isEmpty()) {
                            $wire.set('signature', this.signaturePad.toDataURL());
                        }
                    }
                 }" 
                 @mousedown="!$wire.get('isLocked') && submit()" @touchstart="!$wire.get('isLocked') && submit()" @mouseup="!$wire.get('isLocked') && submit()" @touchend="!$wire.get('isLocked') && submit()">
                
                <div class="flex items-center justify-between px-4">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Sign Agreement</label>
                    <button type="button" @click="clear()" x-show="!$wire.get('isLocked')" class="text-[9px] font-black text-red-500/70 border border-red-500/20 px-2 py-1 rounded-lg uppercase tracking-widest hover:text-red-400 hover:border-red-400/30 transition-all">Clear</button>
                    @if($isLocked)
                        <span class="text-[9px] font-black text-green-500 bg-green-500/10 px-3 py-1.5 rounded-lg uppercase tracking-widest border border-green-500/30">Locked</span>
                    @endif
                </div>
                
                <div class="relative bg-slate-900 border border-white/5 rounded-[1.5rem] overflow-hidden min-h-[160px]" wire:ignore>
                    <canvas x-ref="canvas" class="w-full h-40 cursor-crosshair relative z-10 touch-action-none"></canvas>
                    <div x-show="isEmpty" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <span class="text-slate-800 italic font-black text-4xl opacity-20 select-none tracking-tighter">Sign Here</span>
                    </div>
                </div>

                <!-- Signature Agreement (v30 Smart Card) -->
                <div @if(!$isLocked) @click="$wire.set('agreed', !$wire.get('agreed'))" @endif class="p-6 flex items-start gap-4 bg-slate-800/40 border {{ $agreed ? 'border-blue-500/50' : 'border-white/5' }} rounded-[1.5rem] {{ $isLocked ? 'cursor-default' : 'cursor-pointer' }} group transition-all duration-300 hover:bg-slate-800/60 select-none">
                    <div class="mt-1 flex-shrink-0">
                        <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shadow-lg {{ $agreed ? 'bg-blue-600 border-blue-600 shadow-blue-500/40' : 'bg-slate-900 border-white/10 group-hover:border-blue-500/30' }}">
                            @if($agreed)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="w-3.5 h-3.5 text-white animate-fade-in">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <div class="flex-1 space-y-1">
                        <p class="text-[10px] font-black uppercase tracking-widest leading-none {{ $agreed ? 'text-white' : 'text-slate-500' }}">Confirm Agreement</p>
                        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tight leading-relaxed">Authorized representative signature. By signing, you agree to the <span class="text-blue-500 hover:underline" onclick="event.stopPropagation()">Carrier Services Agreement</span>.</p>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-5 glass-morphism border border-red-500/30 rounded-[2rem] space-y-2 animate-shake">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-center gap-2">
                             <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                             <p class="text-red-400 text-[10px] font-black uppercase tracking-widest">{{ $error }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="pt-6">
                <button type="submit" wire:loading.attr="disabled" {{ $isLocked ? 'disabled' : '' }} class="w-full py-6 {{ $isLocked ? 'bg-slate-700' : 'bg-blue-600' }} text-white rounded-[2rem] font-black italic uppercase tracking-[0.2em] text-sm shadow-2xl {{ $isLocked ? 'shadow-none' : 'shadow-blue-500/40 hover:scale-[1.02] active:scale-[0.98]' }} transition-all relative overflow-hidden group flex items-center justify-center gap-3">
                    <span wire:loading.remove wire:target="save" class="relative z-10">{{ $isLocked ? 'Agreement Signed' : 'Finalize & Enter' }}</span>
                    <span wire:loading wire:target="save" class="relative z-10 flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving Agreement...
                    </span>
                    <div class="absolute inset-0 bg-white/10 translate-x-full group-hover:translate-x-0 transition-transform duration-700"></div>
                </button>
            </div>
        </form>
    </div>
</div>
