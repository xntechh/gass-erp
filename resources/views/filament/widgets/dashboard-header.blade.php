<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-2xl bg-slate-950 p-8 shadow-2xl border border-slate-800">
        {{-- Background Pattern (Opsional: Halus banget) --}}
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#475569 1px, transparent 1px); background-size: 20px 20px;"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-8">
            {{-- Bagian Kiri: Identitas --}}
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-slate-400">
                    <span class="h-px w-8 bg-slate-700"></span>
                    <p class="text-[10px] font-black uppercase tracking-[0.3em]">{{ $this->getData()['salam'] }}</p>
                </div>
                <h1 class="text-4xl font-black text-white tracking-tight">
                    {{ $this->getData()['nama'] }}
                </h1>
                <p class="text-slate-400 text-sm font-medium">
                    Acting Supervisor GA <span class="mx-2 text-slate-700">|</span>
                    <span class="text-slate-300">Sentul Plant Distribution Center</span>
                </p>
            </div>

            {{-- Bagian Kanan: Data Stats Terintegrasi --}}
            <div class="flex flex-wrap items-center gap-12 border-t lg:border-t-0 lg:border-l border-slate-800 pt-6 lg:pt-0 lg:pl-12">
                {{-- Stat 1: Low Stock --}}
                <div class="space-y-1">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Critical Stock</p>
                    <div class="flex items-center gap-3">
                        <span class="text-3xl font-black text-orange-500">{{ $this->getData()['lowStockCount'] }}</span>
                        <div class="h-8 w-px bg-slate-800"></div>
                        <span class="text-xs text-slate-400 leading-tight">Items<br>Required</span>
                    </div>
                </div>

                {{-- Stat 2: System Health --}}
                <div class="space-y-1">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Stock Health</p>
                    <div class="flex items-center gap-3">
                        <span class="text-3xl font-black text-blue-500">{{ $this->getData()['accuracy'] }}%</span>
                        <div class="h-8 w-px bg-slate-800"></div>
                        <span class="text-xs text-slate-400 leading-tight">Accuracy<br>Rate</span>
                    </div>
                </div>

                {{-- Stat 3: Live Pulse --}}
                <div class="hidden sm:block">
                    <div class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1 border border-slate-800">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        <span class="text-[10px] font-bold text-slate-300 uppercase tracking-tighter">System Live</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>