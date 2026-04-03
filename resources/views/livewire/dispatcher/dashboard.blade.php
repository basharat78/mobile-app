<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Carrier;
use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\CarrierDocument;

new #[Layout('components.layouts.app')] class extends Component
{
    public function getStatsProperty()
    {
        $dispatcherId = Auth::id();
        return [
            'total_carriers' => Carrier::where('dispatcher_id', $dispatcherId)->count(),
            'active_loads' => Load::where('dispatcher_id', $dispatcherId)->where('status', 'available')->count(),
            'pending_docs' => CarrierDocument::whereHas('carrier', fn($q) => $q->where('dispatcher_id', $dispatcherId))->where('status', 'pending')->count(),
            'total_revenue' => Load::where('dispatcher_id', $dispatcherId)->where('status', 'booked')->sum('rate'),
        ];
    }

    public function getRecentActivityProperty()
    {
        $dispatcherId = Auth::id();
        
        $carriers = Carrier::with('user')
            ->where('dispatcher_id', $dispatcherId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'type' => 'Registration',
                    'name' => $item->user->name ?? 'Unknown',
                    'company' => $item->user->company_name ?? 'N/A',
                    'status' => $item->status,
                    'time' => $item->created_at,
                    'avatar' => substr($item->user->name ?? '?', 0, 1),
                ];
            });

        $bids = LoadRequest::with(['carrier.user', 'loadJob'])
            ->whereHas('carrier', fn($q) => $q->where('dispatcher_id', $dispatcherId))
            ->latest()
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'type' => 'Load Bid',
                    'name' => $item->carrier->user->name ?? 'Unknown',
                    'company' => "Load #{$item->load_id} ({$item->loadJob->pickup_location})",
                    'status' => $item->status,
                    'time' => $item->created_at,
                    'avatar' => 'LB',
                ];
            });

        return $carriers->concat($bids)->sortByDesc('time')->take(8);
    }
};
?>

<div class="p-8 space-y-8 bg-slate-900 min-h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Command Center</h1>
            <p class="text-slate-500 font-medium">Monitoring global logistics operations</p>
        </div>
        <div class="flex gap-3">
            <div class="px-4 py-2 bg-slate-800 border border-white/5 rounded-xl flex items-center gap-2">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-bold text-slate-300 uppercase tracking-widest">System Live</span>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="p-6 bg-slate-800/50 border border-white/5 rounded-[2rem] shadow-xl relative overflow-hidden group hover:border-blue-500/30 transition-all">
            <div class="relative z-10">
                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Active Carriers</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">{{ $this->stats['total_carriers'] }}</h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-blue-600/5 rounded-full blur-2xl group-hover:bg-blue-600/10 transition-colors"></div>
        </div>

        <div class="p-6 bg-slate-800/50 border border-white/5 rounded-[2rem] shadow-xl relative overflow-hidden group hover:border-green-500/30 transition-all">
            <div class="relative z-10">
                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Available Loads</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">{{ $this->stats['active_loads'] }}</h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-green-600/5 rounded-full blur-2xl group-hover:bg-green-600/10 transition-colors"></div>
        </div>

        <div class="p-6 bg-slate-800/50 border border-white/5 rounded-[2rem] shadow-xl relative overflow-hidden group hover:border-yellow-500/30 transition-all">
            <div class="relative z-10">
                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Pending Docs</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">{{ $this->stats['pending_docs'] }}</h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-yellow-600/5 rounded-full blur-2xl group-hover:bg-yellow-600/10 transition-colors"></div>
        </div>

        <div class="p-6 bg-slate-800/50 border border-white/5 rounded-[2rem] shadow-xl relative overflow-hidden group hover:border-purple-500/30 transition-all">
            <div class="relative z-10">
                <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Booked</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">{{ number_format($this->stats['total_revenue'] / 1000, 1) }}</h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-purple-600/5 rounded-full blur-2xl group-hover:bg-purple-600/10 transition-colors"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Activity Feed -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black text-white italic">Recent Activity</h3>
                <div class="flex gap-2">
                    <a href="/dispatcher/carriers" class="text-blue-500 text-[10px] font-black uppercase tracking-widest">Carriers</a>
                    <span class="text-slate-700">•</span>
                    <a href="/dispatcher/loads" class="text-blue-500 text-[10px] font-black uppercase tracking-widest">Requests</a>
                </div>
            </div>
            
            <div class="bg-slate-800/20 border border-white/5 rounded-[2.5rem] overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/5">
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-widest">Activity</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-widest">Entity</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-widest">Status</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-widest text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($this->recent_activity as $activity)
                            <tr class="hover:bg-white/5 transition-colors group">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center font-bold text-slate-400 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                            {{ $activity['avatar'] }}
                                        </div>
                                        <div>
                                            <p class="text-white font-bold leading-tight">{{ $activity['name'] }}</p>
                                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{{ $activity['type'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    <p class="text-white font-medium text-sm">{{ $activity['company'] }}</p>
                                </td>
                                <td class="px-8 py-5">
                                    <span class="px-3 py-1 bg-{{ $activity['status'] === 'approved' ? 'green' : ($activity['status'] === 'pending' || $activity['status'] === 'requested' ? 'blue' : 'red') }}-500/10 text-{{ $activity['status'] === 'approved' ? 'green' : ($activity['status'] === 'pending' || $activity['status'] === 'requested' ? 'blue' : 'red') }}-500 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        {{ $activity['status'] }}
                                    </span>
                                </td>
                                <td class="px-8 py-5 text-right text-slate-600 text-[10px] font-black uppercase tracking-tighter">
                                    {{ $activity['time']->diffForHumans(null, true) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Alerts / Status -->
        <div class="space-y-6">
            <h3 class="text-xl font-black text-white italic">Active Alerts</h3>
            <div class="space-y-4">
                @php
                    $highValueUnassigned = Load::where('status', 'available')->where('rate', '>=', 2000)->latest()->take(2)->get();
                    $pendingDocsCount = CarrierDocument::where('status', 'pending')->count();
                    $latestPendingDoc = CarrierDocument::with('carrier.user')->where('status', 'pending')->latest()->first();
                @endphp

                @foreach($highValueUnassigned as $load)
                    <div class="p-5 bg-red-500/5 border border-red-500/10 rounded-3xl flex gap-4 animate-fade-in">
                        <div class="w-10 h-10 bg-red-500/10 rounded-xl flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-red-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="text-white text-sm font-bold">High-Value Load Unassigned</h5>
                            <p class="text-slate-500 text-[10px] mt-1 font-medium italic">Load #{{ $load->id }} ({{ $load->pickup_location }} -> {{ $load->drop_location }}) needs booking.</p>
                        </div>
                    </div>
                @endforeach
                
                @if($latestPendingDoc)
                    <div class="p-5 bg-blue-500/5 border border-blue-500/10 rounded-3xl flex gap-4 animate-fade-in">
                        <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="text-white text-sm font-bold">{{ $pendingDocsCount }} Document{{ $pendingDocsCount > 1 ? 's' : '' }} Pending</h5>
                            <p class="text-slate-500 text-[10px] mt-1 font-medium italic">{{ $latestPendingDoc->carrier->user->name }} uploaded {{ str_replace('_', ' ', $latestPendingDoc->type) }}.</p>
                        </div>
                    </div>
                @endif

                @if($highValueUnassigned->isEmpty() && !$latestPendingDoc)
                    <div class="text-center py-10 bg-slate-800/10 rounded-3xl border border-white/5">
                        <p class="text-slate-600 text-[10px] font-black uppercase tracking-widest italic">No active alerts</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
