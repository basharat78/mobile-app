<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\DocumentUploaded;
use Illuminate\Support\Facades\Notification;
use Native\Mobile\Facades\Camera;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;

new #[Layout('components.layouts.app')] class extends Component
{
    public $pendingDocType = null;
    public $uploadStatus = '';
    public $syncing = []; // Track which docs are currently uploading
    public $isProcessing = false; // Add global processing flag
    public $docsSynced = false; // v87: Track if cloud sync completed

    public function mount()
    {
        $this->syncDocStatuses();
    }

    public function syncDocStatuses()
    {
        $user = Auth::user();
        $carrier = $user->carrier;
        if (!$carrier) return;

        try {
            $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/status/' . $user->email;
            $response = \Illuminate\Support\Facades\Http::timeout(8)->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                        // Sync document statuses from cloud to local DB
                        if (!empty($data['documents'])) {
                            foreach ($data['documents'] as $remoteDoc) {
                                $localDoc = \App\Models\CarrierDocument::where('carrier_id', $carrier->id)->where('type', $remoteDoc['type'])->first();

                                \App\Models\CarrierDocument::updateOrCreate(
                                    ['carrier_id' => $carrier->id, 'type' => $remoteDoc['type']],
                                    [
                                        'status' => $remoteDoc['status'],
                                        'file_path' => $localDoc->file_path ?? ('cloud_synced/' . $remoteDoc['type']),
                                    ]
                                );
                            }
                        }

                $this->docsSynced = true;
            } else {
                // API responded but not successfully - still mark as synced to show UI
                $this->docsSynced = true;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Doc status sync failed', ['error' => $e->getMessage()]);
            // Mark as synced anyway so user isn't stuck on loading forever
            $this->docsSynced = true;
        }
    }

    public function pickFromGallery($type)
    {
        if ($this->isProcessing) return;
        $this->isProcessing = true;

        $this->pendingDocType = $type;
        session()->put('pending_doc_type', $type);

        Camera::pickImages('image', false, 1)
            ->id('doc_upload_' . $type)
            ->start();
    }

    public function scanWithCamera($type)
    {
        if ($this->isProcessing) return;
        $this->isProcessing = true;

        $this->pendingDocType = $type;
        session()->put('pending_doc_type', $type);

        Camera::getPhoto()
            ->id('doc_scan_' . $type)
            ->start();
    }

    #[OnNative(PhotoTaken::class)]
    public function handlePhotoTaken(string $path, string $mimeType = 'image/jpeg', ?string $id = null)
    {
        $type = session()->get('pending_doc_type');
        if (!$type) return;

        $this->syncing[$type] = true;
        $this->saveDocument($type, $path);
        unset($this->syncing[$type]);
    }

    #[OnNative(MediaSelected::class)]
    public function handleMediaSelected(bool $success, array $files = [], int $count = 0, ?string $error = null, bool $cancelled = false, ?string $id = null)
    {
        if (!$success || $cancelled || empty($files)) return;

        $type = session()->get('pending_doc_type');
        if (!$type) return;

        $filePath = $files[0]['path'] ?? ($files[0] ?? null);
        if (!$filePath) return;

        $this->syncing[$type] = true;
        $this->saveDocument($type, $filePath);
        unset($this->syncing[$type]);
    }

    protected function saveDocument($type, $sourcePath)
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $type . '_' . time() . '.' . $extension;
        $destination = 'documents/' . $filename;

        // Copy file to local storage
        $storagePath = storage_path('app/public/' . $destination);
        $dir = dirname($storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($sourcePath)) {
            copy($sourcePath, $storagePath);
        }

        // Save to local DB
        CarrierDocument::create([
            'carrier_id' => Auth::user()->carrier->id,
            'type' => $type,
            'file_path' => $destination,
            'status' => 'pending',
        ]);

        // Sync to remote Hostinger MySQL via API
        try {
            $this->isProcessing = true;
            $apiUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/documents/upload';

            $httpRequest = \Illuminate\Support\Facades\Http::timeout(30);

            if (file_exists($sourcePath)) {
                $httpRequest = $httpRequest->attach(
                    'file',
                    file_get_contents($sourcePath),
                    $filename
                );
            }

            $response = $httpRequest->post($apiUrl, [
                'carrier_id' => Auth::user()->carrier->remote_id ?? Auth::user()->carrier->id,
                'type' => $type,
                'user_name' => Auth::user()->name,
                'dispatcher_id' => Auth::user()->carrier->dispatcher_id,
            ]);

            if ($response->successful()) {
                \Illuminate\Support\Facades\Log::info('Document remote sync SUCCESS', $response->json());
            } else {
                \Illuminate\Support\Facades\Log::warning('Document remote sync FAILED', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $syncError) {
            \Illuminate\Support\Facades\Log::error('Document remote sync ERROR', [
                'error' => $syncError->getMessage(),
            ]);
        }

        // Notify dispatcher(s) locally
        $carrier = Auth::user()->carrier;
        if ($carrier->dispatcher_id) {
            $dispatcher = User::find($carrier->dispatcher_id);
            if ($dispatcher) {
                $dispatcher->notify(new DocumentUploaded(Auth::user()->name, $type));
            }
        } else {
            $dispatchers = User::where('role', 'dispatcher')->get();
            Notification::send($dispatchers, new DocumentUploaded(Auth::user()->name, $type));
        }

        session()->forget('pending_doc_type');
        $this->isProcessing = false;
        $this->uploadStatus = ucfirst(str_replace('_', ' ', $type)) . ' uploaded successfully!';
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10" wire:poll.10s="syncDocStatuses">
    {{-- Global Loading Overlay --}}
    <div wire:loading wire:target="saveDocument, pickFromGallery, scanWithCamera" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm animate-fadeIn">
        <div class="flex flex-col items-center gap-4">
            <div class="w-16 h-16 border-4 border-blue-500/20 border-t-blue-600 rounded-full animate-spin"></div>
            <p class="text-white font-black uppercase tracking-widest text-xs italic">Processing Document...</p>
        </div>
    </div>
    <div class="w-full max-w-md mx-auto space-y-10">
        <div class="space-y-2">
            <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none text-center">Fleet Docs</h1>
            <p class="text-slate-400 font-medium text-sm text-center">Required for account approval</p>
        </div>

        @if ($uploadStatus)
            <div class="p-5 glass-morphism border border-green-500/30 rounded-2xl flex items-center gap-4 animate-fadeIn">
                <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <p class="text-green-400 text-sm font-bold">{{ $uploadStatus }}</p>
            </div>
        @endif

        <div class="space-y-5" wire:poll.30s="syncDocStatuses">
            @php
                $uploadedDocs = Auth::user()->carrier?->documents()->get()->keyBy('type');
            @endphp
            @foreach(['mc_authority', 'insurance', 'w9'] as $doc)
                @php
                    $docRecord = $uploadedDocs[$doc] ?? null;
                    $status = $docRecord?->status ?? 'missing';
                    $isApproved = $status === 'approved';
                    $isRejected = $status === 'rejected';
                    $borderClass = match($status) {
                        'approved' => 'border-green-500/30',
                        'rejected' => 'border-red-500/40',
                        'pending'  => 'border-yellow-500/20',
                        default    => 'border-white/5',
                    };
                @endphp
                <div class="p-8 glass-morphism {{ $borderClass }} border rounded-[2.5rem] relative overflow-hidden group">
                    <div class="relative z-10">

                        {{-- Header Row --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="space-y-1">
                                <h3 class="text-xl font-black text-white italic uppercase tracking-tight leading-none">{{ str_replace('_', ' ', $doc) }}</h3>
                            </div>
                            {{-- Status Icon --}}
                            @if($isApproved)
                                <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-green-500">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            @elseif($isRejected)
                                <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-red-500">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            @elseif($status === 'pending')
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></div>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-yellow-500">Under Review</span>
                                </div>
                            @endif
                        </div>

                        {{-- Status Banner --}}
                        @if($isApproved)
                            <div class="mb-4 px-4 py-2.5 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-green-500 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span class="text-[10px] font-black text-green-500 uppercase tracking-widest">Verified by Dispatcher</span>
                            </div>
                        @elseif($isRejected)
                            <div class="mb-4 px-4 py-2.5 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-red-500 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <span class="text-[10px] font-black text-red-500 uppercase tracking-widest">Rejected — Please re-upload</span>
                            </div>
                        @endif

                        {{-- Syncing indicator --}}
                        @if(isset($syncing[$doc]))
                            <div class="mb-4 flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-blue-400">Syncing to Cloud...</span>
                            </div>
                        @endif

                        {{-- Action Buttons --}}
                        @if($isApproved || $status === 'pending')
                            {{-- Approved or Pending: Buttons locked --}}
                            <div class="grid grid-cols-2 gap-4 opacity-30 pointer-events-none">
                                <div class="flex items-center justify-center py-4 px-4 glass rounded-2xl text-[10px] font-black uppercase tracking-widest text-white">
                                    Upload
                                </div>
                                <div class="flex items-center justify-center py-4 px-4 bg-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white gap-2">
                                    Scan
                                </div>
                            </div>
                        @else
                            {{-- Missing or Rejected: Buttons active --}}
                            <div class="grid grid-cols-2 gap-4">
                                <button wire:click="pickFromGallery('{{ $doc }}')" 
                                        wire:loading.attr="disabled"
                                        class="flex items-center justify-center py-4 px-4 glass rounded-2xl text-[10px] font-black uppercase tracking-widest text-white hover:bg-white/10 transition-all active:scale-95 group/btn overflow-hidden relative disabled:opacity-50">
                                    <span wire:loading.remove wire:target="pickFromGallery('{{ $doc }}')" class="relative z-10">{{ $isRejected ? 'Re-upload' : 'Upload' }}</span>
                                    <span wire:loading wire:target="pickFromGallery('{{ $doc }}')" class="relative z-10">...</span>
                                    <div class="absolute inset-0 bg-white/5 translate-y-full group-hover/btn:translate-y-0 transition-transform"></div>
                                </button>
                                <button wire:click="scanWithCamera('{{ $doc }}')" 
                                        wire:loading.attr="disabled"
                                        class="flex items-center justify-center py-4 px-4 {{ $isRejected ? 'bg-red-600 hover:bg-red-500 shadow-red-500/30' : 'bg-blue-600 hover:bg-blue-500 shadow-blue-500/30' }} rounded-2xl text-[10px] font-black uppercase tracking-widest text-white transition-all gap-2 shadow-lg active:scale-95 group/btn overflow-hidden relative disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="scanWithCamera('{{ $doc }}')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 relative z-10">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                    </svg>
                                    <span wire:loading.remove wire:target="scanWithCamera('{{ $doc }}')" class="relative z-10">Scan</span>
                                    <span wire:loading wire:target="scanWithCamera('{{ $doc }}')" class="relative z-10 flex items-center gap-1">
                                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce"></div>
                                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce [animation-delay:0.4s]"></div>
                                    </span>
                                </button>
                            </div>
                        @endif


                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-10">
            <a href="/preferences" class="flex justify-center items-center w-full py-5 bg-blue-gradient rounded-[2rem] text-sm font-black italic uppercase tracking-[0.2em] text-white shadow-2xl shadow-blue-500/40 hover:scale-[1.02] active:scale-95 transition-all">
                Submit & Continue
            </a>
        </div>
    </div>
</div>
