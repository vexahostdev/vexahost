@extends('layouts.dashboard', ['title' => 'Dashboard', 'headerTitle' => 'VPS Instances Saya'])

@section('content')
<div class="space-y-6" x-data="{
    copyIp(ip) {
        if (!ip) return;
        navigator.clipboard.writeText(ip);
        showAlert('IP ' + ip + ' disalin ke clipboard', { icon: 'success', title: 'Berhasil disalin' });
    }
}">
    <!-- Stat Cards -->
    <div class="grid grid-cols-1 {{ $pendingOrdersCount > 0 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3' }} gap-4">
        <div class="bg-white rounded-lg border border-slate-200 p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">VPS Instances</p>
                <p class="text-2xl font-bold text-slate-900 mt-1 font-mono-code">{{ $vps->count() }} <span class="text-xs font-normal text-slate-500 font-sans">Unit</span></p>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ $vps->where('status', 'running')->count() }} Aktif &bull; {{ $vps->whereIn('status', ['suspended', 'stopped', 'error'])->count() }} Perlu Perhatian
                    @if(isset($provisioningOrders) && $provisioningOrders->count() > 0)
                        &bull; <span class="text-amber-600 font-semibold">{{ $provisioningOrders->count() }} Setup</span>
                    @endif
                </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
        </div>

        @if($pendingOrdersCount > 0)
        <div class="bg-amber-50 rounded-lg border border-amber-200 p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-700">Pesanan Diproses</p>
                <p class="text-2xl font-bold text-amber-900 mt-1 font-mono-code">{{ $pendingOrdersCount }} <span class="text-xs font-normal text-amber-600 font-sans">Order</span></p>
                <p class="text-xs text-amber-600 mt-0.5">Sedang dalam setup server oleh VexaHost</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                <svg class="w-5 h-5 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-lg border border-slate-200 p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Faktur & Tagihan</p>
                <p class="text-2xl font-bold text-slate-900 mt-1 font-mono-code">{{ $invoicesCount }} <span class="text-xs font-normal text-slate-500 font-sans">Faktur</span></p>
                <a href="{{ route('dashboard.billing') }}" class="text-xs text-slate-900 underline mt-0.5 inline-block font-medium">
                    Lihat faktur &rarr;
                </a>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Tiket Bantuan</p>
                <p class="text-2xl font-bold text-slate-900 mt-1 font-mono-code">{{ $openTicketsCount }} <span class="text-xs font-normal text-slate-500 font-sans">Aktif</span></p>
                <a href="{{ route('dashboard.support') }}" class="text-xs text-slate-900 underline mt-0.5 inline-block font-medium">
                    Buka tiket &rarr;
                </a>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- Unpaid Orders Section -->
    @if($unpaidOrders->count() > 0)
    <div class="bg-slate-50 dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 rounded-lg p-6 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-slate-200/80 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white">Layanan Terkunci - Menunggu Pembayaran</h3>
                <p class="text-sm text-slate-600 dark:text-zinc-400">Anda memiliki {{ $unpaidOrders->count() }} pesanan yang belum dibayar</p>
            </div>
        </div>
        
        <div class="space-y-4">
            @foreach($unpaidOrders as $order)
            <div class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-lg p-4">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-semibold text-slate-900 dark:text-white">{{ $order->vpsSpec->name }}</h4>
                            @if($order->hostname)
                                <span class="px-2 py-0.5 rounded text-[11px] font-mono-code font-bold bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-200 border border-slate-200 dark:border-zinc-700">
                                    {{ $order->hostname }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-zinc-400 mt-1">
                            <span>{{ $order->vpsSpec->cpu }} vCPU &middot; {{ $order->vpsSpec->ram }} GB RAM &middot; {{ $order->vpsSpec->disk }} GB SSD</span>
                            <span class="px-2 py-0.5 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 rounded text-xs font-semibold">
                                TERKUNCI
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-500 dark:text-zinc-400">Total Tagihan</div>
                        <div class="font-bold text-lg font-mono-code text-slate-900 dark:text-white">Rp {{ number_format($order->amount, 0, ',', '.') }}</div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs mb-4">
                    <div>
                        <span class="text-slate-500 dark:text-zinc-400">Provider & DC:</span>
                        <span class="font-medium text-slate-900 dark:text-zinc-100 block capitalize">{{ $order->provider_label }} · {{ $order->datacenter_location }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-zinc-400">OS & Stack:</span>
                        <span class="font-medium text-slate-900 dark:text-zinc-100 block">{{ $order->os_label }} · {{ $order->control_panel_label }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-zinc-400">Metode Bayar:</span>
                        <span class="font-medium text-slate-900 dark:text-zinc-100 block">{{ strtoupper($order->payment_method) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-zinc-400">Invoice:</span>
                        <span class="font-mono-code font-medium text-slate-900 dark:text-zinc-100 block">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="{{ \App\Models\PaymentGateway::isMidtransMethod($order->payment_method) ? route('order.payment.status', $order->id) : route('order.payment', $order->id) }}" 
                       class="flex-1 py-2.5 px-4 rounded-lg bg-black hover:bg-neutral-800 dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-black font-semibold text-sm text-center transition-colors">
                        Selesaikan Pembayaran
                    </a>
                    <a href="{{ route('order.success', $order->id) }}" 
                       class="py-2.5 px-4 rounded-lg border border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-medium text-sm transition-colors">
                        Lihat Detail
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        
        <div class="mt-4 p-4 bg-slate-100/80 dark:bg-zinc-900/80 border border-slate-200 dark:border-zinc-800 rounded-lg">
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                <span class="font-semibold text-slate-900 dark:text-zinc-200">Catatan:</span> Layanan VPS akan terkunci sampai pembayaran Anda dikonfirmasi. 
                Setelah pembayaran berhasil, tim teknis akan melakukan setup server dalam 1-2 jam kerja.
            </p>
        </div>
    </div>
    @endif

    <!-- VPS & AI Agent List Section -->
    <div id="provisioning-orders-section" class="space-y-4 pt-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Daftar Server, AI Agent &amp; Database</h2>
                <p class="text-xs text-slate-500">Kelola Cloud VPS, runtime AI Agent, dan Dedicated Database Anda</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('home') }}#pricing" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah VPS</span>
                </a>
                <a href="{{ route('home') }}#ai-packages" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>AI Agent</span>
                </a>
                <a href="{{ route('home') }}#database-packages" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    <span>Database</span>
                </a>
            </div>
        </div>

        @if($vps->count() === 0 && (!isset($provisioningOrders) || $provisioningOrders->count() === 0))
            <div class="bg-white rounded-lg border border-slate-200 p-10 text-center max-w-lg mx-auto">
                <div class="w-12 h-12 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">Belum Ada Layanan Aktif</h3>
                <p class="text-xs text-slate-500 leading-relaxed mb-5">
                    Server cloud, AI Agent, atau Dedicated Database Anda belum terdaftar. Silakan pilih paket layanan untuk memulai:
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('home') }}#pricing" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        <span>+ Tambah VPS</span>
                    </a>
                    <a href="{{ route('home') }}#ai-packages" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold transition-colors">
                        <span>+ Tambah AI Agent</span>
                    </a>
                    <a href="{{ route('home') }}#database-packages" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold transition-colors">
                        <span>+ Tambah Database</span>
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {{-- Cards untuk Pesanan yang Sedang Dalam Setup Server oleh Pihak VexaHost --}}
                @if(isset($provisioningOrders))
                    @foreach($provisioningOrders as $order)
                        <div id="order-card-{{ $order->id }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:border-slate-300 transition-colors flex flex-col justify-between">
                            <div>
                                <!-- Card Header -->
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-bold text-slate-900 text-base flex items-center gap-1.5">
                                                <span>{{ $order->hostname ?? 'vx-server-' . $order->id }}</span>
                                            </h3>
                                            @if($order->isDatabasePackage())
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    Managed DB
                                                </span>
                                            @elseif($order->isAiPackage())
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                                                    AI Agent
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                                    {{ $order->vpsSpec->name ?? 'Cloud VPS' }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 mt-0.5">Setup Server Sedang Berjalan</p>
                                    </div>

                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Dalam Setup
                                    </span>
                                </div>

                                <!-- Status Info Box -->
                                <div class="mb-3 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 space-y-1">
                                    <div class="flex items-center justify-between font-medium">
                                        <span class="text-slate-900 font-semibold flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Proses Setup Server
                                        </span>
                                        <span class="text-[11px] text-slate-500 font-mono-code">Order #{{ $order->id }}</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        Pesanan telah terverifikasi. Server sedang dalam proses alokasi dan instalasi oleh tim VexaHost. Maksimal 1×24 jam sejak pembayaran (umumnya lebih cepat).
                                    </p>
                                </div>

                                <!-- Hardware Specs -->
                                <div class="bg-slate-50 rounded p-3 my-2 text-xs space-y-1 border border-slate-100">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Compute:</span>
                                        <span class="font-semibold text-slate-900">
                                            {{ $order->vpsSpec->cpu ?? 1 }} vCPU &bull; {{ $order->vpsSpec->ram ?? 1 }} GB RAM &bull; {{ $order->vpsSpec->disk ?? 20 }} GB SSD
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Provider &amp; DC:</span>
                                        <span class="font-semibold text-slate-900 capitalize">{{ $order->provider_label }} · {{ $order->datacenter_location }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">OS &amp; Stack:</span>
                                        <span class="font-semibold text-slate-900">{{ $order->os_label }} · {{ $order->control_panel_label }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Pembayaran:</span>
                                        <span class="font-semibold text-emerald-600 font-mono-code">LUNAS &bull; Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2 mt-2">
                                <a href="{{ route('order.success', $order->id) }}"
                                   class="flex-1 py-2 px-3 text-center rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                                    Lihat Detail &amp; Faktur
                                </a>
                                <a href="{{ route('dashboard.support') }}" title="Hubungi Tim Teknis"
                                   class="py-2 px-3 text-center rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-medium transition-colors">
                                    Bantuan
                                </a>
                            </div>
                        </div>
                    @endforeach
                @endif
                @foreach($vps as $instance)
                    <div class="bg-white rounded-lg border {{ $instance->isDatabasePackage() ? 'border-emerald-300 ring-1 ring-emerald-300/40 shadow-xs' : ($instance->isAiPackage() ? 'border-blue-300 ring-1 ring-blue-300/40 shadow-xs' : 'border-slate-200') }} p-5 hover:border-slate-400 transition-colors flex flex-col justify-between">
                        <div>
                            <!-- Card Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-slate-900 text-base flex items-center gap-1.5">
                                            <span>{{ $instance->hostname ?? 'vps-' . $instance->id }}</span>
                                        </h3>
                                        @if($instance->isDatabasePackage())
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                Managed DB
                                            </span>
                                        @elseif($instance->isAiPackage())
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                                                AI Agent
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="text-xs font-mono-code font-semibold text-slate-900">
                                            {{ $instance->public_ip ?? 'Mengalokasikan IP...' }}
                                        </span>
                                        @if($instance->public_ip)
                                            <button @click="copyIp('{{ $instance->public_ip }}')" title="Salin IP" class="text-slate-400 hover:text-black">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @php $instanceStatus = $instance->customer_status; @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase border {{ $instanceStatus['class'] }}" title="Status layanan">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $instanceStatus['dot'] }}"></span>
                                    {{ $instanceStatus['label'] }}
                                </span>
                            </div>

                            <!-- Subscription & Expiry Tag -->
                            <div class="mb-3">
                                @if($instance->status === 'suspended')
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        Layanan Ditangguhkan (Suspended)
                                    </span>
                                @elseif($instance->isInGracePeriod())
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        Masa Tenggang s/d {{ $instance->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M') }}
                                    </span>
                                @elseif($instance->isExpired())
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200">
                                        Expired {{ $instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                                    </span>
                                @elseif($instance->expires_at)
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        Tempo: {{ $instance->expires_at->timezone('Asia/Jakarta')->format('d M Y') }} ({{ $instance->days_until_expiry }} hr)
                                    </span>
                                @endif
                            </div>

                            <!-- Hardware Specs -->
                            <div class="bg-slate-50 rounded p-3 my-2 text-xs space-y-1 border border-slate-100">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Compute:</span>
                                    <span class="font-semibold text-slate-900">{{ $instance->cpu ?? 1 }} vCPU &bull; {{ $instance->ram ?? 1 }} GB RAM</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Storage:</span>
                                    <span class="font-semibold text-slate-900">{{ $instance->disk ?? 20 }} GB NVMe SSD</span>
                                </div>
                            </div>

                            <!-- Meta info -->
                            <div class="space-y-1.5 text-xs text-slate-600 mb-3 pt-1">
                                <div class="flex justify-between">
                                    <span>OS:</span>
                                    <span class="font-medium text-slate-900">{{ $instance->os ?? 'Ubuntu 24.04 LTS' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Control Panel / Stack:</span>
                                    <span class="font-semibold text-slate-900 uppercase">
                                        {{ $instance->control_panel_label }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Siklus:</span>
                                    <span class="font-semibold text-slate-900 uppercase">
                                        {{ str_replace('_', ' ', $instance->billing_cycle ?? 'monthly') }}
                                    </span>
                                </div>
                            </div>

                            @if($instance->isAiPackage())
                                <!-- Dedicated Web App Link Box for AI Agent Instance -->
                                <div class="my-3 p-3 rounded-lg bg-blue-50/80 border border-blue-200 text-xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-blue-900 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Link Web App:</span>
                                        </span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-white text-[#4A6FA5] border border-blue-200">Web App</span>
                                    </div>
                                    @if($instance->app_url && $instance->status === 'running')
                                        <div class="flex items-center justify-between gap-1.5 p-2 rounded-lg bg-white border border-blue-100 font-mono-code text-[11px]">
                                            <a href="{{ $instance->app_url }}" target="_blank" rel="noopener" class="text-[#4A6FA5] font-bold hover:underline truncate">
                                                {{ $instance->app_url }}
                                            </a>
                                            <button @click="copyIp('{{ $instance->app_url }}')" title="Salin Link App" class="text-slate-400 hover:text-black shrink-0">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    @else
                                        <p class="text-[11px] text-slate-500 italic">
                                            {{ $instance->status === 'running' ? 'Tautan web sedang disiapkan tim.' : 'Layanan sedang tidak aktif. Buka detail untuk informasi lebih lanjut.' }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                            @if($instance->isAiPackage() && $instance->app_url && $instance->status === 'running')
                                <a href="{{ $instance->app_url }}" target="_blank" rel="noopener"
                                   class="flex-1 py-2 px-3 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white font-bold text-xs text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs">
                                    <span>Buka Web App</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                   class="py-2 px-3 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-800 font-semibold text-xs text-center transition-colors">
                                    Detail
                                </a>
                            @else
                                <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                   class="flex-1 py-2 px-3 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold text-xs text-center transition-colors">
                                    Kelola Layanan
                                </a>
                                @if($instance->canBeRenewed())
                                    <a href="{{ route('dashboard.vps.show', $instance->id) }}"
                                       title="Perpanjang Layanan"
                                       class="py-2 px-3 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-800 font-semibold text-xs text-center transition-colors flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span>Perpanjang</span>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@if(isset($paymentSuccessOrder) && $paymentSuccessOrder)
@push('scripts')
<script>
(function() {
    function initPaymentSuccessModal() {
        if (!window.Swal) {
            setTimeout(initPaymentSuccessModal, 100);
            return;
        }

        // Bersihkan query string di address bar browser agar tidak memicu alert berulang saat F5/reload
        if (window.history && window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('payment_success') || currentUrl.searchParams.has('order_id')) {
                currentUrl.searchParams.delete('payment_success');
                currentUrl.searchParams.delete('order_id');
                window.history.replaceState({}, document.title, currentUrl.pathname + (currentUrl.search ? currentUrl.search : ''));
            }
        }

        window.Swal.fire({
            title: '<div class="text-xl sm:text-2xl font-bold text-slate-900 mt-2">Pembayaran Berhasil Dikonfirmasi!</div>',
            html: `
                <div class="text-left mt-3 space-y-3.5 text-xs text-slate-600">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between">
                        <div>
                            <span class="text-[11px] text-emerald-800 font-semibold block">Status Pembayaran:</span>
                            <span class="text-sm font-bold text-emerald-800 flex items-center gap-1">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                LUNAS
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-emerald-800 block font-mono-code">{{ $paymentSuccessOrder->invoice?->invoice_number ?? ('INV-' . $paymentSuccessOrder->id) }}</span>
                            <span class="text-sm font-bold text-slate-900 font-mono-code">Rp {{ number_format($paymentSuccessOrder->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3.5 space-y-2">
                        <div class="flex justify-between border-b border-slate-200/80 pb-1.5">
                            <span class="text-slate-500">Layanan:</span>
                            <span class="font-bold text-slate-900">{{ $paymentSuccessOrder->vpsSpec?->name ?? 'Cloud VPS' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/80 pb-1.5">
                            <span class="text-slate-500">Server Hostname:</span>
                            <span class="font-mono-code font-bold text-slate-900">{{ $paymentSuccessOrder->hostname ?? ('vx-server-' . $paymentSuccessOrder->id) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/80 pb-1.5">
                            <span class="text-slate-500">OS & Stack:</span>
                            <span class="font-semibold text-slate-800">{{ $paymentSuccessOrder->os_label }} · {{ $paymentSuccessOrder->control_panel_label }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Datacenter:</span>
                            <span class="font-semibold text-slate-800 capitalize">{{ $paymentSuccessOrder->provider_label }} · {{ $paymentSuccessOrder->datacenter_location }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50/90 border border-amber-200 rounded-lg text-amber-900 text-[11px] leading-relaxed">
                        <div class="flex items-center gap-1.5 font-bold mb-1 text-amber-950">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Server Sedang Dalam Antrean Setup</span>
                        </div>
                        Pesanan Anda telah masuk ke tim teknis VexaHost untuk alokasi resource dan konfigurasi (maksimal 1×24 jam sejak pembayaran, umumnya lebih cepat). Kredensial server (IP Publik, SSH, Root Password) akan otomatis dikirimkan ke email Anda begitu setup selesai.
                    </div>
                </div>
            `,
            icon: 'success',
            iconColor: '#10b981',
            confirmButtonText: 'Pantau Status Server',
            confirmButtonColor: '#000000',
            showCancelButton: true,
            cancelButtonText: 'Tutup',
            cancelButtonColor: '#64748b',
            customClass: {
                popup: 'rounded-lg border border-slate-200 shadow-2xl p-6 sm:p-7 max-w-lg',
                confirmButton: 'rounded-lg px-5 py-2.5 font-bold text-sm bg-black text-white hover:bg-neutral-800',
                cancelButton: 'rounded-lg px-4 py-2.5 font-semibold text-sm',
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const targetCard = document.getElementById('order-card-{{ $paymentSuccessOrder->id }}') || document.getElementById('provisioning-orders-section');
                if (targetCard) {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetCard.classList.add('ring-2', 'ring-emerald-500', 'ring-offset-2', 'transition-all', 'duration-500');
                    setTimeout(() => {
                        targetCard.classList.remove('ring-2', 'ring-emerald-500', 'ring-offset-2');
                    }, 3000);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentSuccessModal);
    } else {
        initPaymentSuccessModal();
    }
})();
</script>
@endpush
@endif

