<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Carrier;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $search = '';
    public $statusFilter = '';
    public $view = 'my'; // 'my' or 'all'

    public function getCarriersProperty()
    {
        $query = Carrier::with(['user', 'documents'])
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('company_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            });

        if ($this->view === 'my') {
            $query->where('dispatcher_id', Auth::id());
        }

        return $query->latest()->get();
    }

    public function setView($view)
    {
        $this->view = $view;
    }

    public function assignCarrier($carrierId)
    {
        Carrier::where('id', $carrierId)->update(['dispatcher_id' => Auth::id()]);
        session()->flash('message', 'Carrier successfully assigned to your account.');
    }

    public function unassignCarrier($carrierId)
    {
        Carrier::where('id', $carrierId)->update(['dispatcher_id' => null]);
        session()->flash('message', 'Carrier unassigned from your account.');
    }

    public function updateStatus($carrierId, $status)
    {
        Carrier::find($carrierId)->update(['status' => $status]);
        session()->flash('message', 'Carrier status updated to ' . $status);
    }

    public function updateDocStatus($docId, $status)
    {
        $doc = CarrierDocument::find($docId);
        $doc->update(['status' => $status]);
        
        $carrier = $doc->carrier;
        
        if ($status === 'approved') {
            $approvedDocsCount = $carrier->documents()->where('status', 'approved')->count();
            if ($approvedDocsCount >= 3 && $carrier->status === 'pending') {
                $carrier->update(['status' => 'approved']);
                session()->flash('message', 'Document approved. Carrier also automatically approved.');
                return;
            }
        }

        session()->flash('message', 'Document status updated to ' . $status);
    }
};
?>

