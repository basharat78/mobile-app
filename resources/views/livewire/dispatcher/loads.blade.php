<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Load;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component 
{
    public $pickup_location = '';
    public $pickup_time = '';
    public $drop_location = '';
    public $drop_off_time = '';
    public $deadhead = '';
    public $miles = '';
    public $total_miles = '';
    public $rate = '';
    public $rpm = '';
    public $equipment_type = 'Dry Van';
    public $weight = '';
    public $broker_name = '';
    public $notes = '';
    public $carrier_id = '';
    public $editingLoadId = null;
    public $showingNotes = false;
    public $selectedLoadNotes = '';

    public function updatedMiles()
    {
        $this->calculateTotals();
    }

    public function updatedDeadhead()
    {
        $this->calculateTotals();
    }

    public function updatedRate()
    {
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        $miles = is_numeric($this->miles) ? floatval($this->miles) : 0;
        $deadhead = is_numeric($this->deadhead) ? floatval($this->deadhead) : 0;
        $rate = is_numeric($this->rate) ? floatval($this->rate) : 0;

        $this->total_miles = $miles + $deadhead;

        if ($miles > 0) {
            $this->rpm = number_format($rate / $miles, 2, '.', '');
        } else {
            $this->rpm = '0.00';
        }
    }

    #[Computed]
    public function carriers()
    {
        return Auth::user()->managedCarriers()->with('user')->get();
    }

    #[Computed]
    public function loads()
    {
        return Load::with(['requests.carrier.user', 'carrier.user'])
            ->where('dispatcher_id', Auth::id())
            ->latest()
            ->get();
    }

    public function saveLoad()
    {
        $this->validate([
            'pickup_location' => 'required|string|max:255',
            'drop_location' => 'required|string|max:255',
            'miles' => 'required|numeric|min:1',
            'rate' => 'required|numeric|min:0',
            'equipment_type' => 'required|string',
            'weight' => 'nullable|numeric|min:1',
            'carrier_id' => 'required|exists:carriers,id',
        ]);

        $data = [
            'pickup_location' => $this->pickup_location,
            'pickup_time' => $this->pickup_time,
            'drop_location' => $this->drop_location,
            'drop_off_time' => $this->drop_off_time,
            'deadhead' => $this->deadhead,
            'miles' => $this->miles,
            'total_miles' => $this->total_miles,
            'rate' => $this->rate,
            'rpm' => $this->rpm,
            'equipment_type' => $this->equipment_type,
            'weight' => $this->weight,
            'broker_name' => $this->broker_name,
            'notes' => $this->notes,
            'carrier_id' => $this->carrier_id,
        ];

        if ($this->editingLoadId) {
            Load::find($this->editingLoadId)->update($data);
            session()->flash('message', 'Load updated successfully.');
        } else {
            $data['dispatcher_id'] = Auth::id();
            $data['status'] = 'available';
            Load::create($data);
            session()->flash('message', 'Load created successfully.');
        }

        $this->cancelEdit();
    }

    public function editLoad($id)
    {
        $load = Load::find($id);
        $this->editingLoadId = $id;
        $this->pickup_location = $load->pickup_location;
        $this->pickup_time = $load->pickup_time;
        $this->drop_location = $load->drop_location;
        $this->drop_off_time = $load->drop_off_time;
        $this->deadhead = $load->deadhead;
        $this->miles = $load->miles;
        $this->total_miles = $load->total_miles;
        $this->rate = $load->rate;
        $this->rpm = $load->rpm;
        $this->equipment_type = $load->equipment_type;
        $this->weight = $load->weight;
        $this->broker_name = $load->broker_name;
        $this->notes = $load->notes;
        $this->carrier_id = $load->carrier_id;
    }

    public function cancelEdit()
    {
        $this->editingLoadId = null;
        $this->reset([
            'pickup_location',
            'pickup_time',
            'drop_location',
            'drop_off_time',
            'deadhead',
            'miles',
            'total_miles',
            'rate',
            'rpm',
            'equipment_type',
            'weight',
            'broker_name',
            'notes',
            'carrier_id'
        ]);
    }

    public function deleteLoad($id)
    {
        Load::find($id)->delete();
        session()->flash('message', 'Load deleted.');
    }

    public function approveBid($requestId)
    {
        $request = \App\Models\LoadRequest::with('loadJob')->find($requestId);
        $request->update(['status' => 'approved']);
        $request->loadJob->update(['status' => 'booked']);

        \App\Models\LoadRequest::where('load_id', $request->load_id)
            ->where('id', '!=', $requestId)
            ->update(['status' => 'rejected']);

        session()->flash('message', 'Bid approved and load booked!');
    }

    public function showNotes($id)
    {
        $load = Load::find($id);
        $this->selectedLoadNotes = $load->notes;
        $this->showingNotes = true;
    }

    public function closeNotes()
    {
        $this->showingNotes = false;
        $this->selectedLoadNotes = '';
    }
};
?>

