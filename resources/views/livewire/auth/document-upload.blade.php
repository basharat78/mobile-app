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

<div class="flex flex-col items-center min-h-screen px-6 py-12 bg-slate-900">
    <div class="w-full max-w-md space-y-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-white mb-2">Upload Documents</h1>
            <p class="text-slate-400">Required for account approval</p>
        </div>

        @if ($uploadStatus)
            <div class="p-4 mb-4 text-sm text-green-400 bg-green-400/10 border border-green-400/20 rounded-xl">
                {{ $uploadStatus }}
            </div>
        @endif

        <div class="space-y-4">
            @php
                $uploadedDocs = Auth::user()->carrier?->documents?->pluck('status', 'type')?->toArray() ?? [];
            @endphp
            @foreach(['mc_authority', 'insurance', 'w9'] as $doc)
                <div class="p-6 bg-slate-800 border border-slate-700 rounded-3xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-white capitalize">{{ str_replace('_', ' ', $doc) }}</h3>
                        @php
                            $status = $uploadedDocs[$doc] ?? 'missing';
                            $statusClasses = [
                                'missing' => 'text-slate-500 bg-slate-500/10',
                                'pending' => 'text-yellow-500 bg-yellow-500/10',
                                'approved' => 'text-green-500 bg-green-500/10',
                                'rejected' => 'text-red-500 bg-red-500/10',
                            ];
                        @endphp
                        <span class="px-3 py-1 text-xs font-bold rounded-full {{ $statusClasses[$status] }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <button wire:click="pickFromGallery('{{ $doc }}')" class="flex items-center justify-center py-3 px-4 text-xs font-black uppercase tracking-widest text-white bg-slate-700 rounded-xl hover:bg-slate-600 transition-all active:scale-95">
                            <span wire:loading.remove wire:target="pickFromGallery('{{ $doc }}')">Upload</span>
                            <span wire:loading wire:target="pickFromGallery('{{ $doc }}')">...</span>
                        </button>
                        <button wire:click="scanWithCamera('{{ $doc }}')" class="flex items-center justify-center py-3 px-4 text-xs font-black uppercase tracking-widest text-white bg-blue-600 rounded-xl hover:bg-blue-500 transition-all gap-2 shadow-lg shadow-blue-500/20 active:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                            </svg>
                            <span wire:loading.remove wire:target="scanWithCamera('{{ $doc }}')">Scan</span>
                            <span wire:loading wire:target="scanWithCamera('{{ $doc }}')">...</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-6">
            <a href="/preferences" class="flex justify-center w-full py-4 text-base font-bold text-white bg-slate-700 rounded-xl hover:bg-slate-600 transition-all">
                Submit & Continue
            </a>
        </div>
    </div>
</div>
