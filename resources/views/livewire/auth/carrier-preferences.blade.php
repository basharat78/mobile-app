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

    public function mount()
    {
        $carrier = Auth::user()->carrier;
        $this->preferred_origin = $carrier->preferred_origin;
        $this->preferred_destination = $carrier->preferred_destination;
        $this->preferred_equipment = $carrier->preferred_equipment ?? 'Dry Van';
        $this->min_rate = $carrier->min_rate;
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

        Auth::user()->carrier->update([
            'preferred_origin' => $this->preferred_origin,
            'preferred_destination' => $this->preferred_destination,
            'preferred_equipment' => $this->preferred_equipment,
            'min_rate' => $this->min_rate,
            'signature_path' => $this->signature,
        ]);

        session()->flash('message', 'Preferences saved successfully.');
        return redirect('/dashboard');
    }
};
?>

<div class="min-h-screen bg-slate-900 px-6 py-12 flex flex-col items-center">
    <div class="w-full max-w-md space-y-10">
        <div class="text-center space-y-1">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase">Set Preferences</h1>
            <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em]">Route Optimization</p>
        </div>

        @if (session()->has('message'))
            <div class="p-5 bg-green-500/10 border border-green-500/20 rounded-3xl text-green-500 text-sm font-bold flex items-center gap-3 animate-fade-in">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ session('message') }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
            <!-- Route Preferences -->
            <div class="bg-slate-800/20 border border-white/5 rounded-[2.5rem] p-6 space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-slate-600 group-focus-within:text-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>
                    <input wire:model="preferred_origin" type="text" placeholder="Pick-Up Location" class="block w-full pl-14 pr-6 py-5 bg-slate-900 border border-white/5 rounded-3xl text-white placeholder:text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold text-sm">
                </div>

                <div class="relative group">
                    <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-slate-600 group-focus-within:text-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>
                    <input wire:model="preferred_destination" type="text" placeholder="Drop-Off Location" class="block w-full pl-14 pr-6 py-5 bg-slate-900 border border-white/5 rounded-3xl text-white placeholder:text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold text-sm">
                </div>
            </div>

            <!-- Equipment & Rate -->
            <div class="bg-slate-800/20 border border-white/5 rounded-[2.5rem] p-6 space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Equipment Type</label>
                    <div class="relative">
                        <select wire:model="preferred_equipment" class="block w-full px-6 py-5 bg-slate-900 border border-white/5 rounded-3xl text-white focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm appearance-none">
                            <option value="Dry Van">Dry Van</option>
                            <option value="Reefer">Reefer</option>
                            <option value="Flatbed">Flatbed</option>
                            <option value="Step Deck">Step Deck</option>
                            <option value="Box Truck">Box Truck</option>
                        </select>
                        <div class="absolute inset-y-0 right-6 flex items-center pointer-events-none text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2">Min Rate ($/mile)</label>
                    <input wire:model="min_rate" type="number" step="0.01" placeholder="e.g. 2.50" class="block w-full px-6 py-5 bg-slate-900 border border-white/5 rounded-3xl text-white focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm">
                </div>
            </div>

            <!-- Signature Pad -->
            <div class="bg-slate-800/20 border border-white/5 rounded-[2.5rem] p-6 space-y-4" 
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
                                    if (rect.width === 0) return; // Wait until visible
                                    
                                    canvas.width = rect.width * ratio;
                                    canvas.height = rect.height * ratio;
                                    canvas.getContext('2d').scale(ratio, ratio);
                                    this.signaturePad.clear();
                                };

                                window.addEventListener('resize', resizeCanvas);
                                resizeCanvas();
                                
                                // Re-resize after a short while just in case splash screen was still on
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
                 @mousedown="submit()" @touchstart="submit()" @mouseup="submit()" @touchend="submit()">
                
                <div class="flex items-center justify-between px-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Agreement Signature</label>
                    <button type="button" @click="clear()" class="text-[10px] font-black text-red-500 uppercase tracking-widest hover:text-red-400 transition-colors">Clear Pad</button>
                </div>
                
                <div class="relative bg-slate-900 border border-white/5 rounded-3xl overflow-hidden min-h-[160px]" wire:ignore>
                    <canvas x-ref="canvas" class="w-full h-40 cursor-crosshair relative z-10 touch-action-none"></canvas>
                    <div x-show="isEmpty" class="absolute inset-0 flex items-center justify-center pointer-events-none transition-opacity duration-300">
                        <span class="text-slate-700 italic font-black text-3xl opacity-30 select-none tracking-tighter">Sign Here</span>
                    </div>
                </div>

                <div class="flex items-start gap-4 p-2 group cursor-pointer select-none" wire:click="$toggle('agreed')">
                    <div class="mt-1">
                        <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shadow-lg {{ $agreed ? 'bg-blue-600 border-blue-600 shadow-blue-500/20' : 'bg-slate-900 border-white/10 group-hover:border-blue-500/50' }}">
                            @if($agreed)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="w-3.5 h-3.5 text-white animate-fade-in">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <label class="text-[10px] text-slate-500 font-bold uppercase tracking-tight leading-relaxed cursor-pointer group-hover:text-slate-300 transition-colors">
                        I hereby authorize Truck Zap to facilitate load matching and agree to the <span class="text-blue-500 hover:underline" onclick="event.stopPropagation()">Service Terms</span>.
                    </label>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-5 bg-red-500/10 border border-red-500/20 rounded-3xl space-y-1">
                    @foreach ($errors->all() as $error)
                        <p class="text-red-500 text-[10px] font-black uppercase tracking-widest">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="pt-4">
                <button type="submit" class="w-full py-6 bg-blue-600 text-white rounded-3xl font-black uppercase tracking-[0.2em] text-sm shadow-2xl shadow-blue-500/40 hover:bg-blue-500 active:scale-[0.98] transition-all">
                    Agree & Sign
                </button>
            </div>
        </form>
    </div>
</div>