<div class="p-8 space-y-8 bg-slate-900 min-h-screen">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Load Management</h1>
            <p class="text-slate-500 font-medium">Post and track freight across the network</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div
            class="p-5 bg-green-500/10 border border-green-500/20 rounded-3xl text-green-500 text-sm font-bold flex items-center gap-3 animate-fade-in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"
                class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        <!-- Post/Edit Load Form -->
        <div class="bg-slate-800/50 border border-white/5 rounded-[2.5rem] p-8 space-y-6 lg:sticky lg:top-8">
            <h3 class="text-xl font-black text-white italic">{{ $editingLoadId ? 'Edit Load' : 'Post New Load' }}</h3>
            <form wire:submit="saveLoad" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Pick
                            Location</label>
                        <input wire:model="pickup_location" type="text"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="City, ST">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Pick
                            Time</label>
                        <input wire:model="pickup_time" type="text"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="08:00 AM">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Drop
                            Off Location</label>
                        <input wire:model="drop_location" type="text"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="City, ST">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Drop
                            Off Time</label>
                        <input wire:model="drop_off_time" type="text"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="4:00 PM">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Miles</label>
                        <input wire:model.live="miles" type="number"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="500">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Deadhead</label>
                        <input wire:model.live="deadhead" type="number"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="50">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Total
                            Miles</label>
                        <input wire:model="total_miles" type="number" readonly
                            class="w-full bg-slate-800 border border-white/5 rounded-2xl px-5 py-4 text-slate-400 text-sm outline-none font-bold cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Rate
                            ($)</label>
                        <input wire:model.live="rate" type="number"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="1500">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">RPM</label>
                        <input wire:model="rpm" type="text" readonly
                            class="w-full bg-slate-800 border border-white/5 rounded-2xl px-5 py-4 text-slate-400 text-sm outline-none font-bold cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Weight
                            (lbs)</label>
                        <input wire:model="weight" type="number"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="45000">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Equipment
                            Type</label>
                        <select wire:model="equipment_type"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                            <option value="Dry Van">Dry Van</option>
                            <option value="Reefer">Reefer</option>
                            <option value="Flatbed">Flatbed</option>
                            <option value="Step Deck">Step Deck</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Target
                            Carrier</label>
                        <select wire:model="carrier_id"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                            <option value="">Select Carrier</option>
                            @foreach($this->carriers as $carrier)
                                <option value="{{ $carrier->id }}">{{ $carrier->user->name ?? 'Unknown' }}
                                    ({{ $carrier->user->company_name ?? 'No Company' }})</option>
                            @endforeach
                        </select>
                        @error('carrier_id') <span class="text-red-500 text-[10px] font-bold ml-2">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Broker
                            Name</label>
                        <input wire:model="broker_name" type="text"
                            class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                            placeholder="Brokerage Inc.">
                    </div>
                </div>

                <div>
                    <label
                        class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Note</label>
                    <textarea wire:model="notes" rows="2"
                        class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                        placeholder="Additional details..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 bg-blue-600 text-white font-black uppercase tracking-widest py-4 rounded-2xl hover:bg-blue-500 transition-all shadow-xl shadow-blue-500/20 active:scale-95">
                        {{ $editingLoadId ? 'Update' : 'Post' }}
                    </button>
                    @if($editingLoadId)
                        <button type="button" wire:click="cancelEdit"
                            class="px-6 bg-slate-800 text-slate-400 font-black uppercase tracking-widest py-4 rounded-2xl hover:text-white transition-all">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <!-- Load List -->
        <div class="lg:col-span-2 space-y-6">
            <h3 class="text-xl font-black text-white italic">Active Freight Board</h3>
            <div class="space-y-4">
                @foreach($this->loads as $load)
                    <div
                        class="bg-slate-800/30 border {{ $editingLoadId == $load->id ? 'border-blue-500/50' : 'border-white/5' }} rounded-[2.5rem] p-6 group hover:border-blue-500/30 transition-all">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-blue-600/10 rounded-2xl flex items-center justify-center font-black text-blue-500 italic text-xl shrink-0">
                                    {{ substr($load->equipment_type ?? 'X', 0, 1) }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <div
                                            class="flex items-center gap-2 px-2 py-1 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="2.5" stroke="currentColor" class="w-3 h-3 text-blue-400">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="text-[9px] font-black text-blue-400 uppercase tracking-widest">For:
                                                {{ $load->carrier->user->name ?? 'Unknown' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h4 class="text-white font-bold text-lg">{{ $load->pickup_location }}</h4>
                                        @if($load->pickup_time)
                                            <span
                                                class="px-2 py-0.5 bg-blue-500/10 border border-blue-500/20 rounded text-[9px] font-black text-blue-400 uppercase tracking-widest shrink-0">{{ $load->pickup_time }}</span>
                                        @endif
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-600 shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                        <h4 class="text-white font-bold text-lg">{{ $load->drop_location }}</h4>
                                        @if($load->drop_off_time)
                                            <span
                                                class="px-2 py-0.5 bg-blue-500/10 border border-blue-500/20 rounded text-[9px] font-black text-blue-400 uppercase tracking-widest shrink-0">{{ $load->drop_off_time }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                                        <p
                                            class="text-[10px] text-slate-500 font-black uppercase tracking-widest whitespace-nowrap">
                                            {{ $load->miles }} Miles @if($load->deadhead) (+{{ $load->deadhead }} DH) @endif
                                            • {{ $load->equipment_type }}</p>
                                        @if($load->weight)
                                            <p
                                                class="text-[10px] text-slate-500 font-black uppercase tracking-widest whitespace-nowrap">
                                                {{ number_format($load->weight) }} lbs</p>
                                        @endif
                                        @if($load->rpm > 0)
                                            <p
                                                class="text-[10px] text-blue-500 font-black uppercase tracking-widest whitespace-nowrap">
                                                ${{ $load->rpm }}/mi</p>
                                        @endif
                                        @if($load->broker_name)
                                            <p
                                                class="text-[10px] text-slate-400 font-black uppercase tracking-widest flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="currentColor" class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                                </svg>
                                                {{ $load->broker_name }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-left md:text-right ml-16 md:ml-0 mt-2 md:mt-0">
                                <span
                                    class="text-2xl font-black text-white italic tracking-tighter block leading-none">${{ number_format($load->rate) }}</span>
                                <div class="flex gap-3 justify-start md:justify-end mt-2">
                                
                                     <button wire:click="showNotes({{ $load->id }})"
                                         class="p-2 bg-slate-600/10 rounded-lg text-slate-400 hover:bg-slate-600/20 transition-all">
                                                                                     <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                            </svg>
                                     </button>
                                     <button wire:click="editLoad({{ $load->id }})"
                                        class="p-2 bg-blue-600/10 rounded-lg text-blue-500 hover:bg-blue-600/20 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteLoad({{ $load->id }})"
                                        class="p-2 bg-red-600/10 rounded-lg text-red-500 hover:bg-red-600/20 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Requests Section -->
                        @if($load->requests->count() > 0)
                            <div class="mt-4 pt-4 border-t border-white/5 space-y-3">
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Bids / Requests
                                    ({{ $load->requests->count() }})</p>
                                @foreach($load->requests as $request)
                                    <div
                                        class="bg-slate-900/50 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 group/bid hover:bg-slate-900 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center text-xs font-bold text-slate-400 group-hover/bid:bg-blue-600 group-hover/bid:text-white transition-all">
                                                {{ substr($request->carrier->user->name ?? '?', 0, 1) }}
                                            </div>
                                            <div>
                                                <span
                                                    class="text-sm font-bold text-white block">{{ $request->carrier->user->name ?? 'Unknown Carrier' }}</span>
                                                <span
                                                    class="text-[9px] text-slate-600 font-bold uppercase tracking-widest">{{ $request->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between sm:justify-end gap-4 w-full sm:w-auto">
                                            <span
                                                class="px-3 py-1 bg-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500/10 text-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500 rounded-full text-[10px] font-black uppercase tracking-widest shrink-0">
                                                {{ $request->status }}
                                            </span>
                                            @if($request->status === 'pending' && $load->status === 'available')
                                                <button wire:click="approveBid({{ $request->id }})"
                                                    class="flex-1 sm:flex-none px-4 py-2 bg-blue-600 rounded-xl text-[10px] font-black uppercase tracking-widest text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500 transition-all active:scale-95">Approve</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- Notes Modal -->
    @if($showingNotes)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-800 border border-white/10 rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="p-8 space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-black text-white italic tracking-tighter uppercase">Load Notes</h3>
                        <button wire:click="closeNotes" class="text-slate-500 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                        @if($selectedLoadNotes)
                            <p class="text-slate-300 font-medium leading-relaxed italic whitespace-pre-wrap">"{{ $selectedLoadNotes }}"</p>
                        @else
                            <p class="text-slate-500 font-bold uppercase tracking-widest text-center py-8">No notes attached to this load.</p>
                        @endif
                    </div>

                    <button wire:click="closeNotes" class="w-full bg-slate-700 text-white font-black uppercase tracking-widest py-4 rounded-2xl hover:bg-slate-600 transition-all active:scale-95">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
