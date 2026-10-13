<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }} — VexaHost Enterprise</title>
    <style>
        @page {
            margin: 18mm 18mm 18mm 18mm;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #111111;
            font-size: 10.5px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* Tables base */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Top Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-logo {
            height: 38px;
            width: auto;
            display: block;
            margin-bottom: 6px;
        }
        .brand-name {
            font-size: 16px;
            font-weight: 900;
            color: #000000;
            letter-spacing: -0.3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 8.5px;
            color: #444444;
            letter-spacing: 0.2px;
            line-height: 1.3;
        }

        .invoice-headline {
            text-align: right;
        }
        .invoice-headline h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 0.5px;
        }
        .invoice-headline .inv-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            margin-top: 2px;
        }

        /* Stamp Badge */
        .stamp-badge {
            display: inline-block;
            margin-top: 6px;
            border: 2px solid #000000;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #000000;
            background: #ffffff;
        }

        /* Parties Section */
        .parties-table {
            margin-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
        }
        .parties-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .section-title {
            font-size: 8.5px;
            font-weight: 800;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #000000;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        .party-name {
            font-size: 11.5px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 2px;
        }
        .party-desc {
            font-size: 9.5px;
            color: #333333;
            line-height: 1.4;
        }

        /* Meta / Metadata Bar */
        .meta-strip {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            margin-bottom: 16px;
        }
        .meta-strip td {
            width: 25%;
            padding: 7px 10px;
            vertical-align: top;
            border-right: 1px solid #e5e7eb;
        }
        .meta-strip td:last-child {
            border-right: none;
        }
        .meta-label {
            font-size: 8px;
            font-weight: 800;
            color: #555555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-val {
            font-size: 10px;
            font-weight: bold;
            color: #000000;
        }

        /* Items Table */
        .items-table {
            margin-bottom: 14px;
            border: 1px solid #000000;
        }
        .items-table th {
            background-color: #000000;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 7px 10px;
            text-align: left;
            border-right: 1px solid #333333;
        }
        .items-table th:last-child {
            border-right: none;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 9.5px;
        }
        .items-table td:last-child {
            border-right: none;
        }
        .items-table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .item-main-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #000000;
        }
        .item-spec-list {
            margin-top: 4px;
            font-size: 8.5px;
            color: #333333;
            line-height: 1.35;
        }
        .item-spec-list span {
            display: inline-block;
            margin-right: 6px;
        }

        /* Financial Totals */
        .totals-table {
            width: 46%;
            margin-left: 54%;
            margin-bottom: 16px;
        }
        .totals-table td {
            padding: 3px 6px;
            font-size: 9.5px;
        }
        .totals-label {
            text-align: right;
            color: #444444;
            width: 55%;
        }
        .totals-val {
            text-align: right;
            font-weight: bold;
            color: #000000;
            width: 45%;
        }
        .grand-total {
            border-top: 2px solid #000000;
            border-bottom: 1px solid #000000;
        }
        .grand-total td {
            padding: 6px 6px !important;
        }
        .grand-total .totals-label {
            font-size: 11px;
            font-weight: 900;
            color: #000000;
            text-transform: uppercase;
        }
        .grand-total .totals-val {
            font-size: 13px;
            font-weight: 900;
            color: #000000;
            font-family: 'Courier New', Courier, monospace;
        }

        /* Verification Box */
        .settlement-box {
            background-color: #ffffff;
            border: 1px solid #000000;
            padding: 8px 12px;
            margin-bottom: 16px;
        }
        .settlement-title {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 3px;
            color: #000000;
        }
        .settlement-desc {
            font-size: 9px;
            color: #222222;
            line-height: 1.4;
        }

        /* Footer */
        .footer {
            border-top: 1px solid #000000;
            padding-top: 8px;
            margin-top: 12px;
            font-size: 7.5px;
            color: #555555;
            line-height: 1.4;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo.png');
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
        
        $isPaid = $invoice->status === 'paid'
            || $invoice->paid_at !== null
            || ($invoice->order && ($invoice->order->paid_at || in_array($invoice->order->status, ['paid', 'provisioning', 'active'], true)));

        $cycle = match($invoice->order->billing_cycle ?? 'monthly') {
            'monthly' => '1 Bulan (Bulanan)',
            'quarterly' => '3 Bulan (Kuartalan)',
            'semi_annual' => '6 Bulan (Semester)',
            'annual' => '12 Bulan (Tahunan)',
            default => '1 Bulan',
        };
    @endphp

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="brand-logo" alt="VexaHost Logo">
                @else
                    <div class="brand-name">VexaHost</div>
                @endif
                <div class="brand-name">VexaHost Cloud Infrastructure</div>
                <div class="brand-sub">
                    Enterprise Cloud Hosting &amp; Infrastructure Services<br>
                    Datacenter: Jakarta &amp; Singapore &bull; Portal: https://vexahostcloud.my.id &bull; Email: {{ \App\Support\MailIdentity::contactEmail() }}
                </div>
            </td>
            <td class="invoice-headline" style="width: 45%;">
                <h1>FAKTUR / INVOICE</h1>
                <div class="inv-number">{{ $invoice->invoice_number }}</div>
                <div>
                    @if($isPaid)
                        <div class="stamp-badge">[ LUNAS / PAID ]</div>
                    @else
                        <div class="stamp-badge">[ MENUNGGU PEMBAYARAN ]</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Parties (Issuer & Customer) -->
    <table class="parties-table">
        <tr>
            <td>
                {{-- Identitas penerbit dibaca dari pengaturan sistem (Phase 1),
                     sehingga dapat diubah admin tanpa deploy ulang. --}}
                @php $vxSettings = app(\App\Services\SettingsService::class); @endphp
                <div class="section-title">Diterbitkan Oleh (Issuer):</div>
                <div class="party-name">{{ $vxSettings->get('company_legal_name', 'PT DESTINARA CHAKRAWALA ARTHA') }}</div>
                <div class="party-desc">
                    Divisi Penagihan &amp; Infrastruktur Komputasi Cloud<br>
                    Layanan Cloud VPS &amp; Dedicated Infrastructure<br>
                    @if($vxSettings->get('company_address'))
                        {{ $vxSettings->get('company_address') }}{{ $vxSettings->get('company_city') ? ', ' . $vxSettings->get('company_city') : '' }}{{ $vxSettings->get('company_postal_code') ? ' ' . $vxSettings->get('company_postal_code') : '' }}<br>
                    @endif
                    @if($vxSettings->get('company_npwp'))
                        NPWP: {{ $vxSettings->get('company_npwp') }}<br>
                    @endif
                    @if($vxSettings->get('company_phone'))
                        Telepon: {{ $vxSettings->get('company_phone') }}<br>
                    @endif
                    Email: {{ $vxSettings->get('company_email') ?: \App\Support\MailIdentity::contactEmail() }}<br>
                    Website Resmi: {{ $vxSettings->get('company_website', 'https://vexahostcloud.my.id') }}
                </div>
            </td>
            <td>
                <div class="section-title">Ditagihkan Kepada (Client / Billed To):</div>
                <div class="party-name">{{ $invoice->order->customer->full_name ?? 'Pelanggan Terdaftar' }}</div>
                <div class="party-desc">
                    Perusahaan / Organisasi: {{ $invoice->order->customer->company ?? 'Personal / Enterprise Customer' }}<br>
                    Email Akun: {{ $invoice->order->customer->email ?? '-' }}<br>
                    Username: &#64;{{ $invoice->order->customer->username ?? '-' }}<br>
                    @if($invoice->order->customer->phone ?? false)
                        Kontak / WhatsApp: {{ $invoice->order->customer->phone }}<br>
                    @endif
                    Alamat: {{ $invoice->order->customer->address ?? 'Indonesia' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Meta Strip -->
    <table class="meta-strip">
        <tr>
            <td>
                <div class="meta-label">Nomor Order:</div>
                <div class="meta-val">#ORD-{{ str_pad($invoice->order->id, 6, '0', STR_PAD_LEFT) }}</div>
            </td>
            <td>
                <div class="meta-label">Tanggal Terbit:</div>
                <div class="meta-val">{{ $invoice->issued_at ? $invoice->issued_at->timezone('Asia/Jakarta')->format('d F Y') : $invoice->created_at->timezone('Asia/Jakarta')->format('d F Y') }}</div>
            </td>
            <td>
                <div class="meta-label">Jatuh Tempo:</div>
                <div class="meta-val">{{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->format('d F Y') : '-' }}</div>
            </td>
            <td>
                <div class="meta-label">Metode Pembayaran:</div>
                <div class="meta-val">{{ strtoupper(str_replace('_', ' ', $invoice->order->payment_method ?? 'QRIS / Gateway')) }}</div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 55%;">Deskripsi Layanan & Spesifikasi Server</th>
                <th style="width: 15%; text-align: center;">Durasi</th>
                <th style="width: 12%; text-align: right;">Qty</th>
                <th style="width: 13%; text-align: right;">Jumlah (IDR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">1</td>
                <td>
                    <div class="item-main-title">
                        Cloud VPS Infrastructure — {{ $invoice->order->vpsSpec->name ?? 'Standard Package' }}
                    </div>
                    <div class="item-spec-list">
                        <span>&bull; Hostname: <strong>{{ $invoice->order->hostname ?? 'vps-' . $invoice->order->id }}</strong></span>
                        <span>&bull; Provider: {{ $invoice->order->provider_label ?? 'Enterprise Cloud' }}</span><br>
                        <span>&bull; Komputasi: {{ $invoice->order->vpsSpec->cpu ?? 2 }} vCPU Core</span>
                        <span>&bull; Memory: {{ $invoice->order->vpsSpec->ram ?? 4 }} GB RAM</span>
                        <span>&bull; Storage: {{ $invoice->order->vpsSpec->disk ?? 60 }} GB NVMe</span><br>
                        <span>&bull; Datacenter: {{ ucfirst($invoice->order->datacenter_location ?? 'Indonesia') }}</span>
                        <span>&bull; OS: {{ $invoice->order->os_label ?? 'Ubuntu 24.04 LTS' }}</span>
                        <span>&bull; Stack Panel: {{ $invoice->order->control_panel_label ?? 'Dokploy' }}</span>
                    </div>
                </td>
                <td style="text-align: center;">{{ $cycle }}</td>
                <td style="text-align: right;">1x</td>
                <td style="text-align: right; font-weight: bold;">
                    Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold; color: #555555;">2</td>
                <td>
                    <div class="item-main-title" style="font-weight: normal; color: #333333;">
                        Inisialisasi Cloud Hypervisor & Otomasi Setup Stack
                    </div>
                    <div class="item-spec-list" style="color: #666666;">
                        Konfigurasi firewall enterprise, alokasi IP publik, dan penyediaan stack control panel
                    </div>
                </td>
                <td style="text-align: center; color: #666666;">1x</td>
                <td style="text-align: right; color: #666666;">1x</td>
                <td style="text-align: right; font-weight: bold; color: #333333;">
                    Rp 0
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Totals Table -->
    <table class="totals-table">
        <tr>
            <td class="totals-label">Subtotal Layanan:</td>
            <td class="totals-val">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="totals-label">Pajak Pertambahan Nilai (PPN 0%):</td>
            <td class="totals-val">Rp 0</td>
        </tr>
        <tr>
            <td class="totals-label">Biaya Transaksi / Gateway:</td>
            <td class="totals-val">Rp 0</td>
        </tr>
        <tr class="grand-total">
            <td class="totals-label">TOTAL TAGIHAN:</td>
            <td class="totals-val">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <!-- Settlement & Certification Box -->
    <div class="settlement-box">
        <div class="settlement-title">Konfirmasi & Status Pembayaran Elektronik</div>
        <div class="settlement-desc">
            @if($isPaid)
                Pembayaran faktur ini telah <strong>BERHASIL DITERIMA & DIVERIFIKASI</strong> secara otomatis oleh sistem penagihan VexaHost.<br>
                Waktu Pelunasan: <strong>{{ $invoice->order->paid_at ? $invoice->order->paid_at->timezone('Asia/Jakarta')->format('d F Y, H:i:s') . ' WIB' : '-' }}</strong> &bull;
                Ref Transaksi: <strong>{{ $invoice->order->payment_reference ?? 'SETTLED-' . $invoice->id . '-' . $invoice->order->id }}</strong> &bull;
                Status Layanan: <strong>AKTIF / DALAM PROSES PROVISIONING</strong>
            @else
                Faktur ini berstatus <strong>MENUNGGU PEMBAYARAN</strong>. Silakan selesaikan pembayaran sebelum tanggal jatuh tempo.<br>
                Batas Waktu Pelunasan: <strong>{{ $invoice->due_at ? $invoice->due_at->timezone('Asia/Jakarta')->format('d F Y, H:i') . ' WIB' : '-' }}</strong>
            @endif
        </div>
    </div>

    <!-- Enterprise Legal Footer -->
    <div class="footer">
        Faktur ini merupakan dokumen penagihan dan bukti transaksi elektronik yang sah yang diterbitkan oleh sistem penagihan resmi VexaHost Cloud Indonesia.<br>
        Berdasarkan Undang-Undang Republik Indonesia No. 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik (UU ITE) serta PP No. 71 Tahun 2019,<br>
        dokumen elektronik ini sah secara hukum dan mengikat tanpa memerlukan tanda tangan fisik atau stempel basah.<br>
        Dukungan & Bantuan Layanan: {{ \App\Support\MailIdentity::contactEmail() }} &bull; https://vexahostcloud.my.id &bull; Dokumen ID: {{ strtoupper(hash('crc32b', $invoice->invoice_number . ($invoice->issued_at ?? $invoice->created_at))) }}
    </div>
</body>
</html>
