<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\LoadRequest;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $status = 'all';

    public function mount()
    {
        if (!Auth::user()->isApproved()) {
            return redirect('/dashboard');
        }
    }

    public function with()
    {
        $query = LoadRequest::with('loadJob')
            ->whereHas('loadJob')
            ->where('carrier_id', Auth::user()->carrier->id)
            ->latest();

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        return [
            'requests' => $query->get()
        ];
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10">
    <div class="max-w-md mx-auto space-y-10">
        <div class="space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none text-center">My Requests</h1>
            <p class="text-slate-400 font-medium text-sm text-center">Tracking your freight biddings</p>
        </div>

        <!-- Status Tabs -->
        <div class="flex items-center p-1.5 glass-morphism rounded-2xl border border-white/10 overflow-x-auto no-scrollbar gap-1">
            @foreach(['all', 'pending', 'approved', 'rejected'] as $s)
                <button 
                    wire:click="setStatus('{{ $s }}')"
                    class="flex-1 py-3 px-5 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all duration-300 whitespace-nowrap relative overflow-hidden group {{ $status === $s ? 'text-white' : 'text-slate-500 hover:text-slate-300' }}"
                >
                    @if($status === $s)
                        <div class="absolute inset-0 bg-blue-gradient shadow-lg shadow-blue-500/30"></div>
                    @endif
                    <span class="relative z-10">{{ $s }}</span>
                </button>
            @endforeach
        </div>

        <!-- Requests List -->
        <div class="space-y-5">
            @forelse($requests as $request)
                @php
                    $statusColor = $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red');
                    $gradient = $request->status === 'approved' ? 'bg-gradient-to-br from-green-500 to-emerald-700' : ($request->status === 'pending' ? 'bg-gradient-to-br from-yellow-500 to-orange-700' : 'bg-gradient-to-br from-red-500 to-rose-700');
                @endphp
                <div class="p-8 glass-morphism border border-white/5 rounded-[2.5rem] relative overflow-hidden group hover:border-blue-500/30 transition-all duration-500">
                    <div class="relative z-10 flex flex-col gap-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full {{ $gradient }} animate-pulse shadow-[0_0_8px_rgba(255,255,255,0.4)]"></div>
                                <span class="text-[9px] font-black uppercase tracking-widest {{ 'text-'.$statusColor.'-400' }}">
                                    {{ $request->status }}
                                </span>
                            </div>
                            <span class="text-[9px] text-slate-500 font-black uppercase tracking-widest">{{ $request->created_at->diffForHumans() }}</span>
                        </div>
        
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-blue-600/10 flex items-center justify-center shrink-0 border border-blue-500/10">
                                    <div class="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Pickup</span>
                                    <h3 class="text-white font-black text-lg leading-none italic uppercase tracking-tight">{{ $request->loadJob?->pickup_location ?? 'Unknown' }}</h3>
                                </div>
                            </div>
                            
                            <div class="ml-5 h-6 border-l border-dashed border-slate-700"></div>

                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-800/50 flex items-center justify-center shrink-0 border border-white/5">
                                    <div class="w-2 h-2 rounded-full bg-slate-600"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Delivery</span>
                                    <h3 class="text-slate-400 font-black text-lg leading-none italic uppercase tracking-tight">{{ $request->loadJob?->drop_location ?? 'Unknown' }}</h3>
                                </div>
                            </div>
                        </div>
        
                        <div class="pt-6 border-t border-white/10 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-slate-500 text-[8px] font-black uppercase tracking-widest">Rate Bid</p>
                                <p class="text-white font-black text-2xl italic tracking-tighter text-glow">
                                    <span class="text-blue-500 text-sm italic mr-0.5">$</span>{{ number_format($request->loadJob?->rate ?? 0) }}
                                </p>
                            </div>
                            <div class="text-right space-y-1">
                                <p class="text-slate-500 text-[8px] font-black uppercase tracking-widest">Equipment</p>
                                <div class="px-3 py-1 bg-white/5 rounded-lg border border-white/10">
                                    <p class="text-blue-400 font-black text-[10px] uppercase tracking-widest italic">{{ $request->loadJob?->equipment_type ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Decorative back text -->
                    <div class="absolute -right-4 top-1/2 -translate-y-1/2 text-8xl font-black text-white/[0.02] italic tracking-tighter select-none pointer-events-none">TZ</div>
                </div>
            @empty
                <div class="py-20 text-center space-y-4 glass-morphism rounded-[2.5rem] border border-dashed border-white/10">
                    <div class="w-16 h-16 bg-slate-800/50 rounded-full flex items-center justify-center mx-auto shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-slate-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest italic">No requests found</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
