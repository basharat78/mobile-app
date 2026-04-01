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

<div class="min-h-screen bg-slate-900 px-6 py-12">
    <div class="max-w-md mx-auto space-y-8">
        <div class="text-center space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase">My Requests</h1>
            <p class="text-slate-500 font-medium">Tracking your freight biddings</p>
        </div>

        <!-- Status Tabs -->
        <div class="flex items-center p-1 bg-slate-800/50 rounded-2xl border border-white/5 overflow-x-auto no-scrollbar">
            @foreach(['all', 'pending', 'approved', 'rejected'] as $s)
                <button 
                    wire:click="setStatus('{{ $s }}')"
                    class="flex-1 py-3 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $status === $s ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-slate-500 hover:text-slate-300' }}"
                >
                    {{ $s }}
                </button>
            @endforeach
        </div>

        <!-- Requests List -->
        <div class="space-y-4">
            @forelse($requests as $request)
                <div class="p-6 bg-slate-800/40 border border-white/5 rounded-[2.5rem] relative overflow-hidden group hover:border-blue-500/30 transition-all">
                    <div class="relative z-10 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 bg-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500/10 text-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500 rounded-full text-[10px] font-black uppercase tracking-widest">
                                {{ $request->status }}
                            </span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{{ $request->created_at->diffForHumans() }}</span>
                        </div>

                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                <h3 class="text-white font-bold text-lg leading-tight uppercase tracking-tight">{{ $request->loadJob?->pickup_location ?? 'Unknown' }}</h3>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 bg-slate-600 rounded-full"></div>
                                <h3 class="text-slate-400 font-bold text-lg leading-tight uppercase tracking-tight">{{ $request->loadJob?->drop_location ?? 'Unknown' }}</h3>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                            <div>
                                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Rate Bid</p>
                                <p class="text-white font-black text-xl italic tracking-tighter">${{ number_format($request->loadJob?->rate ?? 0) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Equipment</p>
                                <p class="text-blue-400 font-bold text-sm uppercase tracking-tighter">{{ $request->loadJob?->equipment_type ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-20 text-center space-y-4 bg-slate-800/10 rounded-[2.5rem] border border-dashed border-white/10">
                    <div class="w-16 h-16 bg-slate-800/50 rounded-full flex items-center justify-center mx-auto">
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
