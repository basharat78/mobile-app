<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Auth;
use Native\Mobile\Facades\Camera;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;

new #[Layout('components.layouts.app')] class extends Component
{
    public $pendingDocType = null;
    public $uploadStatus = '';

    public function pickFromGallery($type)
    {
        $this->pendingDocType = $type;
        session()->put('pending_doc_type', $type);

        Camera::pickImages('image', false, 1)
            ->id('doc_upload_' . $type)
            ->start();
    }

    public function scanWithCamera($type)
    {
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

        $this->saveDocument($type, $path);
    }

    #[OnNative(MediaSelected::class)]
    public function handleMediaSelected(bool $success, array $files = [], int $count = 0, ?string $error = null, bool $cancelled = false, ?string $id = null)
    {
        if (!$success || $cancelled || empty($files)) return;

        $type = session()->get('pending_doc_type');
        if (!$type) return;

        $filePath = $files[0]['path'] ?? ($files[0] ?? null);
        if (!$filePath) return;

        $this->saveDocument($type, $filePath);
    }

    protected function saveDocument($type, $sourcePath)
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $type . '_' . time() . '.' . $extension;
        $destination = 'documents/' . $filename;

        // Copy file to storage
        $storagePath = storage_path('app/public/' . $destination);
        $dir = dirname($storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($sourcePath)) {
            copy($sourcePath, $storagePath);
        }

        CarrierDocument::create([
            'carrier_id' => Auth::user()->carrier->id,
            'type' => $type,
            'file_path' => $destination,
            'status' => 'pending',
        ]);

        session()->forget('pending_doc_type');
        $this->uploadStatus = ucfirst(str_replace('_', ' ', $type)) . ' uploaded successfully!';
    }
};
?>

<div class="px-6 py-12 space-y-10 relative z-10">
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

        <div class="space-y-5">
            @php
                $uploadedDocs = Auth::user()->carrier?->documents?->pluck('status', 'type')?->toArray() ?? [];
            @endphp
            @foreach(['mc_authority', 'insurance', 'w9'] as $doc)
                @php
                    $status = $uploadedDocs[$doc] ?? 'missing';
                    $statusGradients = [
                        'missing' => 'bg-slate-700/50',
                        'pending' => 'bg-gradient-to-br from-yellow-500 to-orange-600',
                        'approved' => 'bg-gradient-to-br from-green-500 to-emerald-600',
                        'rejected' => 'bg-gradient-to-br from-red-500 to-rose-600',
                    ];
                    $statusText = [
                        'missing' => 'text-slate-500',
                        'pending' => 'text-yellow-500',
                        'approved' => 'text-green-500',
                        'rejected' => 'text-red-500',
                    ];
                @endphp
                <div class="p-8 glass-morphism border {{ $status === 'missing' ? 'border-white/5' : 'border-'.$statusText[$status] ?? 'white/10' }} rounded-[2.5rem] relative overflow-hidden group">
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="space-y-1">
                                <h3 class="text-xl font-black text-white italic uppercase tracking-tight leading-none">{{ str_replace('_', ' ', $doc) }}</h3>
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full {{ $statusGradients[$status] }} {{ $status !== 'missing' ? 'animate-pulse shadow-lg shadow-current' : '' }}"></div>
                                    <span class="text-[9px] font-black uppercase tracking-widest {{ $statusText[$status] }}">{{ $status }}</span>
                                </div>
                            </div>
                            <div class="w-12 h-12 glass rounded-2xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <button wire:click="pickFromGallery('{{ $doc }}')" class="flex items-center justify-center py-4 px-4 glass rounded-2xl text-[10px] font-black uppercase tracking-widest text-white hover:bg-white/10 transition-all active:scale-95 group/btn overflow-hidden relative">
                                <span wire:loading.remove wire:target="pickFromGallery('{{ $doc }}')" class="relative z-10">Upload</span>
                                <span wire:loading wire:target="pickFromGallery('{{ $doc }}')" class="relative z-10">...</span>
                                <div class="absolute inset-0 bg-white/5 translate-y-full group-hover/btn:translate-y-0 transition-transform"></div>
                            </button>
                            <button wire:click="scanWithCamera('{{ $doc }}')" class="flex items-center justify-center py-4 px-4 bg-blue-600 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white hover:bg-blue-500 transition-all gap-2 shadow-lg shadow-blue-500/30 active:scale-95 group/btn overflow-hidden relative">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 relative z-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                </svg>
                                <span wire:loading.remove wire:target="scanWithCamera('{{ $doc }}')" class="relative z-10">Scan</span>
                                <span wire:loading wire:target="scanWithCamera('{{ $doc }}')" class="relative z-10">...</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-blue-600 translate-x-full group-hover/btn:translate-x-0 transition-transform duration-500"></div>
                            </button>
                        </div>
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
