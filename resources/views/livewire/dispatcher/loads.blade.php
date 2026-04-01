<?php

// use Livewire\Attributes\Layout;
// use Livewire\Volt\Component;
// use App\Models\Load;
// use Illuminate\Support\Facades\Auth;

// new #[Layout('components.layouts.app')] class extends Component
// {
//     public $pickup_location = '';
//     public $drop_location = '';
//     public $miles = '';
//     public $rate = '';
//     public $equipment_type = 'Dry Van';
//     public $notes = '';
//     public $editingLoadId = null;
    

//   public function saveLoad(){
//     $this->validate([
//         'pickup_location' => 'required|string|max:255',
//         'drop_location' => 'required|string|max:255',
//         'miles' => 'required|integer|min:1',
//         'rate' => 'required|numeric|min:0',
//         'equipment_type' => 'required|string',
//     ]);

//     if($this->editingLoadId){
//         $load = Load::find($this->editingLoadId);
//         if($load && $load->dispatcher_id === Auth::id()){
//             $load->update([
//                 'pickup_location' => $this->pickup_location,
//                 'drop_location' => $this->drop_location,
//                 'miles' => $this->miles,
//                 'rate' => $this->rate,
//                 'equipment_type' => $this->equipment_type,
//                 'notes' => $this->notes,
//             ]);
//             session()->flash('message', 'Load updated successfully!');
//         } else {
//             session()->flash('message', 'Load not found or unauthorized.');
//         }
//     } else {
//         Load::create([
//             'dispatcher_id' => Auth::id(),
//             'pickup_location' => $this->pickup_location,
//             'drop_location' => $this->drop_location,
//             'miles' => $this->miles,
//             'rate' => $this->rate,
//             'equipment_type' => $this->equipment_type,
//             'notes' => $this->notes,
//         ]);
//         session()->flash('message', 'Load posted successfully!');
//     }

//     // Reset form fields
//     $this->reset(['pickup_location', 'drop_location', 'miles', 'rate', 'equipment_type', 'notes']);
//   }
//   public function editLoad($id){
//     $load = Load::find($id);
//     if($load && $load->dispatcher_id === Auth::id()){
//         $this->pickup_location = $load->pickup_location;
//         $this->drop_location = $load->drop_location;
//         $this->miles = $load->miles;
//         $this->rate = $load->rate;
//         $this->equipment_type = $load->equipment_type;
//         $this->notes = $load->notes;
//         $this->editingLoadId = $id;
//     } else {
//         session()->flash('message', 'Load not found or unauthorized.');
//   }
//     }
//     public function cancelEdit(){
//         $this->editingLoadId = null;
//         $this->reset(['pickup_location', 'drop_location', 'miles', 'rate', 'equipment_type', 'notes']);
//     }
//     public function deleteLoad($id)
//     {
//         Load::find($id)->delete();
//         session()->flash('message', 'Load deleted.');
//     }


// };


