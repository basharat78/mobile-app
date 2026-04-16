<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Load;

new #[Layout('components.layouts.app')] class extends Component
{
    public $isSyncing = false;
    public $isBackgroundMonitoring = false;
    public $syncLogs = [];
    public $showLogs = false;

    public function mount()
    {
        $this->isBackgroundMonitoring = Auth::user()->carrier->background_sync_enabled;
        $this->syncDashboard();

        // 4. Request Notification Permission (if plugin exists)
        if (class_exists(\Vendor\LocalNotification\Facades\LocalNotification::class)) {
            $status = \Vendor\LocalNotification\Facades\LocalNotification::checkPermission();
            if (!$status['granted']) {
                \Vendor\LocalNotification\Facades\LocalNotification::requestPermission();
            }
        }
    }

    public function updatedIsBackgroundMonitoring($value)
    {
        Auth::user()->carrier->update(['background_sync_enabled' => $value]);

        if ($value) {
            $this->js("window.Native.call('Notification.StartBackgroundSync', { email: '" . Auth::user()->email . "' }).then(r => console.log('Sync Started', r))");
        } else {
            $this->js("window.Native.call('Notification.StopBackgroundSync').then(r => console.log('Sync Stopped', r))");
        }
    }

    public function testNotification()
    {
        if (class_exists(\Vendor\LocalNotification\Facades\LocalNotification::class)) {
            \Illuminate\Support\Facades\Log::info('Triggering manual Test Notification');
            \Vendor\LocalNotification\Facades\LocalNotification::show(
                "Test Alert", 
                "If you see this, the notification system is working!",
                ['channelId' => 'status_updates', 'badge' => 1]
            );
        }
    }

    public function syncDashboard()
    {
        $this->isSyncing = true;
        \App\Services\SyncService::performGlobalSync(Auth::user());
        $this->isSyncing = false;
        
        // Auto-refresh logs if they are open
        if ($this->showLogs) {
            $this->viewSyncLogs();
        }
    }

    public function viewSyncLogs()
    {
        $this->showLogs = true;
        $path = storage_path('app/logs/sync_pulse.log');
        
        if (file_exists($path)) {
            $content = file($path);
            // Show last 30 pulses
            $this->syncLogs = array_reverse(array_slice($content, -30));
        } else {
            $this->syncLogs = ['No pulses recorded yet. Wait for a sync attempt.'];
        }
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
            'requestLoadsCount' => Load::where('carrier_id', $carrier->id)->count(),
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
            foreach($carrier->loadRequests()->whereHas('loadJob')->with('loadJob')->latest()->take(3)->get() as $request) {
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
                <div class="flex items-center gap-2">
                    <p class="text-slate-400 font-medium">Welcome, <span class="text-blue-400">{{ explode(' ', Auth::user()->name)[0] }}</span></p>
                    <button wire:click="viewSyncLogs" class="p-1.5 bg-white/5 rounded-lg border border-white/10 hover:bg-blue-500/10 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="relative group">
                <div class="w-14 h-14 bg-blue-gradient rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-blue-500/40 text-white font-black italic transition-transform group-hover:scale-110 group-hover:rotate-3 duration-500">
                    {{ $s['initials'] }}
                </div>
            </div>
        </div>

        @if($showLogs)
            <div class="p-6 glass-morphism border border-blue-500/20 rounded-3xl animate-fadeIn">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-blue-400 italic">Sync Pulse Log</h3>
                    <button wire:click="$set('showLogs', false)" class="text-slate-500 hover:text-white">&times;</button>
                </div>
                <div class="max-h-48 overflow-y-auto space-y-2 no-scrollbar font-mono text-[9px]">
                    @foreach($syncLogs as $log)
                        <div class="p-2 bg-white/5 rounded-lg border border-white/5 {{ str_contains($log, 'SUCCESS') ? 'text-green-400' : (str_contains($log, 'FAILED') ? 'text-red-400' : 'text-slate-400') }}">
                            {{ $log }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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

        <!-- System Settings -->
        <div class="space-y-6 animate-fadeIn" style="animation-delay: 300ms">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xl font-black text-white italic">Settings</h3>
            </div>
            
            <div class="p-6 glass-morphism border border-white/5 rounded-[2.5rem] flex items-center justify-between shadow-2xl transition-all group hover:border-blue-500/30">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 bg-slate-800/50 rounded-2xl flex items-center justify-center border border-white/5 group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-black italic uppercase tracking-tight">Background Sync</span>
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Instant alerts even if app is closed</span>
                    </div>
                </div>
                
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="isBackgroundMonitoring" class="sr-only peer">
                    <div class="w-14 h-8 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:start-[4px] after:bg-slate-400 after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600 peer-checked:after:bg-white"></div>
                </label>
            </div>

            <p class="text-center text-[9px] font-bold text-slate-600 uppercase tracking-[0.2em] px-10">
                Enabled: keeps app heartbeat alive for 24/7 freight monitoring. Increased battery usage may occur.
            </p>

            <!-- DIAGNOSTIC TOOLS -->
            <div x-data="{ localSyncing: false }" class="mt-8 space-y-4">
                <div class="px-2">
                    <h3 class="text-xs font-black text-slate-500 italic uppercase tracking-widest">Test Service</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <button wire:click="testNotification" class="p-5 glass-morphism border border-white/5 rounded-2xl flex flex-col items-center gap-3 active:scale-95 transition-all text-blue-400 hover:border-blue-500/20 group">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 group-hover:animate-bounce">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        <span class="text-[9px] font-black uppercase tracking-widest leading-none">Test Notification</span>
                    </button>

                    <button @click="localSyncing = true; $wire.syncDashboard().then(() => localSyncing = false)" :disabled="localSyncing" class="p-5 glass-morphism border border-white/5 rounded-2xl flex flex-col items-center gap-3 active:scale-95 transition-all text-purple-400 hover:border-purple-500/20 group disabled:opacity-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 group-hover:rotate-180 transition-transform duration-500" :class="{ 'animate-spin': localSyncing }">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span class="text-[9px] font-black uppercase tracking-widest leading-none">Emergency Sync</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
