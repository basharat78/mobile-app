<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Load;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $search = '';

    public function mount()
    {
        if (!Auth::user()->isApproved()) {
            return redirect('/dashboard');
        }
    }

    #[Computed]
    public function loads()
    {
        return Load::where('status', 'available')
            ->when($this->search, function ($query) {
                $query->where('pickup_location', 'like', '%' . $this->search . '%')
                      ->orWhere('drop_location', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->get();
    }

    #[Computed]
    public function requestedLoadIds()
    {
        return Auth::user()->carrier->loadRequests()->pluck('load_id')->toArray();
    }

    public function requestLoad($loadId)
    {
        $carrier = Auth::user()->carrier;

        if ($carrier->loadRequests()->where('load_id', $loadId)->exists()) {
            session()->flash('error', 'You have already requested this load.');
            return;
        }

        $load = Load::find($loadId);
        $load->requests()->create([
            'carrier_id' => $carrier->id,
            'status' => 'pending',
        ]);

        session()->flash('message', 'Load request sent!');
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10">
    <div class="max-w-md mx-auto space-y-10">
        <div class="flex items-end justify-between">
            <div class="space-y-1">
                <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Find Loads</h1>
                <p class="text-slate-400 font-medium text-sm">Marketplace direct from dispatch</p>
            </div>
            <div class="px-3 py-1.5 glass-morphism border border-blue-500/20 rounded-full flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(59,130,246,0.8)]"></span>
                <span class="text-[9px] font-black text-blue-400 uppercase tracking-widest">Live Updates</span>
            </div>
        </div>

        <!-- Search -->
        <div class="relative group">
            <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-500 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input wire:model.live="search" type="text" class="block w-full pl-14 pr-6 py-5 glass-morphism border border-white/5 rounded-[1.5rem] text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all shadow-[0_10px_30px_-10px_rgba(0,0,0,0.3)]" placeholder="Search locations...">
        </div>

        @if (session()->has('message'))
            <div class="p-5 glass-morphism border border-green-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <p class="text-green-400 text-sm font-bold">{{ session('message') }}</p>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-5 glass-morphism border border-red-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <p class="text-red-400 text-sm font-bold">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Load List -->
        <div class="space-y-6">
            @forelse($this->loads as $load)
                @php
                    $isRequested = in_array($load->id, $this->requestedLoadIds);
                @endphp
                <div class="p-8 glass-morphism border border-white/5 rounded-[3rem] relative overflow-hidden group hover:border-blue-500/30 transition-all duration-500">
                    <div class="flex justify-between items-start relative z-10 mb-8">
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-blue-600/10 flex items-center justify-center shrink-0 border border-blue-500/10">
                                    <div class="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none mb-1">Pickup</span>
                                    <h4 class="text-white font-black italic text-lg uppercase leading-none tracking-tight">{{ $load->pickup_location }}</h4>
                                </div>
                            </div>
                            
                            <div class="ml-5 h-6 border-l border-dashed border-slate-700"></div>

                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-800/50 flex items-center justify-center shrink-0 border border-white/5">
                                    <div class="w-2 h-2 rounded-full bg-slate-600"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none mb-1">Delivery</span>
                                    <h4 class="text-slate-400 font-black italic text-lg uppercase leading-none tracking-tight">{{ $load->drop_location }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-slate-500 text-[8px] font-black uppercase tracking-widest mb-1">Max Rate</p>
                            <span class="text-3xl font-black text-white italic tracking-tighter text-glow">
                                <span class="text-blue-500 text-sm italic mr-0.5">$</span>{{ number_format($load->rate) }}
                            </span>
                            <div class="mt-1 flex items-center justify-end gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3 text-slate-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                                <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest italic">{{ $load->miles }} miles</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-6 border-t border-white/10 relative z-10">
                        <div class="flex gap-2">
                             <div class="px-3 py-1 bg-white/5 rounded-lg border border-white/10">
                                <p class="text-blue-400 font-black text-[9px] uppercase tracking-widest italic">{{ $load->equipment_type }}</p>
                            </div>
                        </div>
                        @if($isRequested)
                            <div class="px-8 py-3 bg-slate-800 rounded-2xl text-slate-500 text-[10px] font-black uppercase tracking-widest cursor-default border border-white/5">
                                Requested
                            </div>
                        @else
                            <button wire:click="requestLoad({{ $load->id }})" class="px-8 py-3 bg-blue-600 rounded-2xl text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/40 active:scale-95 relative overflow-hidden group/btn">
                                <span class="relative z-10">Request Load</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-blue-600 translate-x-full group-hover/btn:translate-x-0 transition-transform duration-500"></div>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-20 space-y-4 glass-morphism rounded-[3rem] border border-dashed border-white/10">
                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-slate-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest italic">No loads found match your criteria.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
