@extends('layouts.app', [
    'title' => 'Kebijakan Privasi (Privacy Policy) — VexaHost',
    'description' => 'Kebijakan Privasi VexaHost: data apa yang kami simpan, berapa lama disimpan, untuk apa dipakai, dan bagaimana Anda meminta penghapusannya.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Kebijakan Privasi'],
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
                    <a href="{{ route('privacy') }}" class="px-2.5 py-1 rounded-md font-bold bg-slate-900 text-white whitespace-nowrap">
                        Privacy Policy
                    </a>
                    <a href="{{ route('refund') }}" class="px-2.5 py-1 rounded-md font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors whitespace-nowrap">
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
                        Dokumen Hukum Resmi
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Kepatuhan: UU PDP No. 27/2022
                    </span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-bold tracking-tight text-slate-900">
                    Kebijakan Privasi (Privacy Policy)
                </h1>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Standar transparansi pengumpulan, pemrosesan, perlindungan, serta hak pemusnahan data pribadi pengguna di ekosistem VexaHost, dikelola oleh VexaHost Cloud Indonesia.
                </p>

                <!-- Metadata Grid -->
                <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block font-medium">Pengendali Data</span>
                        <span class="font-normal text-slate-800 mt-0.5 block">PT DESTINARA CHAKRAWALA ARTHA</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Revisi Terakhir</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">16 September 2026</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Regulasi Rujukan</span>
                        <span class="font-semibold text-slate-800 mt-0.5 block">UU PDP RI &amp; UU ITE</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Identifikasi Versi</span>
                        <span class="font-mono-code font-semibold text-slate-800 mt-0.5 block">PRIV-2026-V2</span>
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
                            Daftar Klausul Privasi
                        </h2>
                        <nav class="space-y-1 text-xs">
                            <button @click="scrollTo('pasal-1')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                1. Komitmen &amp; Landasan Hukum
                            </button>
                            <button @click="scrollTo('pasal-2')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                2. Zero-Knowledge Server Policy
                            </button>
                            <button @click="scrollTo('pasal-3')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                3. Kategori Data yang Dikumpulkan
                            </button>
                            <button @click="scrollTo('pasal-4')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                4. Dasar Hukum &amp; Tujuan Pemrosesan
                            </button>
                            <button @click="scrollTo('pasal-5')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                5. Standar Keamanan Enkripsi
                            </button>
                            <button @click="scrollTo('pasal-6')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                6. Pembagian Data Pihak Ketiga
                            </button>
                            <button @click="scrollTo('pasal-7')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                7. Jadwal Retensi &amp; Pemusnahan
                            </button>
                            <button @click="scrollTo('pasal-8')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                8. Hak-Hak Subjek Data Pribadi
                            </button>
                            <button @click="scrollTo('pasal-9')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                9. Cookies &amp; Sesi Autentikasi
                            </button>
                            <button @click="scrollTo('pasal-10')" class="w-full text-left px-2.5 py-2 rounded-lg font-medium transition-colors text-slate-600 hover:text-slate-900 hover:bg-slate-50 block">
                                10. Kontak Data Protection Officer
                            </button>
                        </nav>
                    </div>

                    <!-- DPO Contact Card -->
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-xs space-y-2.5 shadow-2xs">
                        <div class="font-bold text-slate-900 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Data Protection Officer</span>
                        </div>
                        <p class="text-slate-500 leading-relaxed">
                            Permohonan hak akses, koreksi, atau penghapusan data akun (Right to Erasure) ditujukan ke:
                        </p>
                        <div class="space-y-1 font-mono-code text-[11px]">
                            <p class="text-slate-800 bg-slate-50 p-1.5 rounded border border-slate-100">vexahostcloudtech@gmail.com</p>
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
                            Komitmen Privasi &amp; Landasan Regulasi
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            <span class="font-normal">VexaHost Cloud Indonesia</span> ("Kami", "VexaHost") mengelola platform penyediaan server cloud dengan komitmen tertinggi terhadap perlindungan privasi dan integritas data pribadi pengguna ("Pelanggan", "Anda").
                        </p>
                        <p>
                            Dokumen Kebijakan Privasi ini disusun berdasarkan ketentuan perundang-undangan Negara Republik Indonesia, khususnya <strong>Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)</strong> serta <strong>Undang-Undang Nomor 1 Tahun 2024 tentang Perubahan Kedua atas UU Nomor 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik (UU ITE)</strong>.
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
                            Prinsip Dasar: Zero-Knowledge Server Access
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Sebagai penyedia layanan <em>Infrastructure-as-a-Service</em> (IaaS), VexaHost memisahkan secara tegas antara data operasional akun pelanggan dan data internal yang tersimpan di dalam Virtual Private Server milik Pelanggan.
                        </p>
                        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-2">
                            <strong class="font-bold text-emerald-950 block">Pernyataan Kedaulatan Data Server:</strong>
                            <ul class="space-y-1 list-disc list-inside">
                                <li>Kami <strong>TIDAK PERNAH</strong> membuka, menginspeksi, memindai, menyalin, mendistribusikan, atau memonetisasi kode sumber (source code), berkas aplikasi, basis data (database), file konfigurasi, atau rekaman log apa pun yang Anda simpan di dalam instans VPS.</li>
                                <li>Enkripsi level aplikasi dan penguncian hak akses root SSH merupakan hak eksklusif yang sepenuhnya berada di bawah kendali Pelanggan.</li>
                                <li>Staf VexaHost tidak memiliki pintu belakang (backdoor) ke dalam sistem operasi tamu (guest OS).</li>
                            </ul>
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
                            Kategori Data Pribadi yang Kami Kumpulkan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Kami hanya mengumpulkan data yang relevan dan esensial untuk penyediaan layanan hosting, pemrosesan transaksi, serta pemeliharaan keamanan sistem.
                        </p>

                        <!-- Data Category Matrix -->
                        <div class="overflow-x-auto my-4">
                            <table class="w-full text-left border-collapse border border-slate-200 text-xs">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-900">
                                        <th class="p-3 border border-slate-200 font-bold w-1/4">Kategori Data</th>
                                        <th class="p-3 border border-slate-200 font-bold w-1/3">Rincian Komponen Data</th>
                                        <th class="p-3 border border-slate-200 font-bold">Tujuan Pengumpulan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Identitas Pengguna</td>
                                        <td class="p-3 border border-slate-200">Nama lengkap, username, alamat email, dan identifikasi akun Google (jika mendaftar via SSO).</td>
                                        <td class="p-3 border border-slate-200">Registrasi akun, autentikasi portal, dan pengiriman invoice.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Kontak Operasional</td>
                                        <td class="p-3 border border-slate-200">Nomor telepon seluler / WhatsApp aktif.</td>
                                        <td class="p-3 border border-slate-200">Notifikasi status darurat server dan konfirmasi pesanan Shopee.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Transaksi &amp; Pembayaran</td>
                                        <td class="p-3 border border-slate-200">ID transaksi Lynk, referensi pembayaran QRIS, nomor invoice, dan status pelunasan tagihan.</td>
                                        <td class="p-3 border border-slate-200">Verifikasi aktivasi otomatis, pelaporan pajak, dan pembukuan keuangan.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200 font-semibold text-slate-900">Log Teknis &amp; Audit</td>
                                        <td class="p-3 border border-slate-200">Alamat IP pengakses dashboard, user agent browser, timestamp login, dan aktivitas sesi.</td>
                                        <td class="p-3 border border-slate-200">Audit keamanan siber, proteksi akun terhadap pembobolan brute-force.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-slate-500">
                            Catatan: VexaHost <strong>tidak pernah menyimpan data kartu kredit atau kredensial CVV</strong> pada server kami. Seluruh proses pembayaran online ditangani langsung oleh payment gateway resmi berlisensi Bank Indonesia.
                        </p>
                    </div>
                </section>

                <!-- Pasal 4 -->
                <section id="pasal-4" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 4
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Dasar Hukum &amp; Tujuan Pemrosesan Data
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Berdasarkan Pasal 20 UU PDP, kami memproses data pribadi Anda atas dasar:
                        </p>
                        <ul class="space-y-2 pl-4 border-l-2 border-slate-200 text-xs">
                            <li><strong>Pelaksanaan Kontrak (Contractual Obligation):</strong> Untuk memproses pesanan, mengalokasikan unit server, serta menerbitkan akses root SSH kepada Anda.</li>
                            <li><strong>Kewajiban Hukum (Legal Compliance):</strong> Untuk memenuhi kewajiban perpajakan, pencatatan transaksi keuangan, dan pemenuhan regulasi telekomunikasi siber RI.</li>
                            <li><strong>Kepentingan Sah (Legitimate Interest):</strong> Untuk mencegah penipuan transaksi, menjaga stabilitas jaringan dari serangan botnet, dan meningkatkan keamanan sistem.</li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 5 -->
                <section id="pasal-5" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 5
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Standar Keamanan Enkripsi &amp; Pengamanan Sistem
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            VexaHost menerapkan arsitektur pertahanan berlapis (defense-in-depth) untuk melindungi informasi akun Pelanggan:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-2 text-xs">
                            <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50">
                                <span class="font-bold text-slate-900 block mb-1">Enkripsi Data Transit (In-Transit)</span>
                                Seluruh komunikasi data web antara peramban Anda dan portal VexaHost diproteksi protokol Transport Layer Security (TLS 1.3 / HTTPS) dengan sertifikat 256-bit.
                            </div>
                            <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50">
                                <span class="font-bold text-slate-900 block mb-1">Enkripsi Data Diam (At-Rest)</span>
                                Kata sandi akun disimpan dalam bentuk hash irreversible searah menggunakan algoritma bcrypt/Argon2 dengan salt dinamis.
                            </div>
                            <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50">
                                <span class="font-bold text-slate-900 block mb-1">Autentikasi Multi-Faktor (2FA)</span>
                                Portal client menyediakan mekanisme Time-based One-Time Password (TOTP via Google Authenticator atau Authy) untuk perlindungan ganda.
                            </div>
                            <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50">
                                <span class="font-bold text-slate-900 block mb-1">Audit Log Akses &amp; Deteksi Sesi</span>
                                Sistem secara aktif mencatat IP akses autentikasi dan menyediakan dashboard mandiri bagi Pelanggan untuk memutus sesi aktif mencurigakan.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Pasal 6 -->
                <section id="pasal-6" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 6
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Pengungkapan Data kepada Pihak Ketiga
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            VexaHost <strong>tidak pernah menjual, menyewakan, atau memperdagangkan</strong> data pribadi Anda kepada pihak mana pun untuk keperluan periklanan pihak ketiga.
                        </p>
                        <p>
                            Pengungkapan data hanya dilakukan secara terbatas kepada mitra penyedia infrastruktur yang terikat perjanjian kerahasiaan:
                        </p>
                        <ul class="list-disc list-inside space-y-1.5 pl-2 text-xs text-slate-700">
                            <li><strong>Penyedia Layanan Pembayaran:</strong> Gateway pembayaran berlisensi (Lynk.id / ShopeePay) untuk verifikasi pelunasan invoice.</li>
                            <li><strong>Mitra Datacenter &amp; Upstream:</strong> Penyedia fasilitas jaringan (Tencent Cloud &amp; Cloudeka Lintasarta) dalam rangka perutean subnet IP publik yang dialokasikan.</li>
                            <li><strong>Aparat Penegak Hukum:</strong> Hanya jika diwajibkan oleh penetapan pengadilan atau surat tugas resmi instansi penegak hukum yang sah sesuai hukum acara pidana Republik Indonesia.</li>
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
                            Jadwal Retensi Data &amp; Pemusnahan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Kami menyimpan data pribadi Anda selama akun Anda aktif atau selama diperlukan untuk memenuhi tujuan hukum dan pembukuan.
                        </p>
                        <div class="overflow-x-auto my-3">
                            <table class="w-full text-left border-collapse border border-slate-200 text-xs">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-900">
                                        <th class="p-3 border border-slate-200 font-bold">Komponen Data</th>
                                        <th class="p-3 border border-slate-200 font-bold">Durasi Retensi</th>
                                        <th class="p-3 border border-slate-200 font-bold">Prosedur Pemusnahan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr>
                                        <td class="p-3 border border-slate-200">Data Virtual Disk VPS (Node)</td>
                                        <td class="p-3 border border-slate-200 font-mono-code text-slate-800">{{ app(\App\Services\SettingsService::class)->int('renewal_grace_days_vps', 5) }} hari setelah jatuh tempo</td>
                                        <td class="p-3 border border-slate-200">Zero-fill override &amp; penghapusan partisi virtual.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200">Log Autentikasi &amp; Akses IP</td>
                                        <td class="p-3 border border-slate-200 font-mono-code text-slate-800">90 hari kalender</td>
                                        <td class="p-3 border border-slate-200">Rotasi otomatis dan purge database log.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 border border-slate-200">Arsip Faktur &amp; Invoice Pajak</td>
                                        <td class="p-3 border border-slate-200 font-mono-code text-slate-800">5 tahun</td>
                                        <td class="p-3 border border-slate-200">Diwajibkan oleh ketentuan perundang-undangan perpajakan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Pasal 8 -->
                <section id="pasal-8" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 8
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Hak-Hak Subjek Data Pribadi (Pengguna)
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Sesuai Bab IV UU PDP, Pelanggan memiliki hak-hak hukum atas data pribadi yang tersimpan pada kami:
                        </p>
                        <ul class="space-y-2 pl-4 border-l-2 border-slate-200 text-xs">
                            <li><strong>Hak Mendapatkan Akses:</strong> Anda berhak meninjau seluruh data profil, faktur, dan log instans yang kami simpan melalui menu Pengaturan Dashboard.</li>
                            <li><strong>Hak Koreksi (Rectification):</strong> Anda berhak memperbarui atau merevisi informasi kontak yang sudah tidak akurat kapan saja.</li>
                            <li><strong>Hak Penghapusan Akun (Right to Erasure):</strong> Anda berhak mengajukan penghapusan akun secara permanen sepanjang Anda tidak memiliki tanggungan tagihan aktif atau instans yang sedang berjalan.</li>
                            <li><strong>Hak Menarik Persetujuan:</strong> Anda berhak menghentikan langganan notifikasi promosi atau email pembaruan sistem berkala.</li>
                        </ul>
                    </div>
                </section>

                <!-- Pasal 9 -->
                <section id="pasal-9" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 9
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Kebijakan Cookies &amp; Sesi Autentikasi
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Kami menggunakan cookies sesi teknis murni yang diperlukan (strictly necessary cookies) untuk mengelola status login, proteksi Cross-Site Request Forgery (CSRF token), dan preferensi antarmuka pengguna.
                        </p>
                        <p>
                            Kami tidak menggunakan cookies pelacak lintas platform (cross-site tracking cookies) dari jaringan periklanan pihak ketiga.
                        </p>
                    </div>
                </section>

                <!-- Pasal 10 -->
                <section id="pasal-10" class="scroll-mt-36 bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-2xs">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="px-2.5 py-1 rounded text-xs font-mono-code font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            PASAL 10
                        </span>
                        <h2 class="text-xl font-bold text-slate-900">
                            Petugas Pelindungan Data (DPO) &amp; Pengajuan Permohonan
                        </h2>
                    </div>
                    <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                        <p>
                            Untuk mengajukan pertanyaan, permintaan penegakan hak subjek data, atau pelaporan insiden keamanan data pribadi, Anda dapat menghubungi Petugas Pelindungan Data Pribadi kami:
                        </p>
                        <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <p><strong class="text-slate-900">Data Protection Officer — VexaHost</strong></p>
                            <p class="text-slate-600">VexaHost Cloud Indonesia</p>
                            <p class="text-slate-600">Alamat Surat Elektronik: <code class="font-mono-code bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-900">vexahostcloudtech@gmail.com</code></p>
                            <p class="text-slate-600">Waktu Respons: Maksimal 3 (tiga) hari kerja operasional</p>
                        </div>
                    </div>

                    <!-- Sign-off block -->
                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 text-xs text-slate-500">
                        <div>
                            <p class="font-normal text-slate-900">VexaHost Cloud Indonesia</p>
                            <p class="text-slate-500 mt-0.5">Departemen Tata Kelola Privasi &amp; Kepatuhan UU PDP</p>
                            <p class="text-[11px] text-slate-400 mt-2">Platform created by vexahostcloud.</p>
                        </div>
                        <div class="font-mono-code text-[11px] text-slate-400">
                            Dokumen Resmi: PRIV-2026-V2
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>
@endsection
