<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Load;

new #[Layout('components.layouts.app')] class extends Component
{
    public $isSyncing = false;

    public function mount()
    {
        $this->syncDashboard();
    }

    public function syncDashboard()
    {
        $user = Auth::user();
        $carrier = $user->carrier;
        
        if (!$carrier) return;

        $this->isSyncing = true;

        try {
            // 1. Sync Remote ID if missing
            if (!$carrier->remote_id) {
                $lookupUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/lookup';
                $lookupResponse = \Illuminate\Support\Facades\Http::timeout(5)->post($lookupUrl, ['email' => $user->email]);
                if ($lookupResponse->successful()) {
                    $lookupData = $lookupResponse->json();
                    if (isset($lookupData['carrier_id'])) {
                        $carrier->update([
                            'remote_id' => $lookupData['carrier_id'],
                            'status' => $lookupData['status'] ?? $carrier->status
                        ]);
                    }
                }
            }

            // 2. Sync Status from Cloud
            if ($carrier->remote_id) {
                $statusUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/status/' . $user->email;
                $remoteStatusResponse = \Illuminate\Support\Facades\Http::timeout(5)->get($statusUrl);
                if ($remoteStatusResponse->successful()) {
                    $remoteStatusData = $remoteStatusResponse->json();
                    if (isset($remoteStatusData['status']) && $remoteStatusData['status'] !== $carrier->status) {
                        $carrier->update(['status' => $remoteStatusData['status']]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard sync failed', ['error' => $e->getMessage()]);
        }

        $this->isSyncing = false;
    }

    public function getStatsProperty()
    {
        $user = Auth::user();
        $carrier = $user->carrier;
        
        if (!$carrier) return null;

        $isApproved = $carrier->status === 'approved';

        return [
            'isApproved' => $isApproved,
            'activeLoadsCount' => $isApproved ? $carrier->loadRequests()->where('status', 'approved')->whereHas('loadJob')->count() : 0,
            'pendingRequestsCount' => $isApproved ? $carrier->loadRequests()->where('status', 'pending')->whereHas('loadJob')->count() : 0,
            'requestLoadsCount' => Load::where('carrier_id', $carrier->id)
                ->whereDoesntHave('requests', function($q) use ($carrier) {
                    $q->where('carrier_id', $carrier->id);
                })
                ->where('status', 'active')
                ->count(),
            'status' => ucfirst($carrier->status),
            'initials' => collect(explode(' ', $user->name ?? ''))->filter()->map(fn($n) => substr($n, 0, 1))->take(2)->join('') ?: 'U',
            'recentUpdates' => $this->getRecentUpdates($carrier, $isApproved),
        ];
    }

    protected function getRecentUpdates($carrier, $isApproved)
    {
        $recentUpdates = collect();
        if ($isApproved) {
            foreach($carrier->documents as $doc) {
                $recentUpdates->push([
                    'title' => ucfirst(str_replace('_', ' ', $doc->type)) . ' ' . ucfirst($doc->status),
                    'description' => $doc->status === 'approved' ? 'Verified' : ($doc->status === 'rejected' ? 'Re-upload required' : 'Review in progress'),
                    'type' => $doc->status,
                    'time' => $doc->updated_at,
                ]);
            }
            foreach($carrier->loadRequests()->with('loadJob')->latest()->take(3)->get() as $request) {
                $recentUpdates->push([
                    'title' => 'Load Request ' . ucfirst($request->status),
                    'description' => "For " . ($request->loadJob?->pickup_location ?? 'Unknown Load'),
                    'type' => $request->status,
                    'time' => $request->updated_at,
                ]);
            }
            return $recentUpdates->sortByDesc('time')->take(5);
        }
        return $recentUpdates;
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10" wire:poll.10s="syncDashboard">
    @php $s = $this->stats; @endphp
    
    @if(!$s['isApproved'])
        <!-- Onboarding / Pending Approval State -->
        <div class="space-y-10 animate-fadeIn">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow">Onboarding</h1>
                    <p class="text-slate-400 font-medium text-sm">Complete these steps to start hauling</p>
                </div>
                <div class="px-3 py-1.5 bg-yellow-500/10 border border-yellow-500/20 rounded-xl flex items-center gap-2">
                    <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(234,179,8,0.6)]"></span>
                    <span class="text-[9px] font-black text-yellow-500 uppercase tracking-widest">Awaiting Review</span>
                </div>
            </div>

            <div class="p-10 bg-slate-800/20 border border-white/5 rounded-[3rem] shadow-2xl relative overflow-hidden">
                <div class="flex flex-col items-center text-center space-y-8 relative z-10">
                    <div class="w-24 h-24 rounded-full bg-slate-900 border-2 border-dashed border-slate-700 flex items-center justify-center animate-spin-slow">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    
                    <div class="space-y-3">
                        <h2 class="text-2xl font-black text-white italic uppercase tracking-tighter">{{ $s['status'] }} Verification</h2>
                        <p class="text-slate-400 text-sm font-medium leading-relaxed max-w-[280px]">Our dispatch team is currently reviewing your documents and fleet authority.</p>
                    </div>

                    <div class="w-full h-1 bg-slate-900 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 animate-pulse-width"></div>
                    </div>

                    <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Typical review time: 4-6 hours</p>
                </div>
                
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/5 rounded-full blur-3xl"></div>
            </div>

            <div class="grid gap-4">
                <a href="/document-upload" class="p-6 glass-morphism border border-white/5 rounded-[2rem] flex items-center justify-between group hover:border-blue-500/20 transition-all">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center text-slate-400 group-hover:text-blue-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <span class="text-white font-bold italic uppercase tracking-tight">Manage Documents</span>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Header -->
        <div class="flex items-start justify-between animate-fadeIn">
            <div class="space-y-1">
                <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Truck Zap</h1>
                <p class="text-slate-400 font-medium">Welcome back, <span class="text-blue-400">{{ explode(' ', Auth::user()->name)[0] }}</span>!</p>
            </div>
            <div class="relative group">
                <div class="w-14 h-14 bg-blue-gradient rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-blue-500/40 text-white font-black italic transition-transform group-hover:scale-110 group-hover:rotate-3 duration-500">
                    {{ $s['initials'] }}
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="p-10 bg-royal-gradient rounded-[3rem] relative overflow-hidden group shadow-[0_20px_50px_rgba(30,58,138,0.4)] animate-fadeIn">
            <div class="relative z-10 flex flex-col items-start">
                <div class="px-3 py-1 bg-white/10 backdrop-blur-md rounded-lg mb-4">
                    <p class="text-white/70 text-[9px] font-black uppercase tracking-widest leading-none">Account Status</p>
                </div>
                <h3 class="text-6xl font-black text-white italic tracking-tighter mb-6 leading-none">{{ $s['status'] }}</h3>
                <a href="/my-requests" class="inline-flex items-center gap-2 px-6 py-3.5 bg-white/10 hover:bg-white/20 backdrop-blur-xl border border-white/20 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white transition-all shadow-xl active:scale-95">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.8)]"></span>
                    {{ $s['activeLoadsCount'] }} Loads Approved
                </a>
            </div>
            <!-- Decorative Elements -->
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000"></div>
        </div>

        <!-- Stats Grid -->
        <div class="grid gap-5 animate-fadeIn" style="animation-delay: 100ms">
            <div class="p-6 glass-morphism border border-white/5 rounded-[2.5rem] flex items-center justify-between shadow-2xl active:scale-[0.98] transition-all cursor-pointer group hover:border-blue-500/30">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 bg-blue-gradient rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20 group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V3.375c0-.621-.504 1.125 1.125 1.125h-1.5a3.375 3.375 0 0 1-3.375 3.375H9.75m10.5 11.25V3.375m-10.5 4.5a3.375 3.375 0 0 1-3.375-3.375h-1.5a1.125 1.125 0 0 0-1.125 1.125v12.75c0 .621.504 1.125 1.125 1.125H16.5M9.75 8.25h4.875c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H9.75V8.25Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-black italic uppercase tracking-tight">Active Loads</span>
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">In progress</span>
                    </div>
                </div>
                <span class="text-3xl font-black text-white italic text-glow">{{ $s['activeLoadsCount'] }}</span>
            </div>

            <div class="p-6 glass-morphism border border-white/5 rounded-[2.5rem] flex items-center justify-between shadow-2xl active:scale-[0.98] transition-all cursor-pointer group hover:border-orange-500/30">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-black italic uppercase tracking-tight">Pending</span>
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Awaiting review</span>
                    </div>
                </div>
                <span class="text-3xl font-black text-white italic text-glow text-orange-400">{{ $s['pendingRequestsCount'] }}</span>
            </div>

            <a href="/loads" class="p-6 bg-blue-gradient rounded-[2.5rem] flex items-center justify-between shadow-2xl active:scale-[0.98] transition-all group overflow-hidden relative">
                <div class="flex items-center gap-5 relative z-10">
                    <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-black italic uppercase tracking-tight">Request Loads</span>
                        <span class="text-[9px] font-bold text-white/60 uppercase tracking-widest">Find new freight</span>
                    </div>
                </div>
                <span class="text-3xl font-black text-white italic relative z-10">{{ $s['requestLoadsCount'] }}</span>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            </a>
        </div>

        <!-- Recent Activity -->
        <div class="space-y-6 animate-fadeIn" style="animation-delay: 200ms">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xl font-black text-white italic">Recent Activity</h3>
                <a href="/my-requests" class="text-blue-500 text-[10px] font-black uppercase tracking-widest">View All</a>
            </div>
            
            <div class="space-y-4">
                @forelse($s['recentUpdates'] as $update)
                    <div class="p-5 glass-morphism border border-white/5 rounded-2xl flex items-center gap-5">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center
                            @if($update['type'] === 'approved') bg-green-500/10 text-green-500
                            @elseif($update['type'] === 'rejected') bg-red-500/10 text-red-500
                            @else bg-blue-500/10 text-blue-500
                            @endif">
                            @if($update['type'] === 'approved')
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-[11px] font-black text-white uppercase tracking-tight leading-none mb-1">{{ $update['title'] }}</p>
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">{{ $update['description'] }}</p>
                        </div>
                        <span class="text-[8px] font-black text-slate-600 uppercase">{{ $update['time']->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="py-10 text-center opacity-40 italic">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">No recent updates</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
