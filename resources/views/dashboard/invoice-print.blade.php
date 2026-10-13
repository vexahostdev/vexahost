<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }} — VexaHost Enterprise</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; color: #111111; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .print-card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="light-isolated bg-neutral-100 text-neutral-900 p-4 sm:p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Floating Print Bar (No-print) -->
        <div class="no-print mb-6 flex items-center justify-between bg-black text-white p-4 rounded-lg shadow-md">
            <div class="text-xs">
                <span class="font-bold">Faktur / Dokumen Resmi:</span> {{ $invoice->invoice_number }}
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard.billing') }}" class="px-3 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs inline-flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Kembali</span>
                </a>
                <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-white hover:bg-neutral-200 text-black text-xs font-bold transition-colors flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Faktur</span>
                </button>
                <button onclick="window.close()" class="px-3 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>

        <!-- Invoice Sheet -->
        <div class="print-card bg-white rounded-lg border border-neutral-300 p-8 sm:p-12 relative overflow-hidden shadow-sm">
            
            <!-- Enterprise Monochrome Header -->
            <div class="flex justify-between items-start pb-6 border-b-2 border-black">
                <div class="flex items-start space-x-4">
                    <img src="{{ asset('images/logo.png') }}" alt="VexaHost Logo" class="h-11 w-auto object-contain">
                    <div>
                        <span class="text-xl font-black uppercase tracking-tight text-black block">VexaHost Cloud Infrastructure</span>
                        <p class="text-[11px] text-neutral-600 font-medium">Enterprise Cloud Hosting &amp; Infrastructure Services</p>
                        <p class="text-[10px] text-neutral-500">Jakarta Data Center Hub &bull; {{ \App\Support\MailIdentity::contactEmail() }} &bull; https://vexahostcloud.my.id</p>
                    </div>
                </div>
                <div class="text-right">
                    @php
                        $isPaid = $invoice->status === 'paid' 
                            || $invoice->paid_at !== null 
                            || ($invoice->order && ($invoice->order->paid_at || in_array($invoice->order->status, ['paid', 'provisioning', 'active'], true)));
                    @endphp
                    <h1 class="text-2xl font-black text-black uppercase tracking-wide">FAKTUR / INVOICE</h1>
                    <p class="text-xs font-bold text-black font-mono-code mt-0.5">{{ $invoice->invoice_number }}</p>
                    <div class="mt-2">
                        @if($isPaid)
                            <span class="inline-block border-2 border-black px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-black bg-white">
                                [ LUNAS / PAID ]
                            </span>
                        @else
                            <span class="inline-block border-2 border-black px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-black bg-white">
                                [ MENUNGGU PEMBAYARAN ]
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Parties Details Grid -->
            <div class="grid grid-cols-2 gap-8 py-6 border-b border-neutral-200 text-xs">
                <div>
                    {{-- Identitas penerbit dibaca dari pengaturan sistem (Phase 1),
                         sehingga dapat diubah admin tanpa deploy ulang. --}}
                    @php $vxSettings = app(\App\Services\SettingsService::class); @endphp
                    <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider block border-b border-black pb-1 mb-2">Diterbitkan Oleh (Issuer):</span>
                    <p class="font-bold text-black text-sm">{{ $vxSettings->get('company_legal_name', 'PT DESTINARA CHAKRAWALA ARTHA') }}</p>
                    <p class="text-neutral-700">Divisi Penagihan & Komputasi Cloud</p>
                    <p class="text-neutral-700">Layanan Cloud VPS &amp; Dedicated Infrastructure</p>
                    @if($vxSettings->get('company_address'))
                        <p class="text-neutral-600">{{ $vxSettings->get('company_address') }}{{ $vxSettings->get('company_city') ? ', ' . $vxSettings->get('company_city') : '' }}</p>
                    @endif
                    @if($vxSettings->get('company_npwp'))
                        <p class="text-neutral-600">NPWP: {{ $vxSettings->get('company_npwp') }}</p>
                    @endif
                    <p class="text-neutral-600">Email: {{ $vxSettings->get('company_email') ?: \App\Support\MailIdentity::contactEmail() }}</p>
                    <p class="text-neutral-600">Website: {{ $vxSettings->get('company_website', 'https://vexahostcloud.my.id') }}</p>
                </div>

                <div>
                    <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider block border-b border-black pb-1 mb-2">Ditagihkan Kepada (Client / Billed To):</span>
                    <p class="font-bold text-black text-sm">{{ $invoice->order->customer->full_name ?? 'Pelanggan Terdaftar' }}</p>
                    <p class="text-neutral-700">Perusahaan: {{ $invoice->order->customer->company ?? 'Personal / Enterprise Customer' }}</p>
                    <p class="text-neutral-700">Email Akun: {{ $invoice->order->customer->email ?? '-' }}</p>
                    <p class="text-neutral-600 font-mono-code text-[11px]">Username: &#64;{{ $invoice->order->customer->username ?? '-' }}</p>
                    @if($invoice->order->customer->phone ?? false)
                        <p class="text-neutral-600">WhatsApp/Telp: {{ $invoice->order->customer->phone }}</p>
                    @endif
                    <p class="text-neutral-500 text-[11px]">Alamat: {{ $invoice->order->customer->address ?? 'Indonesia' }}</p>
                </div>
            </div>

            <!-- Dates & Payment Method Strip -->
            <div class="grid grid-cols-4 gap-4 py-3 border-b border-neutral-200 text-xs bg-neutral-50 px-4 -mx-4 my-2">
                <div>
                    <span class="text-neutral-500 block text-[10px] uppercase font-bold">Nomor Order</span>
                    <span class="font-bold text-black font-mono-code">#ORD-{{ str_pad($invoice->order->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div>
                    <span class="text-neutral-500 block text-[10px] uppercase font-bold">Tanggal Terbit</span>
                    <span class="font-bold text-black">{{ $invoice->issued_at ? $invoice->issued_at->timezone('Asia/Jakarta')->format('d F Y') : $invoice->created_at->timezone('Asia/Jakarta')->format('d F Y') }}</span>
                </div>
                <div>
                    <span class="text-neutral-500 block text-[10px] uppercase font-bold">Jatuh Tempo</span>
                    <span class="font-bold text-black">{{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->format('d F Y') : '-' }}</span>
                </div>
                <div>
                    <span class="text-neutral-500 block text-[10px] uppercase font-bold">Metode Bayar</span>
                    <span class="font-bold text-black uppercase">{{ strtoupper(str_replace('_', ' ', $invoice->order->payment_method ?? 'QRIS / Gateway')) }}</span>
                </div>
            </div>

            <!-- Items Table -->
            <div class="py-6">
                <table class="w-full text-left text-xs border border-black">
                    <thead class="bg-black text-white uppercase text-[10px] font-bold tracking-wider">
                        <tr>
                            <th class="p-2.5 text-center w-12 border-r border-neutral-700">No.</th>
                            <th class="p-2.5 border-r border-neutral-700">Deskripsi Layanan & Spesifikasi</th>
                            <th class="p-2.5 text-center border-r border-neutral-700">Durasi</th>
                            <th class="p-2.5 text-right border-r border-neutral-700">Qty</th>
                            <th class="p-2.5 text-right">Jumlah (IDR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        <tr>
                            <td class="p-3 text-center font-bold border-r border-neutral-200">1</td>
                            <td class="p-3 border-r border-neutral-200">
                                <p class="font-bold text-black text-sm">{{ $invoice->order->vpsSpec->name ?? 'Cloud VPS Infrastructure' }}</p>
                                <div class="text-neutral-600 text-[11px] mt-1 space-y-0.5">
                                    <p>&bull; Hostname: <span class="font-mono-code font-semibold text-black">{{ $invoice->order->hostname ?? 'vps-' . $invoice->order->id }}</span> &bull; Provider: {{ $invoice->order->provider_label ?? 'Enterprise Cloud' }}</p>
                                    <p>&bull; Komputasi: {{ $invoice->order->vpsSpec->cpu ?? 2 }} vCPU &bull; Memory: {{ $invoice->order->vpsSpec->ram ?? 4 }} GB RAM &bull; Disk: {{ $invoice->order->vpsSpec->disk ?? 60 }} GB NVMe</p>
                                    <p>&bull; Datacenter: {{ ucfirst($invoice->order->datacenter_location ?? 'Indonesia') }} &bull; OS: {{ $invoice->order->os_label }} &bull; Panel: {{ $invoice->order->control_panel_label }}</p>
                                </div>
                            </td>
                            <td class="p-3 text-center text-neutral-700 border-r border-neutral-200">
                                @php
                                    $cycleLabel = match($invoice->order->billing_cycle ?? 'monthly') {
                                        'monthly' => '1 Bulan',
                                        'quarterly' => '3 Bulan',
                                        'semi_annual' => '6 Bulan',
                                        'annual' => '12 Bulan',
                                        default => '1 Bulan',
                                    };
                                @endphp
                                {{ $cycleLabel }}
                            </td>
                            <td class="p-3 text-right text-neutral-700 border-r border-neutral-200">1x</td>
                            <td class="p-3 text-right font-bold text-black font-mono-code text-sm">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="bg-neutral-50/70">
                            <td class="p-2.5 text-center font-bold text-neutral-400 border-r border-neutral-200">2</td>
                            <td class="p-2.5 border-r border-neutral-200 text-neutral-600">
                                <span class="font-semibold text-neutral-800">Inisialisasi Cloud Hypervisor & Otomasi Setup Stack</span>
                                <p class="text-[10px] text-neutral-500">Konfigurasi firewall enterprise, alokasi IP publik, dan lisensi stack panel</p>
                            </td>
                            <td class="p-2.5 text-center text-neutral-500 border-r border-neutral-200">1x</td>
                            <td class="p-2.5 text-right text-neutral-500 border-r border-neutral-200">1x</td>
                            <td class="p-2.5 text-right font-semibold text-neutral-600 font-mono-code">Rp 0</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals Table -->
            <div class="pt-2 border-t border-neutral-200 flex justify-end text-xs">
                <div class="w-72 space-y-1.5">
                    <div class="flex justify-between text-neutral-700">
                        <span>Subtotal Layanan:</span>
                        <span class="font-mono-code font-bold text-black">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-neutral-700">
                        <span>Pajak Pertambahan Nilai (PPN 0%):</span>
                        <span class="font-mono-code font-semibold text-neutral-500">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-neutral-700">
                        <span>Biaya Transaksi / Gateway:</span>
                        <span class="font-mono-code font-semibold text-neutral-500">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-sm font-black text-black pt-2 border-t-2 border-black">
                        <span class="uppercase">TOTAL TAGIHAN:</span>
                        <span class="font-mono-code text-base text-black">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- Settlement Confirmation Box -->
            <div class="mt-8 p-4 border border-black bg-neutral-50 text-xs">
                <span class="font-bold text-black uppercase tracking-wider block mb-1">Status & Konfirmasi Pembayaran Elektronik:</span>
                @if($isPaid)
                    <p class="text-neutral-800">Pembayaran faktur ini telah <strong>BERHASIL DITERIMA & DIVERIFIKASI</strong> secara otomatis oleh sistem penagihan VexaHost.</p>
                    <p class="text-neutral-600 text-[11px] mt-0.5">Waktu Pelunasan: <strong>{{ $invoice->order->paid_at ? $invoice->order->paid_at->timezone('Asia/Jakarta')->format('d F Y, H:i:s') . ' WIB' : '-' }}</strong> &bull; Ref: <strong class="font-mono-code">{{ $invoice->order->payment_reference ?? 'SETTLED-' . $invoice->id }}</strong></p>
                @else
                    <p class="text-neutral-800">Faktur ini berstatus <strong>MENUNGGU PEMBAYARAN</strong>. Silakan selesaikan pembayaran sebelum tanggal jatuh tempo.</p>
                @endif
            </div>

            <!-- Enterprise Footer -->
            <div class="mt-8 pt-4 border-t border-black text-center text-[10px] text-neutral-500 leading-relaxed">
                <p class="font-semibold text-neutral-700">Faktur ini merupakan dokumen elektronik resmi yang diterbitkan otomatis oleh VexaHost Cloud Indonesia.</p>
                <p>Sah dan mengikat secara hukum berdasarkan ketentuan UU ITE No. 11/2008 & PP No. 71/2019 tanpa memerlukan tanda tangan basah.</p>
                <p class="mt-1 font-mono-code text-[9px] text-neutral-400">Dokumen ID: {{ strtoupper(hash('crc32b', $invoice->invoice_number . ($invoice->issued_at ?? $invoice->created_at))) }} &bull; https://vexahostcloud.my.id &bull; {{ \App\Support\MailIdentity::contactEmail() }}</p>
            </div>
        </div>
    </div>
</body>
</html>
