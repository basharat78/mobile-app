<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Load;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $isSyncing = false;
    public $activeLoadNotes = null;
    public $isApproved = false;
    public $processingLoadId = null; // Immediately locks button on click
    public $lastSyncInfo = 'Never synced';
    public $syncColor = 'text-slate-500';

    public function mount()
    {
        $this->isApproved = Auth::user()->isApproved();
        
        if ($this->isApproved) {
            $this->syncLoads();
        }
    }

    public function syncLoads()
    {
        // Global Watchtower handles syncing. Foreground UI just refreshes data.
        $this->isSyncing = false;
    }

    public function showNotes($loadId)
    {
        $load = Load::find($loadId);
        $this->activeLoadNotes = $load ? $load->notes : 'No dispatcher notes provided.';
    }

    public function closeNotes()
    {
        $this->activeLoadNotes = null;
    }

    #[Computed]
    public function loads()
    {
        return Load::with(['dispatcher', 'requests.carrier.user', 'carrier.user']) // Eager load dispatcher
            ->where(function($query) {
                $query->whereNull('carrier_id') // Public Marketplace
                      ->orWhere('carrier_id', Auth::user()->carrier->id); // Specifically assigned to me
            })
            ->whereDoesntHave('requests', function($query) {
                $query->where('carrier_id', Auth::user()->carrier->id)
                      ->where(function($q) {
                          $q->where('status', 'rejected')
                            ->orWhere(function($q) {
                                $q->where('status', 'approved')
                                  ->where('updated_at', '<', now()->subMinutes(30));
                            });
                      });
            })
            ->when($this->search, function ($query) {
                $query->where(function($q) {
                    $q->where('pickup_location', 'like', '%' . $this->search . '%')
                      ->orWhere('drop_location', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);
    }

    public function requestLoad($loadId)
    {
        // Lock the button IMMEDIATELY to prevent double-click
        $this->processingLoadId = $loadId;

        $carrier = Auth::user()->carrier;
        $user = Auth::user();

        // 1. Sync to Cloud FIRST
        try {
            $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/loads/request';
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($apiUrl, [
                'email' => $user->email,
                'load_id' => $loadId
            ]);

            if ($response->successful()) {
                // 2. ONLY Update Local if Cloud SUCCESS
                \App\Models\LoadRequest::updateOrCreate(
                    ['load_id' => $loadId, 'carrier_id' => $carrier->id],
                    ['status' => 'pending']
                );
                
                session()->flash('sync_success', $response->json()['message'] ?? 'Bid recorded on cloud!');
            } else {
                $error = $response->json()['message'] ?? 'Unknown Cloud Error';
                session()->flash('sync_error', "Refused: " . $error);
                
                // Cleanup: Remove local bid if cloud refused (it's not valid on cloud)
                \App\Models\LoadRequest::where('load_id', $loadId)
                    ->where('carrier_id', $carrier->id)
                    ->delete();

                // v51 Auto-Recovery: If load not found on server, refresh stale data
                if ($response->status() === 404) {
                    $this->syncLoads();
                }

                \Illuminate\Support\Facades\Log::warning('Cloud bid refusal', ['error' => $error]);
                $this->processingLoadId = null; // Unlock button for retry
            }
        } catch (\Exception $e) {
            session()->flash('sync_error', 'Network Error: Cloud unreachable. Bid NOT sent.');
            
            // Cleanup local state on hard network failure
            \App\Models\LoadRequest::where('load_id', $loadId)
                ->where('carrier_id', $carrier->id)
                ->delete();

            \Illuminate\Support\Facades\Log::error('Cloud bid sync exception', ['error' => $e->getMessage()]);
            $this->processingLoadId = null; // Unlock button for retry
        }
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10" @if($isApproved) wire:poll.10s="syncLoads" @endif>
    <div class="max-w-md mx-auto space-y-10">

    @if(!$isApproved)
        <!-- Account Not Approved State -->
        <div class="space-y-8 animate-fadeIn">
            <div class="space-y-1">
                <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Find Loads</h1>
                <p class="text-slate-400 font-medium text-sm">Marketplace direct from dispatch</p>
            </div>

            <div class="p-10 glass-morphism border border-yellow-500/20 rounded-[3rem] relative overflow-hidden">
                <div class="flex flex-col items-center text-center space-y-6 relative z-10">
                    <div class="w-20 h-20 rounded-full bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-yellow-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h2 class="text-2xl font-black text-white italic uppercase tracking-tighter">Account Pending</h2>
                        <p class="text-slate-400 text-sm font-medium leading-relaxed max-w-[280px]">Your account is under review. Please complete the onboarding steps to unlock the freight marketplace.</p>
                    </div>
                    <div class="flex flex-col gap-3 w-full pt-2">
                        <a href="/document-upload" class="w-full py-4 rounded-2xl bg-blue-gradient text-center text-[11px] font-black text-white uppercase tracking-[0.2em] shadow-lg shadow-blue-500/30 active:scale-95 transition-all">Upload Documents</a>
                        <a href="/dashboard" class="w-full py-4 rounded-2xl glass-morphism border border-white/10 text-center text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] active:scale-95 transition-all">Back to Dashboard</a>
                    </div>
                </div>
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-yellow-500/5 rounded-full blur-3xl"></div>
            </div>
        </div>
    @else
        <!-- Header -->
        <div class="flex items-end justify-between">
            <div class="space-y-1">
                <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Find Loads</h1>
                <p class="text-slate-400 font-medium text-sm">Marketplace direct from dispatch</p>
            </div>
            <button wire:click="syncLoads" wire:loading.attr="disabled" class="px-3 py-1.5 glass-morphism border border-blue-500/20 rounded-full flex items-center gap-2 group hover:bg-blue-500/10 transition-all active:scale-95 disabled:opacity-50">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full {{ $isSyncing ? 'animate-ping' : 'animate-pulse' }} shadow-[0_0_8px_rgba(59,130,246,0.8)]"></span>
                <span class="text-[9px] font-black text-blue-400 uppercase tracking-widest">{{ $isSyncing ? 'Syncing...' : 'Live Updates' }}</span>
            </button>
        </div>

        @if (session()->has('sync_error'))
            <div class="p-5 glass-morphism border border-red-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <p class="text-red-400 text-[10px] font-black uppercase tracking-widest">Bid Sync Warning</p>
                    <p class="text-red-500/80 text-xs font-bold leading-tight">{{ session('sync_error') }}</p>
                </div>
            </div>
        @endif

        @if (session()->has('sync_success'))
            <div class="p-5 glass-morphism border border-blue-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-blue-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <p class="text-blue-400 text-[10px] font-black uppercase tracking-widest">{{ session('sync_success') }}</p>
            </div>
        @endif

        <!-- Search -->
        <div class="relative group">
            <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-500 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input wire:model.live="search" type="text" class="block w-full pl-14 pr-6 py-5 glass-morphism border border-white/5 rounded-[1.5rem] text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all shadow-[0_10px_30px_-10px_rgba(0,0,0,0.3)]" placeholder="Search locations...">
        </div>

        <!-- Load List -->
        <div class="space-y-6">
            @forelse($this->loads as $load)
                @php
                    $request = Auth::user()->carrier->loadRequests()->where('load_id', $load->id)->first();
                    $status = $request ? $request->status : null;
                @endphp
                <div class="glass-morphism border border-white/5 rounded-[2.5rem] p-8 space-y-8 relative overflow-hidden group hover:border-blue-500/20 transition-all duration-500 shadow-[0_20px_50px_-15px_rgba(0,0,0,0.5)]">
                    <!-- Status Badge -->
                    @if($status === 'approved')
                        <div class="absolute -right-12 top-6 bg-green-500 text-white py-1 px-12 rotate-45 text-[10px] font-black uppercase tracking-widest shadow-xl">SUCCESS</div>
                    @elseif($status === 'rejected')
                        <div class="absolute -right-12 top-6 bg-red-500 text-white py-1 px-12 rotate-45 text-[10px] font-black uppercase tracking-widest shadow-xl">CANCELLED</div>
                    @endif

                    <!-- Location Flow -->
                    <div class="flex items-start gap-6">
                        <div class="flex flex-col items-center gap-1 mt-1 shrink-0">
                            <div class="w-4 h-4 rounded-full border-2 border-blue-500 bg-blue-500/20 shadow-[0_0_10px_rgba(59,130,246,0.5)]"></div>
                            <div class="w-0.5 h-16 border-r-2 border-dashed border-white/10"></div>
                            <div class="w-4 h-4 rounded-full border-2 border-slate-700"></div>
                        </div>

                        <div class="flex-1 space-y-8">
                            <div>
                                <div class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">Pickup</div>
                                <div class="text-2xl font-black text-white italic tracking-tight leading-none">{{ $load->pickup_location }}</div>
                                <div class="text-[9px] font-bold text-slate-500 mt-1.5 uppercase tracking-widest">{{ $load->pickup_time ?? '08:00 AM' }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Delivery</div>
                                <div class="text-2xl font-black text-white italic tracking-tight leading-none">{{ $load->drop_location }}</div>
                                <div class="text-[9px] font-bold text-slate-500 mt-1.5 uppercase tracking-widest">{{ $load->drop_off_time ?? '04:00 PM' }}</div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Max Rate</div>
                            <div class="text-3xl font-black text-white italic tracking-tighter drop-shadow-lg leading-none">
                                <span class="text-blue-500 text-lg sm:text-xl font-black mr-0.5">$</span>{{ number_format($load->rate) }}
                            </div>
                            <div class="flex items-center justify-end gap-1.5 mt-2 text-blue-400 group-hover:text-blue-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-widest leading-none italic">{{ $load->miles }} Miles</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Stats Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass-morphism border border-white/5 rounded-2xl p-4 flex flex-col items-center shadow-inner">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Equipment</span>
                            <span class="text-[11px] font-black text-blue-400 uppercase tracking-wider italic text-center leading-tight">{{ $load->equipment_type }}</span>
                        </div>
                        <div class="glass-morphism border border-white/5 rounded-2xl p-4 flex flex-col items-center shadow-inner">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Dispatcher / Broker</span>
                            <span class="text-[11px] font-black text-white uppercase tracking-wider italic leading-tight text-center">{{ $load->broker_name ?? 'Direct' }}</span>
                            {{-- @php
                                $dispPhone = $load->dispatcher_phone ?? ($load->dispatcher ? $load->dispatcher->phone : null);
                            @endphp
                            @if($status === 'approved' && $dispPhone)
                                <div class="mt-2 pt-2 border-t border-white/5 w-full flex flex-col items-center">
                                    <span class="text-[7px] font-black text-blue-500 uppercase tracking-widest mb-0.5">Contact Number</span>
                                    <a href="tel:{{ $dispPhone }}" class="text-[10px] font-black text-green-400 group-hover:text-green-300 transition-colors">{{ $dispPhone }}</a>
                                </div>
                            @endif --}}
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-8 py-2">
                        <div class="flex flex-col items-center">
                            <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest mb-1">Weight</span>
                            <span class="text-xs font-black text-white">{{ number_format($load->weight) }} LBS</span>
                        </div>
                        <div class="w-px h-6 bg-white/5 shadow-[0_0_5px_rgba(255,255,255,0.1)]"></div>
                        <div class="flex flex-col items-center">
                            <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest mb-1">Deadhead</span>
                            <span class="text-xs font-black text-white">{{ $load->deadhead }} MI</span>
                        </div>
                        <div class="w-px h-6 bg-white/5 shadow-[0_0_5px_rgba(255,255,255,0.1)]"></div>
                        <div class="flex flex-col items-center">
                            <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest mb-1">RPM</span>
                            <span class="text-xs font-black text-blue-500 italic">${{ $load->rpm }}/mi</span>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center gap-4 pt-2">
                        <button wire:click="showNotes({{ $load->id }})" class="w-14 h-14 rounded-2xl glass-morphism border border-white/5 flex items-center justify-center group/btn hover:border-blue-500/30 transition-all active:scale-95 shrink-0 shadow-lg" title="View Notes">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-slate-400 group-hover/btn:text-blue-500 transition-colors">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3h9m-9 3h9m-6.75-12.75h3.375c.621 0 1.125.504 1.125 1.125v17.25c0 .621-.504 1.125-1.125 1.125H6.75c-.621 0-1.125-.504-1.125-1.125V3.375c0-.621.504-1.125 1.125-1.125Z" />
                            </svg>
                        </button>
                        {{-- new --}}
                           @php
                                $dispPhone = $load->dispatcher_phone ?? ($load->dispatcher ? $load->dispatcher->phone : null);
                            @endphp
                         
                        @if($status === 'approved' && $dispPhone)
                            <a href="tel:{{ $dispPhone }}" class="w-14 h-14 rounded-2xl glass-morphism border border-white/5 flex items-center justify-center group/btn hover:border-green-500/30 transition-all active:scale-95 shrink-0 shadow-lg" title="Call Dispatcher">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-slate-400 group-hover/btn:text-green-500 transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                            </a>
                        @endif
            {{-- end --}}
                        @php
                            $isProcessing = $processingLoadId === $load->id;
                        @endphp

                        @if($status || $isProcessing)
                            <button disabled class="flex-1 py-5 rounded-2xl glass-morphism border border-white/5 bg-white/5 text-[11px] font-black text-slate-500 uppercase tracking-[0.2em] shadow-inner opacity-50">
                                {{ $isProcessing && !$status ? 'SENDING...' : ($status === 'pending' ? 'REQUESTED' : strtoupper($status)) }}
                            </button>
                        @else
                            <button 
                                wire:click="requestLoad({{ $load->id }})" 
                                wire:loading.attr="disabled"
                                wire:target="requestLoad"
                                class="flex-1 py-5 rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-[11px] font-black text-white uppercase tracking-[0.2em] shadow-[0_10px_25px_-5px_rgba(37,99,235,0.4)] transition-all active:scale-[0.98] drop-shadow-lg disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="requestLoad">REQUEST LOAD</span>
                                <span wire:loading wire:target="requestLoad">SENDING...</span>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-20 flex flex-col items-center justify-center space-y-6 opacity-40">
                    <div class="w-20 h-20 rounded-full border-2 border-dashed border-white/20 flex items-center justify-center shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-black text-white italic tracking-tighter uppercase">No loads found</p>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Market is quiet right now</p>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Pagination (v85) -->
        <div class="mt-6 space-y-3">
            <div class="flex items-center justify-center gap-3">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Page {{ $this->loads->currentPage() }} of {{ $this->loads->lastPage() }}</span>
                <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                <span class="text-[9px] font-black text-blue-500 uppercase tracking-widest">{{ $this->loads->total() }} Loads</span>
            </div>
            {{ $this->loads->links() }}
        </div>

        <!-- SYNC INTEL FOOTER -->
        <div class="pt-10 pb-20 border-t border-white/5 text-center space-y-2">
            <div class="flex items-center justify-center gap-2">
                <span class="w-1 h-1 rounded-full animate-pulse bg-current {{ $syncColor }}"></span>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] {{ $syncColor }}">{{ $lastSyncInfo }}</p>
            </div>
            <p class="text-[8px] font-bold text-slate-600 uppercase tracking-widest">Global Marketplace Monitor v71</p>
        </div>
    @endif
</div>

    <!-- Notes Modal -->
    @if($activeLoadNotes !== null)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-6 animate-fadeIn">
            <div wire:click="closeNotes" class="absolute inset-0 bg-slate-950/80 backdrop-blur-md"></div>
            
            <div class="relative w-full max-w-sm glass-morphism border border-white/10 rounded-[2.5rem] p-10 space-y-8 shadow-2xl animate-scaleIn">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-black text-white italic uppercase tracking-tighter leading-none">Dispatcher Notes</h2>
                        <div class="h-1 w-12 bg-blue-500 rounded-full shadow-[0_0_8px_rgba(59,130,246,0.8)]"></div>
                    </div>
                    <button wire:click="closeNotes" class="w-10 h-10 rounded-full glass-morphism border border-white/5 flex items-center justify-center hover:bg-white/10 transition-all text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="text-slate-300 font-medium leading-relaxed whitespace-pre-wrap max-h-60 overflow-y-auto custom-scrollbar italic">
                    {{ $activeLoadNotes }}
                </div>

                <button wire:click="closeNotes" class="w-full py-5 rounded-2xl bg-white/5 border border-white/10 text-[11px] font-black text-white uppercase tracking-[0.2em] hover:bg-white/10 transition-all active:scale-95 leading-none">
                    CLOSE NOTES
                </button>
            </div>
        </div>
    @endif

</div>
