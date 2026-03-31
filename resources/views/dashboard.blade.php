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
        $isApproved = $carrier->status === 'approved';

        $initials = collect(explode(' ', $user->name))->map(fn($n) => substr($n, 0, 1))->join('');
        
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

    <div class="px-6 py-8 space-y-8">
        @if(!$isApproved)
            <!-- Onboarding / Pending Approval State -->
            <div class="space-y-8 animate-fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Onboarding</h1>
                        <p class="text-slate-500 font-medium">Complete these steps to start hauling</p>
                    </div>
                    <div class="px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-2xl">
                        <span class="text-[10px] font-black text-yellow-500 uppercase tracking-widest">Action Required</span>
                    </div>
                </div>

                <div class="grid gap-4">
                    <!-- Step 1: Documents -->
                    <div class="p-6 bg-slate-800/40 border {{ !$hasDocs ? 'border-blue-500/50' : 'border-white/5' }} rounded-[2rem] relative overflow-hidden group">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 {{ $hasDocs ? 'bg-green-500/20 text-green-500' : 'bg-blue-600/20 text-blue-500' }} rounded-2xl flex items-center justify-center">
                                    @if($hasDocs)
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white leading-tight">Fleet Documents</h3>
                                    <p class="text-slate-500 text-xs font-medium">MC Authority, Insurance, W9</p>
                                </div>
                            </div>
                            @if($hasDocs)
                                <span class="text-[10px] font-black text-green-500 uppercase tracking-widest">Complete</span>
                            @else
                                <a href="/document-upload" class="px-5 py-2.5 bg-blue-600 rounded-xl text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                                    Upload Now
                                </a>
                            @endif
                        </div>
                        @if(!$hasDocs)
                            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-600/5 rounded-full blur-2xl"></div>
                        @endif
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black text-white italic tracking-tighter uppercase">Truck Zap</h1>
                    <p class="text-slate-500 font-medium">Welcome back, {{ explode(' ', $user->name)[0] }}!</p>
                </div>
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-blue-500/40 text-white font-black italic">
                    {{ $initials }}
                </div>
            </div>

            <!-- Status Card -->
            <div class="p-8 bg-slate-800/40 border border-white/5 rounded-[2.5rem] relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Account Status</p>
                    <h3 class="text-5xl font-black text-white italic tracking-tighter mb-4">{{ $carrierStatus }}</h3>
                    <a href="/my-requests" class="inline-flex px-6 py-3 bg-slate-900/50 border border-white/10 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-300 hover:text-white transition-colors">
                        {{ $activeLoadsCount ?? 0 }} Loads Approved
                    </a>
                </div>
                <!-- Decorative circle -->
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-600/5 rounded-full blur-3xl group-hover:bg-blue-600/10 transition-colors"></div>
            </div>

            <!-- Stats Grid -->
            <div class="space-y-4">
                <div class="p-5 bg-blue-600 rounded-3xl flex items-center justify-between shadow-xl shadow-blue-500/20 active:scale-[0.98] transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V3.375c0-.621-.504 1.125 1.125 1.125h-1.5a3.375 3.375 0 0 1-3.375 3.375H9.75m10.5 11.25V3.375m-10.5 4.5a3.375 3.375 0 0 1-3.375-3.375h-1.5a1.125 1.125 0 0 0-1.125 1.125v12.75c0 .621.504 1.125 1.125 1.125H16.5M9.75 8.25h4.875c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H9.75V8.25Z" />
                            </svg>
                        </div>
                        <span class="text-white font-bold">Active Loads</span>
                    </div>
                    <span class="text-2xl font-black text-white italic">{{ $activeLoadsCount }}</span>
                </div>

                <div class="p-5 bg-orange-500 rounded-3xl flex items-center justify-between shadow-xl shadow-orange-500/20 active:scale-[0.98] transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <span class="text-white font-bold">Pending Requests</span>
                    </div>
                    <span class="text-2xl font-black text-white italic">{{ $pendingRequestsCount }}</span>
                </div>

                <a href="/loads" class="p-5 bg-blue-500 rounded-3xl flex items-center justify-between shadow-xl shadow-blue-500/20 active:scale-[0.98] transition-all block">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <span class="text-white font-bold">Request Loads</span>
                    </div>
                    <span class="text-2xl font-black text-white italic">{{ $requestLoadsCount }}</span>
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
