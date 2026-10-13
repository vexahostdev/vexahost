@extends('layouts.app', [
    'title' => 'Kebijakan Pengembalian Dana (Refund Policy) — VexaHost',
    'description' => 'Kebijakan pengembalian dana VexaHost: syarat refund, jangka waktu pengajuan, layanan yang memenuhi syarat, dan cara mengajukannya.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Kebijakan Refund'],
    ],
])

@section('content')
<div x-data="{
    activeSection: 'pasal-1',
    scrollTo(id) {
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
            this.activeSection = id;
        }
    }
}" class="min-h-screen bg-slate-50 text-slate-900 selection:bg-slate-900 selection:text-white">

    <!-- Top Sub-Header Bar (Legal Documentation Hub) -->
    <div class="bg-white border-b border-slate-200 sticky top-16 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-x-auto no-scrollbar py-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Portal Legal</span>
                <span class="text-slate-300">/</span>
                <nav class="flex items-center space-x-1 sm:space-x-2 text-xs">
                    <a href="{{ route('terms') }}" class="px-2.5 py-1 rounded-md font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors whitespace-nowrap">
                        Terms of Service
                    </a>
                    <a href="{{ route('privacy') }}" class="px-2.5 py-1 rounded-md font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors whitespace-nowrap">
                        Privacy Policy
                    </a>
                    <a href="{{ route('refund') }}" class="px-2.5 py-1 rounded-md font-bold bg-slate-900 text-white whitespace-nowrap">
                        Refund Policy
                    </a>
                </nav>
            </div>

            <div class="hidden sm:flex items-center space-x-3 shrink-0">
                <button onclick="window.print()" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 text-xs font-semibold transition-colors shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Dokumen</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Header Hero Banner -->
    <header class="bg-white border-b border-slate-200 py-10 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                        Dokumen Kebijakan Transaksi
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Garansi Teknis 48 Jam
                    </span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-bold tracking-tight text-slate-900">
                    Kebijakan Pengembalian Dana (Refund Policy)
                </h1>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Ketentuan transparan pembatalan transaksi, syarat garansi kendala teknis awal, batasan non-refundable, serta prosedur pencairan dana bagi pelanggan VexaHost.
                </p>

                <!-- Metadata Grid -->
                <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block font-medium">Jendela Garansi</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">48 Jam Sejak Aktivasi</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Estimasi Waktu Proses</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">1 &ndash; 3 Hari Kerja Bank</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Metode Pengembalian</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">Bank Lokal &amp; E-Wallet</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Identifikasi Dokumen</span>
                        <span class="font-mono-code font-semibold text-slate-800 mt-0.5 block">REF-2026-V2</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Layout (Sidebar + Content Body) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- Left Sticky Sidebar Table of Contents -->
            <aside class="w-full lg:w-72 shrink-0">
                <div class="sticky top-36 space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 px-2">
                            Daftar Klausul Refund
                        </h2>
                        <nav class="space-y-1 text-xs">
                            <button @click="scrollTo('pasal-1')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                1. Prinsip Kepuasan Layanan
                            </button>
                            <button @click="scrollTo('pasal-2')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                2. Garansi Teknis 48 Jam
                            </button>
                            <button @click="scrollTo('pasal-3')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                3. Kondisi Non-Refundable
                            </button>
                            <button @click="scrollTo('pasal-4')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                4. Pesanan Marketplace Shopee
                            </button>
                            <button @click="scrollTo('pasal-5')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                5. Persyaratan Pengajuan Klaim
                            </button>
                            <button @click="scrollTo('pasal-6')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                6. Saluran &amp; Durasi Pencairan
                            </button>
                            <button @click="scrollTo('pasal-7')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                7. Biaya Administrasi Gateway
                            </button>
                            <button @click="scrollTo('pasal-8')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                8. Pencegahan Penyalahgunaan
                            </button>
                        </nav>
                    </div>

                    <!-- Billing Helpdesk Card -->
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-xs space-y-2.5 shadow-2xs">
                        <div class="font-bold text-slate-900 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>Pusat Bantuan Billing</span>
                        </div>
                        <p class="text-slate-500 leading-relaxed">
                            Konsultasikan kendala tagihan dan faktur Anda langsung ke tim finance:
                        </p>
                        <div class="space-y-1 font-mono-code text-[11px]">
                            <p class="text-slate-800 bg-slate-50 p-1.5 rounded border border-slate-100">{{ \App\Support\MailIdentity::contactEmail() }}</p>
                            <a href="{{ \App\Support\NomorWhatsApp::tautan() }}" target="_blank" class="text-[#25D366] block font-semibold hover:underline">
                                WhatsApp Finance {{ \App\Support\NomorWhatsApp::lokal() }}
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Legal Article Body -->
            <main class="flex-1 min-w-0 space-y-12">

                <!-- Pasal 1 -->
                <section id="pasal-1" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 1
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Prinsip &amp; Komitmen Kepuasan Layanan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            VexaHost Cloud Indonesia berkomitmen menghadirkan solusi Cloud VPS, Dedicated Database, dan runtime AI Agent dengan performa komputasi terbaik dan ketersediaan tinggi.
                        </p>
                        <p>
                            Kami memahami bahwa dalam penerapan infrastruktur teknis, potensi kendala hardware host atau inkompatibilitas arsitektur dapat terjadi. Dokumen ini menjelaskan hak serta mekanisme pengembalian dana (refund) secara berkeadilan, transparan, dan terukur.
                        </p>
                    </div>
                </section>

                <!-- Pasal 2 -->
                <section id="pasal-2" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 2
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Garansi Teknis 48 Jam Pertama (First Provisioning Guarantee)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Bagi setiap pemesanan unit server baru, Pelanggan berhak mengajukan permohonan pengembalian dana penuh (100% dari biaya sewa paket) dalam jangka waktu <strong>48 (empat puluh delapan) jam</strong> terhitung sejak kredensial server pertama kali diterbitkan, dengan kriteria teknis berikut:
                        </p>
                        <div class="overflow-x-auto my-3">
                            <table class="w-full text-left border-collapse border border-slate-200 text-xs">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-900">
                                        <th class="p-3 border border-slate-200 font-bold w-1/3">Kondisi Kendala Teknis</th>
                                        <th class="p-3 border border-slate-200 font-bold">Ambang Batas Verifikasi</th>
                                        <th class="p-3 border border-slate-200 font-bold">Status Hak Refund</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Kerusakan Hardware / Host Node</td>
                                        <td class="p-3 border border-slate-200">Host hypervisor KVM mengalami kerusakan fisik yang gagal dipulihkan dalam 24 jam.</td>
                                        <td class="p-3 border border-slate-200 font-semibold text-emerald-700">Eligible (Disetujui Penuh)</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Ketidaksesuaian Spesifikasi</td>
                                        <td class="p-3 border border-slate-200">Alokasi inti vCPU, RAM, atau kapasitas NVMe tidak sesuai dengan paket yang dipesan.</td>
                                        <td class="p-3 border border-slate-200 font-semibold text-emerald-700">Eligible (Disetujui Penuh)</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Kegagalan Routing Jaringan Publik</td>
                                        <td class="p-3 border border-slate-200">IP publik yang diberikan terblokir permanen pada upstream gateway datacenter sejak awal penerbitan.</td>
                                        <td class="p-3 border border-slate-200 font-semibold text-emerald-700">Eligible (Ganti IP / Refund)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Pasal 3 -->
                <section id="pasal-3" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 3
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Kondisi Pengecualian (Non-Refundable Circumstances)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Pengembalian dana secara tegas <strong>TIDAK BERLAKU</strong> dalam situasi-situasi berikut:
                        </p>
                        <ul class="space-y-2 pl-4 border-l-2 border-slate-200 text-xs">
                            <li>
                                <strong>Pelanggaran Acceptable Use Policy (AUP):</strong> Server yang disuspend atau diterminasi karena terdeteksi menjalankan aktivitas penambangan kripto (crypto mining), serangan DDoS, pengiriman spam, hosting botnet, atau materi ilegal.
                            </li>
                            <li>
                                <strong>Faktur Perpanjangan (Renewal):</strong> Pembayaran perpanjangan masa sewa untuk bulan kedua dan seterusnya tidak memenuhi syarat pengembalian dana, karena server telah dioperasikan pada periode sebelumnya.
                            </li>
                            <li>
                                <strong>Kesalahan Konfigurasi Internal Pengguna:</strong> Pengguna salah memilih varian Sistem Operasi (misal: memilih Ubuntu padahal membutuhkan Debian), salah mengatur iptables/UFW sehingga terkunci dari SSH, atau gagal mengompilasi aplikasi mandiri.
                            </li>
                            <li>
                                <strong>Inkompatibilitas Aplikasi Eksternal:</strong> Aplikasi atau script pihak ketiga milik Pengguna yang tidak berjalan karena membutuhkan dependensi proprietary di luar spesifikasi Linux standar.
                            </li>
                            <li>
                                <strong>Pengajuan Melewati Batas Waktu:</strong> Permohonan yang diajukan setelah melewati batas 48 jam sejak server aktif pertama kali.
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 4 -->
                <section id="pasal-4" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 4
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Ketentuan Transaksi Marketplace Shopee
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            4.1. Bagi transaksi pemesanan yang dilakukan melalui Toko Resmi Shopee VexaHost, mekanisme pembatalan pesanan dan resolusi dana tunduk pada Syarat &amp; Ketentuan Perlindungan Pembeli (Garansi Shopee).
                        </p>
                        <p>
                            4.2. Apabila Anda mengalami kendala teknis pada 48 jam pertama untuk pesanan Shopee, Anda wajib berkomunikasi terlebih dahulu dengan Customer Care VexaHost melalui chat resmi Shopee sebelum mengklik tombol "Pesanan Diterima".
                        </p>
                    </div>
                </section>

                <!-- Pasal 5 -->
                <section id="pasal-5" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 5
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Persyaratan &amp; Alur Pengajuan Klaim Refund
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Pengajuan klaim pengembalian dana dilakukan melalui tahapan formal:
                        </p>
                        <ol class="list-decimal list-inside space-y-2 pl-2 text-xs text-slate-700">
                            <li>Buka <strong>Tiket Bantuan Dukungan Teknis</strong> pada Client Dashboard dengan kategori departemen <em>Billing &amp; Finance</em>.</li>
                            <li>Sertakan <strong>Nomor Faktur / Invoice ID</strong> (contoh: <code class="font-mono-code bg-slate-100 px-1 py-0.5 rounded">INV-XXXX</code>) dan Alamat IP instans yang bersangkutan.</li>
                            <li>Jelaskan secara terperinci deskripsi kendala teknis yang dihadapi beserta tangkapan layar (screenshot) log galat diagnostik.</li>
                            <li>Tim teknis VexaHost akan melakukan verifikasi forensik pada hypervisor dalam waktu maksimal 1x24 jam sejak tiket diterima.</li>
                        </ol>
                    </div>
                </section>

                <!-- Pasal 6 -->
                <section id="pasal-6" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 6
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Saluran Pencairan &amp; Estimasi Waktu Proses
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            6.1. Setelah permohonan pengembalian dana dinyatakan disetujui secara tertulis oleh tim Finance, dana akan ditransfer kembali ke rekening atau dompet digital asal Pelanggan dalam waktu <strong>1 hingga 3 hari kerja operasional perbankan</strong> (tidak termasuk hari Sabtu, Minggu, dan hari libur nasional).
                        </p>
                        <p>
                            6.2. Saluran pengembalian dana yang didukung mencakup:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-xs text-slate-700">
                            <li><strong>Transfer Bank Nasional:</strong> Bank Central Asia (BCA), Bank Mandiri, Bank Rakyat Indonesia (BRI), Bank Negara Indonesia (BNI).</li>
                            <li><strong>Dompet Digital (E-Wallet):</strong> GoPay, OVO, DANA, ShopeePay.</li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 7 -->
                <section id="pasal-7" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 7
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Biaya Administrasi Transaksi Pihak Ketiga
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Nilai dana yang dikembalikan adalah murni nilai sewa layanan yang diterima VexaHost. Biaya transaksi pihak ketiga (MDR payment gateway Lynk / QRIS / biaya admin bank) yang dipotong langsung oleh pihak perbankan saat transaksi awal bersifat non-refundable dan berada di luar yurisdiksi kendali VexaHost.
                        </p>
                    </div>
                </section>

                <!-- Pasal 8 -->
                <section id="pasal-8" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 8
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Pencegahan Penyalahgunaan Kebijakan (Fraud &amp; Abuse Prevention)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Kebijakan pengembalian dana dirancang untuk melindungi hak pelanggan yang beritikad baik. VexaHost berhak menolak permohonan refund dari pengguna yang terindikasi melakukan penyalahgunaan sistematis (churning), seperti berulang kali memesan server untuk mengeksploitasi bandwidth atau kuota komputasi singkat lalu meminta pengembalian dana.
                        </p>
                        <p>
                            Pengguna yang terbukti melakukan penyalahgunaan kebijakan refund dapat dikenai pemblokiran akun permanen dari platform VexaHost.
                        </p>
                    </div>

                    <!-- Sign-off block -->
                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 text-xs text-slate-500">
                        <div>
                            <p class="font-normal text-slate-900">VexaHost Cloud Indonesia</p>
                            <p class="text-slate-500 mt-0.5">Departemen Keuangan, Penagihan &amp; Perlindungan Konsumen</p>
                        </div>
                        <div class="font-mono-code text-[11px] text-slate-400">
                            Dokumen Resmi: REF-2026-V2
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>
@endsection
