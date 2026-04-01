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

<div class="px-6 py-8 space-y-6 min-h-screen bg-slate-900">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black text-white italic">Available Loads</h1>
        <div class="px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full">
            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Live</span>
        </div>
    </div>

    <!-- Search -->
    <div class="relative group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-500 transition-colors">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>
        <input wire:model.live="search" type="text" class="block w-full pl-12 pr-4 py-4 bg-slate-800/50 border border-white/5 rounded-2xl text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="Search by location...">
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-green-500/10 border border-green-500/20 rounded-xl text-green-400 text-sm font-bold">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm font-bold">
            {{ session('error') }}
        </div>
    @endif

    <!-- Load List -->
    <div class="space-y-4">
        @forelse($this->loads as $load)
            @php
                $isRequested = in_array($load->id, $this->requestedLoadIds);
            @endphp
            <div class="p-6 bg-slate-800/40 border border-white/5 rounded-[2rem] space-y-4 relative overflow-hidden group hover:border-blue-500/50 transition-all">
                <div class="flex justify-between items-start relative z-10">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <h4 class="text-white font-bold leading-none">{{ $load->pickup_location }}</h4>
                        </div>
                        <div class="flex items-center gap-2 pl-1 h-4">
                            <div class="w-0.5 h-full bg-slate-700 ml-[3px]"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            <h4 class="text-white font-bold leading-none">{{ $load->drop_location }}</h4>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-black text-white italic tracking-tighter">${{ number_format($load->rate, 2) }}</span>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{{ $load->miles }} miles</p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 relative z-10">
                    <div class="flex gap-2">
                        <span class="px-3 py-1 bg-white/5 rounded-full text-[10px] font-bold text-slate-400">{{ $load->equipment_type }}</span>
                    </div>
                    @if($isRequested)
                        <div class="px-6 py-2 bg-slate-700 rounded-xl text-slate-400 text-xs font-black uppercase tracking-widest cursor-default">
                            Requested
                        </div>
                    @else
                        <button wire:click="requestLoad({{ $load->id }})" class="px-6 py-2 bg-blue-600 rounded-xl text-white text-xs font-black uppercase tracking-widest hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                            Request
                        </button>
                    @endif
                </div>
                
                <!-- Background Glow -->
                <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-blue-600/5 rounded-full blur-2xl group-hover:bg-blue-600/10 transition-colors"></div>
            </div>
        @empty
            <div class="text-center py-12 space-y-4">
                <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-slate-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <p class="text-slate-500 font-medium">No loads found match your criteria.</p>
            </div>
        @endforelse
    </div>
</div>
