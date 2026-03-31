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

</div>