use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Load;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component
{
    public $pickup_location = '';
    public $drop_location = '';
    public $miles = '';
    public $rate = '';
    public $equipment_type = 'Dry Van';
    public $notes = '';
    public $editingLoadId = null;

    // public function getLoadsProperty()
    // {
    //     return Load::all();
    // }
 public function getLoadsProperty()
    {
        return Load::with('requests.carrier.user')
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
        ]);

        if ($this->editingLoadId) {
            Load::find($this->editingLoadId)->update([
                'pickup_location' => $this->pickup_location,
                'drop_location' => $this->drop_location,
                'miles' => $this->miles,
                'rate' => $this->rate,
                'equipment_type' => $this->equipment_type,
                'notes' => $this->notes,
            ]);
            session()->flash('message', 'Load updated successfully.');
        } else {
            Load::create([
                'dispatcher_id' => Auth::id(),
                'pickup_location' => $this->pickup_location,
                'drop_location' => $this->drop_location,
                'miles' => $this->miles,
                'rate' => $this->rate,
                'equipment_type' => $this->equipment_type,
                'notes' => $this->notes,
                'status' => 'available',
            ]);
            session()->flash('message', 'Load created successfully.');
        }

        $this->cancelEdit();
    }

    public function editLoad($id)
    {
        $load = Load::find($id);
        $this->editingLoadId = $id;
        $this->pickup_location = $load->pickup_location;
        $this->drop_location = $load->drop_location;
        $this->miles = $load->miles;
        $this->rate = $load->rate;
        $this->equipment_type = $load->equipment_type;
        $this->notes = $load->notes;
    }

    public function cancelEdit()
    {
        $this->editingLoadId = null;
        $this->reset(['pickup_location', 'drop_location', 'miles', 'rate', 'notes', 'equipment_type']);
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
        <div class="p-5 bg-green-500/10 border border-green-500/20 rounded-3xl text-green-500 text-sm font-bold flex items-center gap-3 animate-fade-in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5">
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
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Pickup Location</label>
                        <input wire:model="pickup_location" type="text" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold" placeholder="City, ST">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Drop Location</label>
                        <input wire:model="drop_location" type="text" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold" placeholder="City, ST">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Miles</label>
                        <input wire:model="miles" type="number" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold" placeholder="500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Rate ($)</label>
                        <input wire:model="rate" type="number" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold" placeholder="1500">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2 mb-1">Equipment Type</label>
                    <select wire:model="equipment_type" class="w-full bg-slate-900 border border-white/5 rounded-2xl px-5 py-4 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                        <option value="Dry Van">Dry Van</option>
                        <option value="Reefer">Reefer</option>
                        <option value="Flatbed">Flatbed</option>
                        <option value="Step Deck">Step Deck</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-blue-600 text-white font-black uppercase tracking-widest py-4 rounded-2xl hover:bg-blue-500 transition-all shadow-xl shadow-blue-500/20 active:scale-95">
                        {{ $editingLoadId ? 'Update' : 'Post' }}
                    </button>
                    @if($editingLoadId)
                        <button type="button" wire:click="cancelEdit" class="px-6 bg-slate-800 text-slate-400 font-black uppercase tracking-widest py-4 rounded-2xl hover:text-white transition-all">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <!-- Load List -->
        <div class="lg:col-span-2 space-y-6">
            <h3 class="text-xl font-black text-white italic">Active Freight Board</h3>
            <div class="space-y-4">
                @foreach($this->loads as $load)
                    <div class="bg-slate-800/30 border {{ $editingLoadId == $load->id ? 'border-blue-500/50' : 'border-white/5' }} rounded-[2.5rem] p-6 group hover:border-blue-500/30 transition-all">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-600/10 rounded-2xl flex items-center justify-center font-black text-blue-500 italic text-xl">
                                    {{ substr($load->equipment_type ?? 'X', 0, 1) }}
                                </div>
                                <div>
                                    <h4 class="text-white font-bold text-lg">{{ $load->pickup_location }} → {{ $load->drop_location }}</h4>
                                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest">{{ $load->miles }} Miles • {{ $load->equipment_type }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-2xl font-black text-white italic tracking-tighter">${{ number_format($load->rate) }}</span>
                                <div class="flex gap-3 justify-end mt-2">
                                    <button wire:click="editLoad({{ $load->id }})" class="p-2 bg-blue-600/10 rounded-lg text-blue-500 hover:bg-blue-600/20 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteLoad({{ $load->id }})" class="p-2 bg-red-600/10 rounded-lg text-red-500 hover:bg-red-600/20 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Requests Section -->
                        @if($load->requests->count() > 0)
                            <div class="mt-4 pt-4 border-t border-white/5 space-y-3">
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Bids / Requests ({{ $load->requests->count() }})</p>
                                @foreach($load->requests as $request)
                                    <div class="bg-slate-900/50 rounded-2xl p-4 flex items-center justify-between group/bid hover:bg-slate-900 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center text-xs font-bold text-slate-400 group-hover/bid:bg-blue-600 group-hover/bid:text-white transition-all">
                                                {{ substr($request->carrier->user->name ?? '?', 0, 1) }}
                                            </div>
                                            <div>
                                                <span class="text-sm font-bold text-white block">{{ $request->carrier->user->name ?? 'Unknown Carrier' }}</span>
                                                <span class="text-[9px] text-slate-600 font-bold uppercase tracking-widest">{{ $request->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span class="px-3 py-1 bg-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500/10 text-{{ $request->status === 'approved' ? 'green' : ($request->status === 'pending' ? 'yellow' : 'red') }}-500 rounded-full text-[10px] font-black uppercase tracking-widest">
                                                {{ $request->status }}
                                            </span>
                                            @if($request->status === 'pending' && $load->status === 'available')
                                                <button wire:click="approveBid({{ $request->id }})" class="px-4 py-2 bg-blue-600 rounded-xl text-[10px] font-black uppercase tracking-widest text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500 transition-all active:scale-95">Approve</button>
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
</div>