<div class="p-8 space-y-8 bg-slate-900 min-h-screen">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Carrier Management</h1>
            <p class="text-slate-500 font-medium">Manage and verify your logistics partners</p>
        </div>
        <div class="flex gap-4">
            <div class="p-1 bg-slate-800 border border-white/5 rounded-xl flex items-center">
                <button wire:click="setView('my')" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all {{ $view === 'my' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:text-slate-300' }}">My Carriers</button>
                <button wire:click="setView('all')" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all {{ $view === 'all' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:text-slate-300' }}">All Marketplace</button>
            </div>
            <select wire:model.live="statusFilter" class="bg-slate-800 border border-white/5 rounded-xl px-4 py-2 text-sm text-white outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <input wire:model.live="search" type="text" placeholder="Search..." class="bg-slate-800 border border-white/5 rounded-xl px-4 py-2 text-sm text-white outline-none focus:ring-2 focus:ring-blue-500 w-48">
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-5 bg-green-500/10 border border-green-500/20 rounded-3xl text-green-500 text-xs font-bold flex items-center gap-3 animate-fade-in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6">
        @forelse($this->carriers as $carrier)
            <div class="bg-slate-800/40 border border-white/5 rounded-[2.5rem] p-8 space-y-6 relative overflow-hidden group hover:border-blue-500/30 transition-all">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-blue-600/10 rounded-[1.5rem] flex items-center justify-center font-black text-2xl text-blue-500 group-hover:bg-blue-600 group-hover:text-white transition-all">
                            {{ substr($carrier->user->name ?? '?', 0, 1) }}
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white italic tracking-tight">{{ $carrier->user->name ?? 'Unknown' }}</h3>
                            <p class="text-slate-400 font-bold uppercase tracking-widest text-[10px]">{{ $carrier->user->company_name ?? 'No Company' }} • {{ $carrier->user->phone ?? 'No Phone' }}</p>
                            @if($carrier->dispatcher_id)
                                <p class="text-[9px] text-blue-500 font-black uppercase tracking-[0.2em] mt-1">Managed by {{ $carrier->dispatcher_id === Auth::id() ? 'You' : $carrier->dispatcher->name }}</p>
                            @else
                                <p class="text-[9px] text-yellow-500/50 font-black uppercase tracking-[0.2em] mt-1 italic">Unassigned Carrier</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-3">
                        <div class="flex gap-2">
                            @if($carrier->dispatcher_id === Auth::id())
                                <button wire:click="unassignCarrier({{ $carrier->id }})" class="px-4 py-1.5 bg-red-500/10 text-red-500 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-red-500/20 transition-all">Unassign</button>
                            @elseif(!$carrier->dispatcher_id)
                                <button wire:click="assignCarrier({{ $carrier->id }})" class="px-4 py-1.5 bg-blue-600 text-white rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-blue-500 shadow-lg shadow-blue-500/20 transition-all">Assign To Me</button>
                            @endif
                            <span class="px-4 py-1.5 bg-{{ $carrier->status === 'approved' ? 'green' : ($carrier->status === 'pending' ? 'yellow' : 'red') }}-500/10 text-{{ $carrier->status === 'approved' ? 'green' : ($carrier->status === 'pending' ? 'yellow' : 'red') }}-500 rounded-full text-[10px] font-black uppercase tracking-widest">
                                {{ $carrier->status }}
                            </span>
                        </div>
                        <div class="flex gap-3">
                            @if($carrier->status !== 'approved')
                                <button wire:click="updateStatus({{ $carrier->id }}, 'approved')" class="text-[9px] font-black uppercase tracking-widest text-green-500 hover:text-green-400">Approve Account</button>
                            @endif
                            @if($carrier->status !== 'rejected')
                                <button wire:click="updateStatus({{ $carrier->id }}, 'rejected')" class="text-[9px] font-black uppercase tracking-widest text-red-500 hover:text-red-400">Reject</button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="pt-6 border-t border-white/5">
                    <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Verification Artifacts</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach(['mc_authority', 'insurance', 'w9'] as $type)
                            @php 
                                $doc = $carrier->documents->where('type', $type)->first();
                            @endphp
                            <div class="p-5 bg-slate-900/50 rounded-2xl border border-white/5 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black text-white uppercase tracking-widest">{{ str_replace('_', ' ', $type) }}</span>
                                    @if($doc)
                                        <div class="w-1.5 h-1.5 rounded-full bg-{{ $doc->status === 'approved' ? 'green' : ($doc->status === 'pending' ? 'yellow' : 'red') }}-500"></div>
                                    @else
                                        <div class="w-1.5 h-1.5 rounded-full bg-slate-800"></div>
                                    @endif
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    @if($doc)
                                        <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="text-[9px] font-black text-blue-500 uppercase tracking-widest hover:underline">Download</a>
                                        <div class="flex gap-1">
                                            @if($doc->status !== 'approved')
                                                <button wire:click="updateDocStatus({{ $doc->id }}, 'approved')" class="w-7 h-7 bg-green-500/10 text-green-500 rounded-lg flex items-center justify-center hover:bg-green-500/20 transition-all font-bold text-xs">✓</button>
                                            @endif
                                            @if($doc->status !== 'rejected')
                                                <button wire:click="updateDocStatus({{ $doc->id }}, 'rejected')" class="w-7 h-7 bg-red-500/10 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-500/20 transition-all font-bold text-xs">✕</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[9px] text-slate-700 italic font-medium">No submission</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Preferences Preview -->
                <div class="pt-6 border-t border-white/5 flex flex-wrap gap-8">
                    <div class="space-y-1">
                        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest">Route Priority</p>
                        <p class="text-xs font-bold text-slate-300">{{ $carrier->preferred_origin ?? 'Any' }} <span class="text-blue-500">→</span> {{ $carrier->preferred_destination ?? 'Any' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest">Equipment</p>
                        <p class="text-xs font-bold text-slate-300">{{ $carrier->preferred_equipment ?? 'Any' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest">Rate Floor</p>
                        <p class="text-xs font-black text-blue-400 italic">${{ number_format($carrier->min_rate ?? 0, 2) }}/mi</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-24 bg-slate-800/10 border border-dashed border-white/5 rounded-[3rem]">
                <div class="w-20 h-20 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-slate-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">No matched carriers found</p>
            </div>
        @endforelse
    </div>
</div>
