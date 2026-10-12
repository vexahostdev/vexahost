@extends('layouts.app', ['title' => 'Pesanan Berhasil — VexaHost'])

@section('content')
@php
    $isPaid = (bool) ($order->paid_at || in_array($order->status, ['paid', 'provisioning', 'active'], true) || ($order->invoice && $order->invoice->status === 'paid'));
@endphp
<div class="py-16 bg-slate-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg border border-slate-200 p-8 sm:p-10 text-center">

            @if($order->status === 'active' && $order->vpsInstance)
                {{-- Order sudah aktif dan VPS sudah di-provision oleh admin --}}
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-5 border border-emerald-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>

                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 mb-2">VPS Anda Sudah Aktif</h1>
                <p class="text-sm text-slate-600 mb-8 max-w-md mx-auto">
                    Cloud VPS Anda telah berhasil di-setup dan siap digunakan. Kelola server Anda melalui dashboard.
                </p>
            @else
                {{-- Order masih pending / menunggu pembayaran / menunggu provisioning --}}
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-900 flex items-center justify-center mx-auto mb-5 border border-slate-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>

                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 mb-2">Pesanan Anda Sedang Diproses</h1>
                <p class="text-sm text-slate-600 mb-8 max-w-md mx-auto">
                    @if($isPaid)
                        Pembayaran telah dikonfirmasi lunas. Tim teknis kami sedang melakukan setup server Anda. Kredensial server akan dikirimkan ke email Anda.
                    @else
                        Pembayaran Anda telah kami terima dan masuk dalam antrean verifikasi mutasi tim teknis. Tim kami akan segera memverifikasi dan menyiapkan server VPS Anda.
                    @endif
                </p>

                {{-- Progress Steps --}}
                <div class="flex items-center justify-center gap-2 mb-8 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-black text-white flex items-center justify-center text-[10px] font-bold">1</span>
                        <span class="font-semibold text-slate-900">Pesanan Dibuat</span>
                    </div>
                    <div class="w-8 h-px bg-black"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-black text-white flex items-center justify-center text-[10px] font-bold">2</span>
                        <span class="font-semibold text-slate-900">Pembayaran Diterima</span>
                    </div>
                    <div class="w-8 h-px {{ $isPaid ? 'bg-black' : 'bg-slate-300' }}"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full {{ $isPaid ? 'bg-black text-white' : 'bg-slate-200 text-slate-500' }} flex items-center justify-center text-[10px] font-bold">3</span>
                        <span class="font-semibold {{ $isPaid ? 'text-slate-900' : 'text-slate-400' }}">Setup Server</span>
                    </div>
                    <div class="w-8 h-px bg-slate-300"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold">4</span>
                        <span class="font-semibold text-slate-400">Aktif</span>
                    </div>
                </div>
            @endif

            {{-- Order Details Box --}}
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-6 text-left mb-8 space-y-4 text-xs">
                <div class="flex justify-between items-center pb-2">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Nomor Invoice:</span>
                        <span class="font-mono-code font-bold text-slate-900 text-sm">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded font-bold uppercase border text-xs
                        @if($isPaid)
                            border-emerald-300 bg-emerald-50 text-emerald-700
                        @else
                            border-slate-300 bg-slate-100 text-slate-800
                        @endif
                    ">
                        {{ $isPaid ? 'LUNAS' : 'DIPROSES (VERIFIKASI)' }}
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-2 text-xs border-t border-slate-200">
                    <div>
                        <span class="text-slate-500 block">VPS Name:</span>
                        <span class="font-semibold text-slate-900 font-mono-code">{{ $order->hostname ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Paket VPS:</span>
                        <span class="font-semibold text-slate-900">{{ $order->vpsSpec->name ?? 'Standard' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Provider:</span>
                        <span class="font-semibold text-slate-900">{{ $order->provider_label }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Datacenter & OS:</span>
                        <span class="font-semibold text-slate-900 capitalize">{{ $order->datacenter_location }} · {{ $order->os_label }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-2 text-xs border-t border-slate-200">
                    <div>
                        <span class="text-slate-500 block">Stack Server:</span>
                        <span class="font-semibold text-slate-900">{{ $order->control_panel_label }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Spesifikasi:</span>
                        <span class="font-semibold text-slate-900">
                            {{ $order->vpsSpec->cpu ?? '-' }} vCPU · {{ $order->vpsSpec->ram ?? '-' }} GB · {{ $order->vpsSpec->disk ?? '-' }} GB
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Siklus Tagihan:</span>
                        <span class="font-semibold text-slate-900">
                            @if($order->billing_cycle === 'annual')
                                12 Bulan (1 Tahun)
                            @elseif($order->billing_cycle === 'semi_annual')
                                6 Bulan
                            @elseif($order->billing_cycle === 'quarterly')
                                3 Bulan
                            @else
                                1 Bulan
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Total Tagihan:</span>
                        <span class="font-bold text-slate-900 font-mono-code">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- VPS Credentials - hanya tampil jika sudah di-provision oleh admin --}}
                @if($order->vpsInstance && $order->status === 'active')
                    @php $vps = $order->vpsInstance; @endphp
                    <div class="pt-4 bg-white p-5 rounded-lg border border-slate-200 space-y-4" x-data="{
                        showPassword: false,
                        copied: false,
                        copiedSsh: false,
                        sshCmd: '{{ $vps->ssh_command }}',
                        copyText(text, type) {
                            navigator.clipboard.writeText(text);
                            if (type === 'ssh') { this.copiedSsh = true; setTimeout(() => this.copiedSsh = false, 2000); }
                        }
                    }">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <p class="font-bold text-slate-900 text-sm">Informasi Akses Server</p>
                            </div>
                            @php $badge = $vps->statusBadge; @endphp
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded border {{ $badge['bg'] }}">
                                {{ $badge['label'] }}
                            </span>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-slate-500 block text-[11px] mb-0.5">Hostname:</span>
                                <span class="font-mono-code font-semibold text-slate-800">{{ $vps->hostname }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[11px] mb-0.5">IP Publik & Port:</span>
                                <span class="font-mono-code font-bold text-slate-950">{{ $vps->public_ip }}:{{ $vps->ssh_port ?? 22 }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[11px] mb-0.5">Sistem Operasi:</span>
                                <span class="font-medium text-slate-800">{{ $vps->os }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[11px] mb-0.5">Masa Berlaku:</span>
                                <span class="font-medium text-slate-800">
                                    {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                                </span>
                            </div>
                        </div>

                        {{-- SSH Command --}}
                        <div class="p-3 bg-neutral-900 rounded-lg text-white space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-neutral-400">
                                <span>Perintah Terminal SSH:</span>
                                <button type="button" @click="copyText(sshCmd, 'ssh')" class="text-white hover:text-neutral-300 font-medium flex items-center gap-1 cursor-pointer">
                                    <span x-text="copiedSsh ? 'Tersalin!' : 'Salin Perintah'"></span>
                                </button>
                            </div>
                            <div class="font-mono-code text-xs text-emerald-400 select-all">
                                {{ $vps->ssh_command }}
                            </div>
                        </div>

                        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-800">
                            <span class="font-semibold">Catatan:</span> Username &amp; password SSH dapat dilihat di menu detail VPS pada Dashboard (tab Akses, klik ikon mata).
                        </div>
                    </div>
                @elseif($isPaid)
                    {{-- Sudah bayar tapi belum di-provision --}}
                    <div class="pt-4 bg-cyan-50 p-5 rounded-lg border border-cyan-200 text-center">
                        <svg class="w-8 h-8 text-cyan-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-sm font-semibold text-cyan-800">Server Sedang Di-Setup</p>
                        <p class="text-xs text-cyan-700 mt-1">Pembayaran telah dikonfirmasi lunas. Tim teknis kami sedang mempersiapkan VPS Anda. Kredensial akses server akan tersedia di Dashboard setelah proses setup selesai (maksimal 1×24 jam sejak pembayaran, umumnya lebih cepat). Anda juga akan menerima email &amp; WhatsApp saat server siap.</p>
                    </div>
                @else
                    {{-- Belum bayar --}}
                    <div class="pt-4 bg-amber-50 p-5 rounded-lg border border-amber-200 text-center">
                        <svg class="w-8 h-8 text-amber-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm font-semibold text-amber-800">Menunggu Pembayaran</p>
                        <p class="text-xs text-amber-700 mt-1">Silakan lakukan pembayaran sesuai metode yang dipilih. Setelah pembayaran terkonfirmasi, VPS Anda akan segera di-setup oleh tim kami.</p>
                        @if($order->invoice && $order->invoice->due_at)
                            <p class="text-xs text-amber-600 mt-2 font-medium">Batas waktu pembayaran: {{ $order->invoice->due_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('dashboard.index', $isPaid ? ['payment_success' => 1, 'order_id' => $order->id] : []) }}" class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white text-sm font-bold transition-colors">
                    Buka Dashboard
                </a>
                @if($order->invoice)
                    <a href="{{ route('dashboard.invoice.print', $order->invoice->id) }}"
                       @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $order->invoice->id) }}' })"
                       class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-900 text-sm font-semibold transition-colors cursor-pointer">
                        Cetak Invoice
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
