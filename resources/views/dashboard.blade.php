<x-layouts.app>
    @php
        $user = Auth::user();
        $carrier = $user->carrier;
        
        if (!$carrier) {
            // This should only happen for dispatchers or weird edge cases
            header('Location: /dispatcher/dashboard');
            exit;
        }

        $docsCount = $carrier->documents->count();
        $hasDocs = $carrier->hasMinimumDocuments();
        $hasPreferences = $carrier->hasPreferences();
        
        // Final Sync Logic (v31 - Identity & Status Polling)
        try {
            // Self-Healing: If remote_id is missing, find it by email
            if (!$carrier->remote_id) {
                $lookupUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/lookup';
                $lookupResponse = \Illuminate\Support\Facades\Http::timeout(10)->post($lookupUrl, [
                    'email' => $user->email
                ]);
                
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

            // Real-time Status Sync: If not yet approved, poll the central server
            // Updated (v33): Now uses Email for 100% reliable matching
            if ($carrier->status !== 'approved' && $carrier->remote_id) {
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
            \Illuminate\Support\Facades\Log::warning('Dashboard live sync failed', ['error' => $e->getMessage()]);
        }

        $isApproved = $carrier->status === 'approved';

        $initials = collect(explode(' ', $user->name ?? ''))
            ->filter()
            ->map(fn($n) => substr($n, 0, 1))
            ->take(2)
            ->join('') ?: 'U';
        
        // Stats for approved users
        $activeLoadsCount = $isApproved ? $carrier->loadRequests()->where('status', 'approved')->count() : 0;
        $pendingRequestsCount = $isApproved ? $carrier->loadRequests()->where('status', 'pending')->count() : 0;
        $requestLoadsCount = \App\Models\Load::where('status', 'available')->count();
        
        $carrierStatus = ucfirst($carrier->status);
        
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
                    'description' => "For {$request->loadJob->pickup_location}",
                    'type' => $request->status,
                    'time' => $request->updated_at,
                ]);
            }
            $recentUpdates = $recentUpdates->sortByDesc('time')->take(5);
        }
    @endphp

    <div class="px-6 py-12 space-y-10 relative z-10">
        @if(!$isApproved)
            <!-- Onboarding / Pending Approval State -->
            <div class="space-y-10 animate-fade-in">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow">Onboarding</h1>
                        <p class="text-slate-400 font-medium text-sm">Complete these steps to start hauling</p>
                    </div>
                    <div class="px-3 py-1.5 bg-yellow-500/10 border border-yellow-500/20 rounded-xl">
                        <span class="text-[9px] font-black text-yellow-500 uppercase tracking-widest">Action Required</span>
                    </div>
                </div>

                <div class="grid gap-5">
                    <!-- Step 1: Documents -->
                    <div class="p-8 glass-morphism border {{ !$hasDocs ? 'border-blue-500/40 glow-blue' : 'border-white/5' }} rounded-[2.5rem] relative overflow-hidden group transition-all duration-500 hover:scale-[1.02]">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-5">
                                <div class="w-14 h-14 {{ $hasDocs ? 'bg-green-500/20 text-green-500' : 'bg-blue-gradient text-white shadow-lg shadow-blue-500/40' }} rounded-[1.25rem] flex items-center justify-center transition-all duration-500 group-hover:rotate-6">
                                    @if($hasDocs)
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-7 h-7">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="space-y-0.5">
                                    <h3 class="text-xl font-black text-white italic tracking-tight leading-none uppercase">Fleet Docs</h3>
                                    <p class="text-slate-400 text-xs font-semibold tracking-wide">Authority, Insurance, W9</p>
                                </div>
                            </div>
                            @if($hasDocs)
                                <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4 text-green-500">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            @else
                                <a href="/document-upload" class="px-6 py-3 bg-blue-600 rounded-2xl text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/40 active:scale-90 relative overflow-hidden group/btn">
                                    <span class="relative z-10">Upload</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-blue-600 translate-x-full group-hover/btn:translate-x-0 transition-transform duration-500"></div>
                                </a>
                            @endif
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-600/5 rounded-full blur-3xl group-hover:bg-blue-600/10 transition-colors"></div>
                    </div>

                    <!-- Step 2: Preferences -->
                    <div class="p-6 bg-slate-800/40 border {{ $hasDocs && !$hasPreferences ? 'border-orange-500/50' : 'border-white/5' }} rounded-[2rem] relative overflow-hidden group">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 {{ $hasPreferences ? 'bg-green-500/20 text-green-500' : ($hasDocs ? 'bg-orange-500/20 text-orange-500' : 'bg-slate-700/50 text-slate-500') }} rounded-2xl flex items-center justify-center">
                                    @if($hasPreferences)
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white leading-tight">Hauling Preferences</h3>
                                    <p class="text-slate-500 text-xs font-medium">Equipment type and routes</p>
                                </div>
                            </div>
                            @if($hasPreferences)
                                <span class="text-[10px] font-black text-green-500 uppercase tracking-widest">Complete</span>
                            @elseif($hasDocs)
                                <a href="/preferences" class="px-5 py-2.5 bg-orange-600 rounded-xl text-white text-[10px] font-black uppercase tracking-widest hover:bg-orange-500 transition-all shadow-lg shadow-orange-500/20 active:scale-95">
                                    Set Prefs
                                </a>
                            @else
                                <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Locked</span>
                            @endif
                        </div>
                    </div>

                    <!-- Step 3: Verification -->
                    <div class="p-6 bg-slate-800/40 border {{ $hasDocs && $hasPreferences ? 'border-yellow-500/50' : 'border-white/5' }} rounded-[2rem] relative overflow-hidden group">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 {{ $hasDocs && $hasPreferences ? 'bg-yellow-500/20 text-yellow-500' : 'bg-slate-700/50 text-slate-500' }} rounded-2xl flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white leading-tight">Admin Review</h3>
                                    <p class="text-slate-500 text-xs font-medium">Finalizing your account</p>
                                </div>
                            </div>
                            @if($hasDocs && $hasPreferences)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                    <span class="text-[10px] font-black text-yellow-500 uppercase tracking-widest">In Progress</span>
                                </div>
                            @else
                                <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Locked</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-blue-600/10 border border-blue-500/20 rounded-[2.5rem] relative overflow-hidden">
                    <p class="text-blue-400 text-[10px] font-black uppercase tracking-[0.2em] mb-3">Support Note</p>
                    <p class="text-white text-sm font-medium leading-relaxed relative z-10">Once you complete the first two steps, our dispatchers will review your profile within 4-6 hours. You'll receive a notification once approved.</p>
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl"></div>
                </div>
            </div>
        @else
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase text-glow leading-none">Truck Zap</h1>
                    <p class="text-slate-400 font-medium">Welcome back, <span class="text-blue-400">{{ explode(' ', $user->name)[0] }}</span>!</p>
                </div>
                <div class="relative group">
                    <div class="w-14 h-14 bg-blue-gradient rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-blue-500/40 text-white font-black italic transition-transform group-hover:scale-110 group-hover:rotate-3 duration-500">
                        {{ $initials }}
                    </div>
                    <div class="absolute -inset-2 bg-blue-500/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </div>
            </div>

            <!-- Status Card -->
            <div class="p-10 bg-royal-gradient rounded-[3rem] relative overflow-hidden group shadow-[0_20px_50px_rgba(30,58,138,0.4)]">
                <div class="relative z-10 flex flex-col items-start">
                    <div class="px-3 py-1 bg-white/10 backdrop-blur-md rounded-lg mb-4">
                        <p class="text-white/70 text-[9px] font-black uppercase tracking-widest leading-none">Account Status</p>
                    </div>
                    <h3 class="text-6xl font-black text-white italic tracking-tighter mb-6 leading-none">{{ $carrierStatus }}</h3>
                    <a href="/my-requests" class="inline-flex items-center gap-2 px-6 py-3.5 bg-white/10 hover:bg-white/20 backdrop-blur-xl border border-white/20 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white transition-all shadow-xl active:scale-95">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                        {{ $activeLoadsCount ?? 0 }} Loads Approved
                    </a>
                </div>
                <!-- Decorative Elements -->
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000"></div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-white/10 to-transparent"></div>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-5">
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
                    <span class="text-3xl font-black text-white italic text-glow">{{ $activeLoadsCount }}</span>
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
                    <span class="text-3xl font-black text-white italic text-glow text-orange-400">{{ $pendingRequestsCount }}</span>
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
                    <span class="text-3xl font-black text-white italic relative z-10">{{ $requestLoadsCount }}</span>
                    <!-- Shine Effect -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                </a>
            </div>

            <!-- Recent Activity -->
            <div class="space-y-6">
                <div class="flex items-center justify-between px-2">
                    <h3 class="text-xl font-black text-white italic">Recent Updates</h3>
                    <a href="/my-requests" class="text-blue-500 text-[10px] font-black uppercase tracking-widest">View All</a>
                </div>
                <!-- ... existing items loop ... -->
            </div>
        @endif
    </div>
</x-layouts.app>
