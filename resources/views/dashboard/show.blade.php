@extends('layouts.dashboard', ['title' => 'Detail VPS ' . ($vps->hostname ?? $vps->id), 'headerTitle' => 'Kelola Instance VPS', 'backUrl' => route('dashboard.index'), 'backLabel' => 'Kembali ke Daftar VPS'])

@section('content')
<div class="space-y-6" x-data="{
    activeTab: '{{ $vps->isAiPackage() ? 'webapp' : ($vps->isDatabasePackage() ? 'database' : 'overview') }}',
    renewalModal: false,
    reinstallModal: {{ $errors->hasAny(['os', 'control_panel', 'confirm_hostname', 'notes']) ? 'true' : 'false' }},
    rebootGuideModal: false,
    unreachableModal: {{ $errors->has('description') ? 'true' : 'false' }},
    passwordLoading: false,
    passwordError: '',
    revealedPassword: '',
    showPassword: false,
    revealedAt: '{{ $vps->root_password_revealed_at ? $vps->root_password_revealed_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') : '' }}',
    showDbPassword: false,
    copyText(text) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showAlert('Teks disalin ke clipboard: ' + text, { icon: 'success', title: 'Berhasil disalin' });
    },
    copySecret(text) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showAlert('Password disalin ke clipboard.', { icon: 'success', title: 'Berhasil disalin' });
    },
    // Ikon mata: klik = tampil, klik lagi = sembunyi. Password diambil dari server
    // (terenkripsi di database) hanya saat pertama kali dibuka, lalu disimpan di memori halaman.
    async togglePassword() {
        if (this.showPassword) {
            this.showPassword = false;
            return;
        }
        if (this.revealedPassword) {
            this.showPassword = true;
            return;
        }
        if (this.passwordLoading) return;
        this.passwordLoading = true;
        this.passwordError = '';
        try {
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
            const res = await fetch('{{ route('dashboard.vps.reveal-password', $vps->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success && data.password) {
                this.revealedPassword = data.password;
                this.revealedAt = data.revealed_at || this.revealedAt;
                this.showPassword = true;
            } else {
                this.passwordError = data.message || 'Password belum tersedia. Silakan hubungi support.';
            }
        } catch (err) {
            this.passwordError = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        } finally {
            this.passwordLoading = false;
        }
    },
    async revealFromGuide() {
        this.activeTab = 'access';
        if (!this.showPassword) await this.togglePassword();
    }
}">
    <!-- Back to Instances -->
    <div>
        <a href="{{ route('dashboard.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke VPS Instances</span>
        </a>
    </div>

    <!-- Lifecycle & Status Warning Alerts -->
    @if($vps->status === 'suspended')
        <div class="p-4 rounded-lg bg-slate-100 border border-slate-300 text-slate-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-bold text-sm block">Instance VPS Ditangguhkan (Suspended)</strong>
                Layanan VPS ini sedang ditangguhkan karena melewati jatuh tempo pembayaran. Perpanjang sekarang untuk mengaktifkan kembali server.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="px-3.5 py-1.5 rounded-lg bg-black text-white hover:bg-neutral-800 text-xs font-semibold whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang & Aktifkan Kembali
                </button>
            @endif
        </div>
    @elseif($vps->isInGracePeriod())
        <div class="p-4 rounded-lg bg-slate-100 border border-slate-300 text-slate-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-bold text-sm block">Masa Tenggang Berlangganan (Grace Period)</strong>
                Masa aktif VPS telah jatuh tempo. Anda memiliki masa tenggang hingga <strong>{{ $vps->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</strong> sebelum layanan dihentikan otomatis.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="px-3.5 py-1.5 rounded-lg bg-black text-white hover:bg-neutral-800 text-xs font-semibold whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang Layanan Sekarang
                </button>
            @endif
        </div>
    @elseif($vps->isExpired())
        <div class="p-4 rounded-lg bg-slate-100 border border-slate-300 text-slate-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-bold text-sm block">Masa Aktif Berakhir (Expired)</strong>
                Masa aktif server telah kedaluwarsa pada {{ $vps->expires_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="px-3.5 py-1.5 rounded-lg bg-black text-white hover:bg-neutral-800 text-xs font-semibold whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang Layanan
                </button>
            @endif
        </div>
    @endif

    @if($vps->isEolWithReplacement())
        <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 flex items-start gap-3">
            <div class="text-xs leading-relaxed">
                <strong class="font-bold text-slate-900 text-sm block mb-0.5">Penyesuaian Skema Paket (End-of-Life)</strong>
                Paket lama Anda sudah tidak lagi diproduksi oleh penyedia infrastruktur (End-of-Life). Perpanjangan masa aktif untuk server ini akan disesuaikan secara otomatis ke skema paket <strong>{{ $vps->eol_replacement_spec?->name }}</strong> (Rp {{ number_format($vps->renewal_price, 0, ',', '.') }}/bulan) tanpa mengubah data di dalam server Anda.
            </div>
        </div>
    @elseif($vps->isEolWithoutReplacement())
        <div class="p-4 rounded-lg bg-slate-100 border border-slate-300 text-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs leading-relaxed">
                <strong class="font-bold text-slate-900 text-sm block mb-0.5">Paket Dihentikan Permanen (End-of-Life)</strong>
                Paket lama Anda telah dihentikan secara permanen oleh penyedia infrastruktur. Hubungi tim dukungan untuk bantuan migrasi server ke paket aktif.
            </div>
            <a href="{{ route('dashboard.support') }}" class="px-3.5 py-1.5 rounded-lg bg-black text-white hover:bg-neutral-800 text-xs font-semibold whitespace-nowrap self-start sm:self-auto">
                Hubungi Tim Dukungan
            </a>
        </div>
    @endif

    <!-- Permintaan layanan yang masih diproses tim -->
    @foreach($openRequests as $openRequest)
        <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-xs">
                    <strong class="font-bold text-sm block">
                        {{ $openRequest->type_label }} sedang {{ $openRequest->status === 'in_progress' ? 'dikerjakan' : 'menunggu diproses' }} tim
                    </strong>
                    Diajukan {{ $openRequest->created_at->locale('id')->diffForHumans() }}. Estimasi selesai maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}). Anda akan menerima email saat selesai.
                </div>
            </div>
            <a href="{{ route('dashboard.support.show', $openRequest->id) }}" class="px-3.5 py-1.5 rounded-lg bg-white border border-blue-300 hover:bg-blue-100 text-blue-900 text-xs font-semibold whitespace-nowrap self-start sm:self-auto">
                Lihat Tiket #TK-{{ str_pad($openRequest->id, 4, '0', STR_PAD_LEFT) }}
            </a>
        </div>
    @endforeach

    <!-- Server Top Card -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center font-bold text-lg shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-slate-900">{{ $vps->hostname ?? 'VPS-' . $vps->id }}</h1>
                    @if($vps->isDatabasePackage())
                        <span class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Managed DB
                        </span>
                    @elseif($vps->isAiPackage())
                        <span class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                            AI Combo
                        </span>
                    @endif
                    @php $customerStatus = $vps->customer_status; @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase border {{ $customerStatus['class'] }}" title="Status layanan">
                        <span class="w-2 h-2 rounded-full {{ $customerStatus['dot'] }}"></span>
                        {{ $customerStatus['label'] }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-600 mt-1.5">
                    <span>IP: <strong class="text-slate-900 font-mono-code">{{ $vps->public_ip ?? 'Belum dialokasikan' }}</strong></span>
                    <span>&bull;</span>
                    <span>OS: <strong class="text-slate-900">{{ $vps->os_label }}</strong></span>
                    <span>&bull;</span>
                    <span>Provider: <strong class="text-slate-900">{{ $vps->provider_label }}</strong></span>
                    <span>&bull;</span>
                    <span>Stack: <strong class="text-slate-900">{{ $vps->control_panel_label }}</strong></span>
                    <span>&bull;</span>
                    <span>Siklus: <strong class="text-slate-900 uppercase">{{ str_replace('_', ' ', $vps->billing_cycle ?? 'monthly') }}</strong></span>
                </div>
            </div>
        </div>

        <!-- Action Toolbar -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Perpanjang Layanan</span>
                </button>
            @elseif($vps->isEolWithoutReplacement())
                <a href="{{ route('dashboard.support') }}" class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <span>Hubungi Tim Migrasi</span>
                </a>
            @endif

            @if($vps->public_ip && $vps->status === 'running')
                @if($vps->isAiPackage() && $vps->app_url)
                    <a href="{{ $vps->app_url }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
                        <span>Buka Web App</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @elseif($vps->isDatabasePackage() && $vps->web_manager_url_resolved)
                    <a href="{{ $vps->web_manager_url_resolved }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
                        <span>Buka Web Manager</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @elseif(!$vps->isDatabasePackage() && $vps->panel_url)
                    {{-- Hanya link yang diisi admin; tanpa link, tombol tidak ditampilkan (bukan menebak IP:port). --}}
                    <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-semibold transition-colors flex items-center gap-1.5">
                        <span>Panel Web</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @endif
            @endif

            {{-- Panel tidak menjalankan aksi daya sendiri (server retail tanpa API).
                 Reboot dilakukan pelanggan lewat SSH, reinstall diajukan ke tim. --}}
            {{-- Panduan reboot lewat SSH hanya relevan bila server menyala (atau sedang
                 bermasalah, karena modalnya juga berisi tombol lapor ke tim). --}}
            @if(in_array($vps->status, ['running', 'error'], true))
                <button type="button" @click="rebootGuideModal = true" class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-900 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Cara Reboot
                </button>
            @endif

            @if(isset($openRequests['reinstall']))
                <a href="{{ route('dashboard.support.show', $openRequests['reinstall']->id) }}" class="px-4 py-2 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-xs font-semibold text-blue-800 transition-colors">
                    Reinstall Sedang Diproses
                </a>
            @elseif($vps->acceptsServiceRequests())
                <button type="button" @click="reinstallModal = true" class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-900 transition-colors">
                    Ajukan Reinstall OS
                </button>
            @endif
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold pt-1">
        @if($vps->isDatabasePackage())
            <button @click="activeTab = 'database'"
                    :class="activeTab === 'database' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors flex items-center gap-1.5">
                <span>Koneksi Database (.env)</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            </button>
            <button @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Ringkasan Server
            </button>
            <button @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Akses SSH Server
            </button>
        @elseif($vps->isAiPackage())
            <button @click="activeTab = 'webapp'"
                    :class="activeTab === 'webapp' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors flex items-center gap-1.5">
                <span>Akses Web App</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            </button>
            <button @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Ringkasan Server
            </button>
            <button @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Akses SSH (Lanjutan)
            </button>
        @else
            <button @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Ringkasan Server
            </button>
            <button @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Detail Akses & SSH
            </button>
        @endif

        <button @click="activeTab = 'subscription'"
                :class="activeTab === 'subscription' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-4 rounded-lg transition-colors">
            Berlangganan & Siklus
        </button>

        @if(!$vps->isAiPackage() && !$vps->isDatabasePackage())
            <button @click="activeTab = 'panel'"
                    :class="activeTab === 'panel' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="py-2 px-4 rounded-lg transition-colors">
                Control Panel
            </button>
        @endif

        <button @click="activeTab = 'requests'"
                :class="activeTab === 'requests' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-4 rounded-lg transition-colors flex items-center gap-1.5">
            <span>Permintaan & Bantuan</span>
            @if($openRequests->isNotEmpty())
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-100 text-blue-800" :class="activeTab === 'requests' ? 'bg-neutral-800 text-white' : ''">
                    {{ $openRequests->count() }}
                </span>
            @endif
        </button>

        <button @click="activeTab = 'logs'"
                :class="activeTab === 'logs' ? 'bg-black text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-4 rounded-lg transition-colors flex items-center gap-1.5">
            <span>Audit Trail & Riwayat</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-200 text-slate-800" :class="activeTab === 'logs' ? 'bg-neutral-800 text-white' : ''">
                {{ $vps->activityLogs->count() }}
            </span>
        </button>
    </div>

    @if($vps->isDatabasePackage())
    @php
        $dbHost = $vps->public_ip ?? '103.xxx.xxx.xxx';
        $dbPort = $vps->db_port_resolved;
        $dbConn = $vps->db_connection_driver;
        $dbName = $vps->db_name ?? 'vexadb_production';
        $dbUser = $vps->db_user ?? 'admin_vexa';
        $dbPass = $vps->db_password ?: ($vps->initial_root_password ?: 'vexapass123');
        $dbUrl = $vps->database_url;
        $webMgrUrl = $vps->web_manager_url_resolved;
        $dbEngineLabel = match($vps->db_engine) {
            'mysql' => 'MySQL 8.0 / MariaDB 11',
            'redis' => 'Redis 7 In-Memory Cache',
            'mongodb' => 'MongoDB 7 Community',
            'vector' => 'Qdrant / pgvector (AI & RAG)',
            default => 'PostgreSQL 16',
        };
        $dbManagerLabel = $vps->db_manager === 'cli_only' ? 'CLI Only (Headless)' : 'CloudBeaver / Adminer Web GUI';

        $fullEnvConfig = "DB_CONNECTION={$dbConn}\n"
                       . "DB_HOST={$dbHost}\n"
                       . "DB_PORT={$dbPort}\n"
                       . "DB_DATABASE={$dbName}\n"
                       . "DB_USERNAME={$dbUser}\n"
                       . "DB_PASSWORD={$dbPass}\n"
                       . "DATABASE_URL={$dbUrl}";
        if ($webMgrUrl) {
            $fullEnvConfig .= "\nWEB_MANAGER_URL={$webMgrUrl} (CloudBeaver)";
        }
    @endphp
    <!-- Tab Database: Kredensial & Connection String (.env) -->
    <div x-show="activeTab === 'database'" class="space-y-6">
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-slate-900">Format Connection String Database</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            {{ $dbEngineLabel }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Format connection string siap pakai untuk disalin langsung ke file <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-800 font-mono-code">.env</code> aplikasi backend Anda.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($webMgrUrl && $vps->status === 'running')
                        <a href="{{ $webMgrUrl }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-xs">
                            <span>Buka Web GUI</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @endif
                    <button type="button" @click="copyText(@js($fullEnvConfig))" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Salin Format .env</span>
                    </button>
                </div>
            </div>

            <!-- Connection String .env Code Block -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Konfigurasi .env Aplikasi (Laravel, Node.js, Next.js, Django, FastAPI, Go)
                    </span>
                    <button type="button" @click="showDbPassword = !showDbPassword" class="text-xs font-medium text-slate-600 hover:text-black flex items-center gap-1">
                        <svg x-show="!showDbPassword" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showDbPassword" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        <span x-text="showDbPassword ? 'Sembunyikan Password' : 'Lihat Password'"></span>
                    </button>
                </div>

                <div class="relative group">
                    <pre class="bg-slate-950 text-slate-100 p-4 sm:p-5 rounded-lg font-mono-code text-xs sm:text-sm overflow-x-auto leading-relaxed border border-slate-800 shadow-inner select-all"><code>DB_CONNECTION={{ $dbConn }}
DB_HOST={{ $dbHost }}
DB_PORT={{ $dbPort }}
DB_DATABASE={{ $dbName }}
DB_USERNAME={{ $dbUser }}
DB_PASSWORD=<span x-text="showDbPassword ? '{{ $dbPass }}' : '****************'">****************</span>
DATABASE_URL={{ $dbUrl }}
@if($webMgrUrl)
WEB_MANAGER_URL={{ $webMgrUrl }} (CloudBeaver)
@endif</code></pre>
                    <button type="button" @click="copyText(@js($fullEnvConfig))" class="absolute top-3 right-3 px-3 py-1.5 rounded-md bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium transition-colors shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Salin .env</span>
                    </button>
                </div>
            </div>

            <!-- Detail Parameter Database (Grid 3 Kolom) -->
            <div class="space-y-3 pt-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Rincian Parameter Koneksi</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {{-- DB_CONNECTION --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_CONNECTION</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs">{{ $dbConn }}</span>
                        </div>
                        <button @click="copyText('{{ $dbConn }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- DB_HOST --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_HOST</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs">{{ $dbHost }}</span>
                        </div>
                        <button @click="copyText('{{ $dbHost }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- DB_PORT --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_PORT</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs">{{ $dbPort }}</span>
                        </div>
                        <button @click="copyText('{{ $dbPort }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- DB_DATABASE --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_DATABASE</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs">{{ $dbName }}</span>
                        </div>
                        <button @click="copyText('{{ $dbName }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- DB_USERNAME --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_USERNAME</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs">{{ $dbUser }}</span>
                        </div>
                        <button @click="copyText('{{ $dbUser }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- DB_PASSWORD --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DB_PASSWORD</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs" x-text="showDbPassword ? '{{ $dbPass }}' : '••••••••••••••••'"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="showDbPassword = !showDbPassword" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Lihat/Sembunyikan">
                                <svg x-show="!showDbPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showDbPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                            <button @click="copyText('{{ $dbPass }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin Password">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- DATABASE_URL --}}
                    <div class="sm:col-span-2 lg:col-span-2 p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">DATABASE_URL</span>
                            <span class="font-mono-code font-bold text-slate-900 text-xs truncate block">{{ $dbUrl }}</span>
                        </div>
                        <button @click="copyText('{{ $dbUrl }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white shrink-0" title="Salin URL">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    {{-- WEB_MANAGER_URL --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">WEB_MANAGER_URL</span>
                            @if($webMgrUrl)
                                <span class="font-mono-code font-bold text-emerald-700 text-xs">{{ $webMgrUrl }}</span>
                            @else
                                <span class="text-slate-400 text-xs font-mono-code">CLI Only (Disabled)</span>
                            @endif
                        </div>
                        @if($webMgrUrl)
                            <button @click="copyText('{{ $webMgrUrl }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-white" title="Salin">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Panduan Remote Database Client -->
            <div class="mt-4 p-4 rounded-lg bg-emerald-50/60 border border-emerald-200 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Panduan Remote Database Client</h4>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Server ini khusus untuk database dan terpisah dari server aplikasi Anda. Anda dapat menghubungkan aplikasi backend (Vercel, Railway, VPS luar) atau remote client (DBeaver, TablePlus, DataGrip, Navicat, psql, mysql client) via Dedicated IP Publik Static server:
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                    <div class="bg-white p-2.5 rounded border border-emerald-100">
                        <span class="text-slate-400 block text-[10px]">PostgreSQL</span>
                        <span class="font-mono-code font-bold text-slate-800">Port 5432</span>
                    </div>
                    <div class="bg-white p-2.5 rounded border border-emerald-100">
                        <span class="text-slate-400 block text-[10px]">MySQL / MariaDB</span>
                        <span class="font-mono-code font-bold text-slate-800">Port 3306</span>
                    </div>
                    <div class="bg-white p-2.5 rounded border border-emerald-100">
                        <span class="text-slate-400 block text-[10px]">Redis Cache</span>
                        <span class="font-mono-code font-bold text-slate-800">Port 6379</span>
                    </div>
                    <div class="bg-white p-2.5 rounded border border-emerald-100">
                        <span class="text-slate-400 block text-[10px]">Vector Qdrant</span>
                        <span class="font-mono-code font-bold text-slate-800">Port 6333</span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500">
                    Disarankan menggunakan SSH Tunneling (<code class="bg-white px-1 py-0.5 rounded border border-slate-200 text-slate-800 font-mono-code">ssh -L {{ $dbPort }}:localhost:{{ $dbPort }} {{ $vps->ssh_user }}&#64;{{ $dbHost }}</code>) untuk keamanan maksimal di lingkungan production.
                </p>
            </div>
        </div>
    </div>
    @endif

    @if($vps->isAiPackage())
    <!-- Tab 0: Akses Web App (Khusus Paket AI Combo) -->
    <div x-show="activeTab === 'webapp'" class="space-y-6">
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Akses Web App {{ $vps->app_name ?? $vps->control_panel_label }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tautan langsung menuju antarmuka runtime AI yang telah aktif di server Anda.</p>
                </div>
                @if($vps->app_url && $vps->status === 'running')
                    <a href="{{ $vps->app_url }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-2 self-start sm:self-auto">
                        <span>Buka Web App di Tab Baru</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @endif
            </div>

            <!-- Web App URL Box -->
            <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-slate-600">URL Akses Web:</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono-code font-bold text-slate-900 text-xs sm:text-sm bg-white px-3 py-1 rounded border border-slate-200 select-all">
                            {{ $vps->app_url ?? 'Sedang mengalokasikan...' }}
                        </span>
                        @if($vps->app_url)
                            <button @click="copyText('{{ $vps->app_url }}')" class="px-3 py-1 rounded border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-900" title="Salin URL">
                                Salin
                            </button>
                        @endif
                    </div>
                </div>
                <p class="text-[11px] text-slate-500">
                    Akses URL di atas melalui browser modern (Chrome, Safari, Firefox, Edge).
                </p>
            </div>

            <!-- Panduan Penggunaan Berdasarkan Paket/Stack -->
            <div class="space-y-3 pt-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Panduan Penggunaan Cepat</h4>

                @if($vps->control_panel === 'claude_opencode')
                    <div class="p-4 rounded-lg border border-slate-200 bg-white space-y-2 text-xs text-slate-700">
                        <p class="font-semibold text-slate-900">Combo Terminal Coding Agent:</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-slate-600">
                            <li>Buka Web Terminal melalui URL di atas atau sambungkan terminal lokal Anda melalui SSH.</li>
                            <li>Ketik <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-900 font-mono-code">claude</code> untuk menjalankan Claude Code CLI resmi Anthropic.</li>
                            <li>Ketik <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-900 font-mono-code">opencode</code> jika ingin menggunakan multi-provider AI model open-source.</li>
                            <li>Masukkan API Key Anda saat pertama kali diminta. Environment Docker, Git, Node.js LTS, dan Python 3.12 sudah terpasang.</li>
                        </ol>
                    </div>
                @elseif($vps->control_panel === 'vscode_server')
                    <div class="p-4 rounded-lg border border-slate-200 bg-white space-y-2 text-xs text-slate-700">
                        <p class="font-semibold text-slate-900">Combo Cloud AI Workstation:</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-slate-600">
                            <li>Buka URL Web App di atas langsung dari browser perangkat Anda (laptop, tablet/iPad).</li>
                            <li>Masukkan password VPS Anda jika diminta untuk membuka sesi Web VS Code Server.</li>
                            <li>Buka integrated terminal di VS Code (<code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-900 font-mono-code">Ctrl + `</code>) dan jalankan <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-900 font-mono-code">claude</code>.</li>
                        </ol>
                    </div>
                @elseif(in_array($vps->control_panel, ['hermes_omniroute', 'hermes_agent', 'omniroute', '9router']))
                    <div class="p-4 rounded-lg border border-slate-200 bg-white space-y-2 text-xs text-slate-700">
                        <p class="font-semibold text-slate-900">Combo Hermes Autonomous Hub:</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-slate-600">
                            <li>Buka antarmuka OmniRoute di URL di atas untuk mengonfigurasi routing AI providers dan API keys.</li>
                            <li>Hermes Agent runtime telah berjalan di background sebagai service 24/7.</li>
                            <li>Semua task dan automasi akan diproses secara otomatis melalui proxy routing terpadu.</li>
                        </ol>
                    </div>
                @else
                    <div class="p-4 rounded-lg border border-slate-200 bg-white space-y-2 text-xs text-slate-700">
                        <p class="font-semibold text-slate-900">Combo Enterprise Private AI &amp; RAG:</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-slate-600">
                            <li>Buka URL Web App di browser Anda untuk mengakses dashboard studio AI.</li>
                            <li>Buat Knowledge Base baru dan unggah dokumen PDF / data privat organisasi Anda.</li>
                            <li>Pilih local model via Ollama yang telah terintegrasi di server. Data tersimpan di server privat Anda.</li>
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Tab 1: Ringkasan -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        @if(!$vps->isAiPackage() && !$vps->isDatabasePackage() && $vps->status === 'running')
            <!-- Mulai di sini: 3 langkah pertama untuk server baru -->
            <div class="bg-white rounded-lg border border-slate-200 p-6">
                <h3 class="text-base font-bold text-slate-900">Mulai di Sini</h3>
                <p class="text-xs text-slate-500 mt-0.5 mb-4">Tiga langkah pertama setelah server Anda aktif.</p>

                <ol class="grid gap-3 md:grid-cols-3 text-xs">
                    <li class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-black text-white font-bold">1</span>
                        <p class="font-semibold text-slate-900">Ambil password SSH</p>
                        <p class="text-slate-500">Username: <span class="font-mono-code font-semibold text-slate-900">{{ $vps->ssh_user }}</span>. Klik untuk melihat password di tab Akses.</p>
                        <button type="button" @click="revealFromGuide()" class="px-3 py-1.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold">
                            Tampilkan Password
                        </button>
                    </li>
                    <li class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-black text-white font-bold">2</span>
                        <p class="font-semibold text-slate-900">Login ke server lewat SSH</p>
                        <p class="text-slate-500">Jalankan dari Terminal, PowerShell, atau PuTTY.</p>
                        <div class="flex items-center justify-between gap-2 p-2 rounded bg-black text-white font-mono-code text-[11px]">
                            <span class="truncate">{{ $vps->ssh_command }}</span>
                            <button type="button" @click="copyText(@js($vps->ssh_command))" class="px-2 py-0.5 rounded bg-neutral-800 hover:bg-neutral-700 font-sans shrink-0">Salin</button>
                        </div>
                    </li>
                    <li class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-black text-white font-bold">3</span>
                        <p class="font-semibold text-slate-900">Ganti password</p>
                        <p class="text-slate-500">Setelah diganti, simpan password baru Anda sendiri. Password awal di panel tidak berlaku lagi.</p>
                        <div class="flex items-center justify-between gap-2 p-2 rounded bg-black text-white font-mono-code text-[11px]">
                            <span>passwd</span>
                            <button type="button" @click="copyText('passwd')" class="px-2 py-0.5 rounded bg-neutral-800 hover:bg-neutral-700 font-sans shrink-0">Salin</button>
                        </div>
                    </li>
                </ol>

                @if($vps->control_panel && $vps->control_panel !== 'none')
                    <p class="text-xs text-slate-500 mt-4">
                        Memakai {{ $vps->control_panel_label }}? Buka tab
                        <button type="button" @click="activeTab = 'panel'" class="font-semibold text-slate-900 underline">Control Panel</button>
                        untuk alamat panelnya.
                    </p>
                @endif
            </div>
        @endif

        <!-- Ringkasan paket: hanya data yang benar-benar tercatat -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg border border-slate-200 p-5">
                <span class="text-xs text-slate-500 font-semibold block mb-1">Prosesor</span>
                <p class="text-lg font-bold text-slate-900">{{ $vps->cpu ?? 1 }} vCPU</p>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-5">
                <span class="text-xs text-slate-500 font-semibold block mb-1">Memori</span>
                <p class="text-lg font-bold text-slate-900">{{ $vps->ram ?? 1 }} GB RAM</p>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-5">
                <span class="text-xs text-slate-500 font-semibold block mb-1">Penyimpanan</span>
                <p class="text-lg font-bold text-slate-900">{{ $vps->disk ?? 20 }} GB</p>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-5">
                <span class="text-xs text-slate-500 font-semibold block mb-1">Status Layanan</span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-bold border {{ $customerStatus['class'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $customerStatus['dot'] }}"></span>
                    {{ $customerStatus['label'] }}
                </span>
                <p class="text-xs text-slate-500 mt-1.5">
                    @if($vps->expires_at)
                        {{ $vps->isExpired() ? 'Berakhir' : 'Aktif s/d' }} {{ $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') }}
                    @else
                        Masa aktif belum tercatat
                    @endif
                </p>
            </div>
        </div>

        <!-- Detail Server -->
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h3 class="text-base font-bold text-slate-900 mb-4">Detail Server</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
                <div>
                    <span class="text-slate-400 block mb-1">Sistem Operasi:</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->os_label ?: '—' }}</p>
                </div>

                <div>
                    <span class="text-slate-400 block mb-1">Stack / Control Panel:</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->control_panel_label ?: '—' }}</p>
                </div>

                <div>
                    <span class="text-slate-400 block mb-1">Lokasi Datacenter:</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->datacenter_location ? ucfirst($vps->datacenter_location) : '—' }}</p>
                </div>

                <div>
                    <span class="text-slate-400 block mb-1">Alamat IP Publik:</span>
                    <p class="font-bold text-slate-900 text-sm font-mono-code">{{ $vps->public_ip ?? 'Belum dialokasikan' }}</p>
                </div>

                <div>
                    <span class="text-slate-400 block mb-1">Infrastruktur:</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->provider_label }}</p>
                </div>

                <div>
                    <span class="text-slate-400 block mb-1">Aktif Sejak:</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Access & SSH with Secure Password Challenge Reveal -->
    <div x-show="activeTab === 'access'" class="space-y-6" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Kredensial Akses SSH</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Password disimpan terenkripsi (AES-256) dan hanya dibuka saat Anda menekan ikon mata.</p>
                </div>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                    Port {{ $vps->ssh_port ?: 22 }} (SSH)
                </span>
            </div>

            <div class="space-y-3 text-xs divide-y divide-slate-100 pt-2">
                <div class="flex justify-between items-center py-2">
                    <span class="text-slate-500 font-medium">Dedicated Public IPv4:</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono-code font-bold text-slate-900 text-sm">{{ $vps->public_ip ?? '127.0.0.1' }}</span>
                        <button @click="copyText('{{ $vps->public_ip }}')" class="p-1 rounded text-slate-400 hover:text-black hover:bg-slate-100" title="Salin IP">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex justify-between items-center py-2">
                    <span class="text-slate-500 font-medium">Private Subnet IP:</span>
                    <span class="font-mono-code text-slate-900">{{ $vps->private_ip ?? '—' }}</span>
                </div>

                <div class="flex justify-between items-center py-2">
                    <span class="text-slate-500 font-medium">Username SSH:</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono-code text-slate-900 font-bold">{{ $vps->ssh_user }}</span>
                        <button @click="copyText(@js($vps->ssh_user))" class="p-1 rounded text-slate-400 hover:text-black hover:bg-slate-100" title="Salin username">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Password SSH: ikon mata (tampil/sembunyi) -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 gap-2">
                    <div>
                        <span class="text-slate-500 font-medium block">Password SSH Awal:</span>
                        <span class="text-[11px] text-slate-400 block">Klik ikon mata untuk menampilkan atau menyembunyikan.</span>
                        <span class="text-[11px] text-slate-400 block">Jika Anda sudah menggantinya lewat <code class="font-mono-code">passwd</code>, password ini tidak berlaku lagi.</span>
                    </div>

                    <div class="flex flex-col items-start sm:items-end gap-1">
                        <div class="flex items-center gap-2">
                            <span x-show="!showPassword" class="font-mono-code text-slate-400 font-bold tracking-widest text-sm bg-slate-50 px-3 py-1.5 rounded border border-slate-200">
                                ••••••••••••••••
                            </span>
                            <span x-show="showPassword" style="display: none;" class="font-mono-code font-bold text-sm bg-emerald-50 text-emerald-900 px-3 py-1.5 rounded border border-emerald-200 select-all break-all" x-text="revealedPassword"></span>

                            <button type="button" @click="togglePassword()" :disabled="passwordLoading"
                                    :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    class="p-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 disabled:opacity-50 transition-colors">
                                <svg x-show="passwordLoading" style="display: none;" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <svg x-show="!passwordLoading && !showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="!passwordLoading && showPassword" style="display: none;" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>

                            <button type="button" x-show="showPassword" style="display: none;" @click="copySecret(revealedPassword)" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-900">
                                Salin
                            </button>
                        </div>
                        <span x-show="passwordError" style="display: none;" class="text-[11px] text-red-600 font-medium" x-text="passwordError"></span>
                        <span x-show="showPassword && revealedAt" style="display: none;" class="text-[11px] text-slate-400" x-text="'Terakhir dibuka: ' + revealedAt"></span>
                    </div>
                </div>

                <div class="pt-4">
                    <span class="text-slate-600 block mb-2 font-medium">Perintah Terminal Cepat:</span>
                    <div class="flex items-center justify-between p-3.5 rounded-lg bg-black text-white font-mono-code text-xs">
                        <span>{{ $vps->ssh_command }}</span>
                        <button @click="copyText('{{ $vps->ssh_command }}')" class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-white font-sans text-xs">
                            Salin Perintah
                        </button>
                    </div>
                </div>

                @if($vps->isDatabasePackage())
                    <div class="mt-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Panduan Remote Database Client</h4>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Server ini khusus untuk database dan terpisah dari server aplikasi Anda. Anda dapat menghubungkan aplikasi backend (Vercel, Railway, VPS luar) atau remote client (DBeaver, TablePlus, DataGrip, Navicat, psql, mysql client) via Dedicated IP Publik Static server:
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div class="bg-white p-2.5 rounded border border-emerald-100">
                                <span class="text-slate-400 block text-[10px]">PostgreSQL</span>
                                <span class="font-mono-code font-bold text-slate-800">Port 5432</span>
                            </div>
                            <div class="bg-white p-2.5 rounded border border-emerald-100">
                                <span class="text-slate-400 block text-[10px]">MySQL / MariaDB</span>
                                <span class="font-mono-code font-bold text-slate-800">Port 3306</span>
                            </div>
                            <div class="bg-white p-2.5 rounded border border-emerald-100">
                                <span class="text-slate-400 block text-[10px]">Redis Cache</span>
                                <span class="font-mono-code font-bold text-slate-800">Port 6379</span>
                            </div>
                            <div class="bg-white p-2.5 rounded border border-emerald-100">
                                <span class="text-slate-400 block text-[10px]">Vector Qdrant</span>
                                <span class="font-mono-code font-bold text-slate-800">Port 6333</span>
                            </div>
                        </div>
                        <div class="bg-white p-3 rounded border border-emerald-100 space-y-1.5 text-xs">
                            <span class="text-slate-600 font-semibold block text-[11px]">Contoh Connection String (.env):</span>
                            <div class="p-2 rounded bg-slate-900 text-slate-100 font-mono-code text-[11px] overflow-x-auto">
                                DATABASE_URL="postgresql://username:password&#64;{{ $vps->public_ip }}:5432/database_name"
                            </div>
                            <p class="text-[11px] text-slate-500">
                                Kompatibel langsung dengan Vercel, Supabase client, Laravel, Next.js, FastAPI, Prisma, DBeaver, TablePlus, dan Navicat.
                            </p>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Disarankan menggunakan SSH Tunneling (<code class="bg-white px-1 py-0.5 rounded border border-slate-200 text-slate-800 font-mono-code">ssh -L 5432:localhost:5432 {{ $vps->ssh_user }}&#64;{{ $vps->public_ip }}</code>) untuk keamanan maksimal.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab 3: Subscription Lifecycle & Billing Cycle Card -->
    <div x-show="activeTab === 'subscription'" class="space-y-6" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Siklus Tagihan & Masa Aktif</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Informasi masa berlangganan, grace period, dan perpanjangan otomatis.</p>
                </div>
                <a href="{{ route('dashboard.billing') }}" class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-900 text-xs font-semibold transition-colors">
                    Lihat Riwayat Invoice &rarr;
                </a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
                    <span class="text-xs text-slate-500 block mb-1">Siklus Berlangganan</span>
                    <p class="font-bold text-slate-900 text-sm uppercase">{{ str_replace('_', ' ', $vps->billing_cycle ?? 'monthly') }}</p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        @if(($vps->billing_cycle ?? 'monthly') === 'annual')
                            Diskon 15% diterapkan
                        @elseif(($vps->billing_cycle ?? 'monthly') === 'semi_annual')
                            Diskon 10% diterapkan
                        @elseif(($vps->billing_cycle ?? 'monthly') === 'quarterly')
                            Diskon 5% diterapkan
                        @else
                            Tagihan bulanan standar
                        @endif
                    </p>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
                    <span class="text-xs text-slate-500 block mb-1">Tanggal Mulai Layanan</span>
                    <p class="font-bold text-slate-900 text-sm">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('d M Y') : '-' }}</p>
                    <p class="text-[11px] text-slate-500 mt-1">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('H:i T') : '' }}</p>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
                    <span class="text-xs text-slate-500 block mb-1">Tanggal Jatuh Tempo (Expiry)</span>
                    <p class="font-bold text-slate-900 text-sm {{ $vps->isExpired() ? 'text-red-600' : '' }}">
                        {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        @if($vps->isExpired())
                            <span class="text-red-600 font-semibold">Telah Kadaluarsa</span>
                        @elseif($vps->expires_at)
                            Sisa {{ $vps->days_until_expiry }} hari lagi
                        @endif
                    </p>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
                    <span class="text-xs text-slate-500 block mb-1">Batas Masa Tenggang</span>
                    <p class="font-bold text-slate-900 text-sm">
                        {{ $vps->grace_period_ends_at ? $vps->grace_period_ends_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Masa tenggang: {{ $graceDays }} hari setelah jatuh tempo
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-lg border border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <div>
                        <strong class="text-slate-900 block font-semibold">Perpanjangan Otomatis (Auto-Renewal)</strong>
                        <span class="text-slate-500">Status saat ini: {{ $vps->auto_renew ? 'Aktif (Otomatis ditagih saat jatuh tempo)' : 'Non-aktif (Pemberitahuan manual)' }}</span>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full font-semibold {{ $vps->auto_renew ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-300' }}">
                    {{ $vps->auto_renew ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Tab 4: Control Panel -->
    <div x-show="activeTab === 'panel'" class="space-y-6" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-900 mb-1">Akses Stack Server</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Stack terpasang: <strong class="text-slate-900">{{ $vps->control_panel_label }}</strong>
            </p>

            @if($vps->panel_url)
                <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="text-slate-700 font-medium">URL Dashboard Panel:</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="font-mono-code font-bold text-slate-900 underline truncate">
                                {{ $vps->panel_url }}
                            </a>
                            <button type="button" @click="copyText(@js($vps->panel_url))" class="px-2 py-0.5 rounded border border-slate-300 bg-white hover:bg-slate-100 font-semibold text-slate-900 shrink-0">Salin</button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Buka URL di atas untuk masuk atau membuat akun administrator panel.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="px-5 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold text-xs transition-colors">
                        Buka Control Panel
                    </a>
                    <a href="{{ route('dashboard.support') }}" class="px-5 py-2.5 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-900 font-semibold text-xs transition-colors">
                        Bantuan Setup
                    </a>
                </div>
            @else
                <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                    <p class="font-semibold">Alamat panel belum tersedia</p>
                    <p>Tim kami belum mencantumkan alamat panel untuk server ini. Hubungi support dan kami akan mengirimkannya.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ route('dashboard.support') }}" class="px-5 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold text-xs transition-colors">
                        Minta Alamat Panel
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Tab: Permintaan & Bantuan -->
    <div x-show="activeTab === 'requests'" class="space-y-6" style="display: none;">
        <!-- Apa yang bisa dilakukan sendiri dan apa yang lewat tim -->
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h3 class="text-base font-bold text-slate-900">Butuh Apa?</h3>
            <p class="text-xs text-slate-500 mt-0.5 mb-4">
                Beberapa hal bisa Anda lakukan sendiri dalam hitungan detik. Sisanya dikerjakan tim kami maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}).
            </p>

            <div class="divide-y divide-slate-100 text-xs">
                @if(in_array($vps->status, ['running', 'error'], true))
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                        <div>
                            <p class="font-semibold text-slate-900">Restart server</p>
                            <p class="text-slate-500">Lakukan sendiri lewat SSH, selesai dalam 1-2 menit.</p>
                        </div>
                        <button type="button" @click="rebootGuideModal = true" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 font-semibold text-slate-900 self-start sm:self-auto">Lihat Caranya</button>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">Ganti password SSH</p>
                        <p class="text-slate-500">Lakukan sendiri: login SSH lalu jalankan <code class="font-mono-code">passwd</code>.</p>
                    </div>
                    <button type="button" @click="copyText('passwd')" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 font-semibold text-slate-900 self-start sm:self-auto">Salin Perintah</button>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">Server tidak bisa diakses sama sekali</p>
                        <p class="text-slate-500">SSH tidak merespons atau server hang. Tim akan me-restart dari sisi infrastruktur.</p>
                    </div>
                    @if(isset($openRequests['unreachable']))
                        <a href="{{ route('dashboard.support.show', $openRequests['unreachable']->id) }}" class="px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-800 font-semibold self-start sm:self-auto">Sedang Diproses</a>
                    @elseif($vps->acceptsServiceRequests())
                        <button type="button" @click="unreachableModal = true" class="px-3 py-1.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold self-start sm:self-auto">Laporkan ke Tim</button>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">Install ulang OS (reinstall)</p>
                        <p class="text-slate-500">Seluruh data terhapus. Tim memasang OS baru dan password SSH baru muncul di dashboard.</p>
                    </div>
                    @if(isset($openRequests['reinstall']))
                        <a href="{{ route('dashboard.support.show', $openRequests['reinstall']->id) }}" class="px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-800 font-semibold self-start sm:self-auto">Sedang Diproses</a>
                    @elseif($vps->acceptsServiceRequests())
                        <button type="button" @click="reinstallModal = true" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 font-semibold text-slate-900 self-start sm:self-auto">Ajukan Reinstall</button>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">Pertanyaan atau kendala lain</p>
                        <p class="text-slate-500">Konfigurasi, domain, panel, atau tagihan.</p>
                    </div>
                    <a href="{{ route('dashboard.support') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 font-semibold text-slate-900 self-start sm:self-auto">Buat Tiket Bantuan</a>
                </div>
            </div>
        </div>

        <!-- Riwayat tiket server ini -->
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-base font-bold text-slate-900">Riwayat Permintaan Server Ini</h3>
                <p class="text-xs text-slate-500 mt-0.5">10 tiket terakhir yang terkait dengan server ini.</p>
            </div>

            @php
                $ticketStatusLabels = [
                    'open' => ['Menunggu', 'bg-amber-50 text-amber-800 border-amber-200'],
                    'in_progress' => ['Dikerjakan', 'bg-blue-50 text-blue-800 border-blue-200'],
                    'resolved' => ['Selesai', 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    'closed' => ['Ditutup', 'bg-slate-100 text-slate-700 border-slate-300'],
                ];
            @endphp

            <div class="divide-y divide-slate-100 text-xs">
                @forelse($serverTickets as $serverTicket)
                    @php [$ticketStatusText, $ticketStatusClass] = $ticketStatusLabels[$serverTicket->status] ?? [ucfirst($serverTicket->status), 'bg-slate-100 text-slate-700 border-slate-300']; @endphp
                    <a href="{{ route('dashboard.support.show', $serverTicket->id) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-6 py-3 hover:bg-slate-50 transition-colors">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $serverTicket->subject }}</p>
                            <p class="text-slate-500">
                                #TK-{{ str_pad($serverTicket->id, 4, '0', STR_PAD_LEFT) }} &bull; {{ $serverTicket->type_label }} &bull; {{ $serverTicket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                            </p>
                        </div>
                        <span class="px-2 py-0.5 rounded border text-[11px] font-semibold self-start sm:self-auto {{ $ticketStatusClass }}">{{ $ticketStatusText }}</span>
                    </a>
                @empty
                    <p class="px-6 py-8 text-center text-slate-500">Belum ada permintaan untuk server ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab 5: Audit Trail & Activity Log -->
    <div x-show="activeTab === 'logs'" class="space-y-6" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Audit Trail & Riwayat Aktivitas Server</h3>
                    <p class="text-xs text-slate-500 mt-0.5">25 aktivitas terakhir: permintaan layanan, akses kredensial, dan perubahan status server.</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-300">
                    Immutable Audit Log
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Waktu</th>
                            <th class="py-3 px-4">Aksi</th>
                            <th class="py-3 px-4">Deskripsi</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($vps->activityLogs as $log)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 whitespace-nowrap text-slate-600 font-mono-code">
                                    {{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i:s') }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if(in_array($log->action, ['start', 'provision', 'reinstall_completed']))
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @elseif(in_array($log->action, ['stop', 'terminate']))
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-red-50 text-red-700 border border-red-200 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @elseif(in_array($log->action, ['reboot', 'force_reboot']))
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @elseif(in_array($log->action, ['reinstall_requested', 'unreachable_reported']))
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @elseif($log->action === 'password_reveal')
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded font-mono-code text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-300 uppercase">
                                            {{ $log->action }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-800">
                                    {{ $log->description }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @php
                                        $logStatusLabel = [
                                            'completed' => 'Selesai',
                                            'submitted' => 'Diajukan',
                                            'pending' => 'Menunggu',
                                            'failed' => 'Gagal',
                                        ][$log->status] ?? $log->status;
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ in_array($log->status, ['completed', 'submitted'], true) ? 'bg-slate-100 text-slate-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $logStatusLabel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-slate-500 font-mono-code">
                                    {{ $log->ip_address ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-500">
                                    Belum ada catatan aktivitas untuk instance ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php $serverName = $vps->hostname ?? ('VPS-' . $vps->id); @endphp

    <!-- Modal: Panduan Reboot lewat SSH -->
    <div x-show="rebootGuideModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md w-full shadow-xl max-h-[90vh] overflow-y-auto" @click.away="rebootGuideModal = false">
            <h3 class="text-base font-bold text-slate-900 mb-1">Cara Restart Server</h3>
            <p class="text-xs text-slate-500 mb-4">Anda memegang akses penuh ke server, jadi restart bisa dilakukan sendiri dan langsung berjalan.</p>

            <ol class="space-y-3 text-xs">
                <li>
                    <p class="font-semibold text-slate-900 mb-1">1. Login ke server</p>
                    <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-black text-white font-mono-code">
                        <span class="truncate">{{ $vps->ssh_command }}</span>
                        <button type="button" @click="copyText(@js($vps->ssh_command))" class="px-2 py-0.5 rounded bg-neutral-800 hover:bg-neutral-700 font-sans shrink-0">Salin</button>
                    </div>
                </li>
                <li>
                    <p class="font-semibold text-slate-900 mb-1">2. Jalankan perintah restart</p>
                    <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-black text-white font-mono-code">
                        <span>sudo reboot</span>
                        <button type="button" @click="copyText('sudo reboot')" class="px-2 py-0.5 rounded bg-neutral-800 hover:bg-neutral-700 font-sans shrink-0">Salin</button>
                    </div>
                </li>
                <li>
                    <p class="font-semibold text-slate-900 mb-1">3. Tunggu 1-2 menit, lalu login kembali</p>
                    <p class="text-slate-500">Koneksi SSH akan terputus saat server restart. Itu normal.</p>
                </li>
            </ol>

            <div class="mt-5 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                <p class="font-semibold text-slate-900">Tidak bisa login SSH sama sekali?</p>
                <p class="text-slate-500 mt-0.5">Laporkan ke tim. Kami akan me-restart server dari sisi infrastruktur.</p>
                @if(isset($openRequests['unreachable']))
                    <a href="{{ route('dashboard.support.show', $openRequests['unreachable']->id) }}" class="inline-block mt-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-800 font-semibold">
                        Laporan Anda sedang diproses &rarr;
                    </a>
                @elseif($vps->acceptsServiceRequests())
                    <button type="button" @click="rebootGuideModal = false; unreachableModal = true" class="mt-2 px-3 py-1.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-semibold">
                        Laporkan Server Tidak Bisa Diakses
                    </button>
                @endif
            </div>

            <div class="pt-4 flex justify-end">
                <button type="button" @click="rebootGuideModal = false" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium text-xs">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    @if($vps->acceptsServiceRequests() && !isset($openRequests['unreachable']))
        <!-- Modal: Laporkan server tidak bisa diakses -->
        <div x-show="unreachableModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
            <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md w-full shadow-xl" @click.away="unreachableModal = false">
                <h3 class="text-base font-bold text-slate-900 mb-1">Laporkan Server Tidak Bisa Diakses</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Tim akan mengecek dan me-restart <strong class="text-slate-900">{{ $serverName }}</strong> dari sisi infrastruktur, maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}).
                </p>

                <form action="{{ route('dashboard.vps.report-unreachable', $vps->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Keterangan (opsional)</label>
                        <textarea name="description" rows="3" maxlength="1000" placeholder="Contoh: SSH timeout sejak jam 10 pagi, website juga tidak bisa dibuka."
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="unreachableModal = false" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold">
                            Kirim Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($vps->acceptsServiceRequests() && !isset($openRequests['reinstall']))
        @php
            $reinstallOsOptions = $vps->reinstallOsOptions();
            $reinstallStackOptions = $vps->reinstallStackOptions();
            $selectedOs = old('os', array_key_exists((string) $vps->os, $reinstallOsOptions) ? $vps->os : array_key_first($reinstallOsOptions));
            $selectedStack = old('control_panel', $vps->control_panel);
        @endphp
        <!-- Modal: Ajukan Reinstall OS -->
        <div x-show="reinstallModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
            <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md w-full shadow-xl max-h-[90vh] overflow-y-auto" @click.away="reinstallModal = false">
                <h3 class="text-base font-bold text-slate-900 mb-1">Ajukan Reinstall OS</h3>
                <p class="text-xs text-slate-500 mb-3">
                    Reinstall dikerjakan tim kami, maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}). Server tetap berjalan seperti biasa sampai tim mulai mengerjakan.
                </p>
                <p class="text-xs text-red-800 bg-red-50 p-3 rounded-lg border border-red-200 mb-4 font-medium">
                    Seluruh data di server akan terhapus permanen. Backup data Anda sebelum mengajukan.
                </p>

                <form action="{{ route('dashboard.vps.request-reinstall', $vps->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Sistem Operasi Baru</label>
                        <select name="os" required class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                            @foreach($reinstallOsOptions as $osKey => $osName)
                                <option value="{{ $osKey }}" @selected((string) $selectedOs === (string) $osKey)>{{ $osName }}</option>
                            @endforeach
                        </select>
                        @error('os')
                            <p class="mt-1 text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Stack / Control Panel</label>
                        @if($vps->hasFixedStack())
                            <p class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-700">
                                {{ $vps->control_panel_label }} (bawaan paket, dipasang ulang)
                            </p>
                        @else
                            <select name="control_panel" required class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                                @foreach($reinstallStackOptions as $stackKey => $stackName)
                                    <option value="{{ $stackKey }}" @selected((string) $selectedStack === (string) $stackKey)>{{ $stackName }}</option>
                                @endforeach
                            </select>
                            @error('control_panel')
                                <p class="mt-1 text-red-600">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Catatan untuk tim (opsional)</label>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Contoh: mohon aktifkan swap 2 GB setelah install."
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">
                            Ketik <span class="font-mono-code font-bold text-slate-900">{{ $serverName }}</span> untuk konfirmasi
                        </label>
                        <input type="text" name="confirm_hostname" required autocomplete="off" value="{{ old('confirm_hostname') }}"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-black bg-white font-mono-code">
                        @error('confirm_hostname')
                            <p class="mt-1 text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-[11px] text-slate-500">
                        Setelah selesai, password SSH baru tampil di tab Akses dan Anda menerima email pemberitahuan.
                    </p>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="reinstallModal = false" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold">
                            Kirim Permintaan Reinstall
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Perpanjang Layanan (Pilar 1, 2, 4) --}}
    @if($vps->canBeRenewed())
        <div x-show="renewalModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 overflow-y-auto"
             @keydown.escape.window="renewalModal = false">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8 text-slate-800"
                 @click.away="renewalModal = false">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Perpanjang Layanan VPS</h3>
                            <p class="text-xs text-slate-500 font-mono-code">{{ $vps->hostname }} ({{ $vps->public_ip ?? 'IP Pending' }})</p>
                        </div>
                    </div>
                    <button type="button" @click="renewalModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Banner Fallback / EOL Replacement jika ada --}}
                @if($vps->isEolWithReplacement())
                    <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed">
                        <div class="flex items-center gap-2 font-bold text-amber-950 mb-1">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Penyesuaian Skema Paket (End-of-Life)
                        </div>
                        Paket lama Anda sudah tidak lagi diproduksi oleh penyedia infrastruktur. Perpanjangan masa aktif untuk server ini dialihkan ke skema paket pengganti <strong>{{ $vps->eol_replacement_spec->name }}</strong> tanpa mengubah IP, data, atau konfigurasi di dalam server Anda.
                    </div>
                @endif

                {{-- Ringkasan Periode & Durasi --}}
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2.5 text-xs">
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Paket Layanan:</span>
                        <span class="font-bold text-slate-900">{{ $vps->renewal_spec->name ?? $vps->package_name ?? 'Cloud VPS' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Masa Aktif Saat Ini:</span>
                        <span class="font-semibold text-slate-800">
                            {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : 'Belum ditentukan' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Perpanjangan Ditambahkan:</span>
                        <span class="font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            +1 Siklus ({{ ucfirst(str_replace('_', ' ', $vps->billing_cycle ?? 'monthly')) }})
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600 pt-1 border-t border-slate-200">
                        <span>Masa Aktif Baru Menjadi:</span>
                        <span class="font-bold text-slate-900">
                            {{ ($vps->expires_at && $vps->expires_at->isFuture() ? $vps->expires_at->copy()->addMonth() : now()->addMonth())->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' }}
                        </span>
                    </div>
                    @if($vps->expires_at && $vps->expires_at->isFuture())
                        <p class="text-[11px] text-slate-500 italic pt-0.5">
                            * Sisa masa aktif Anda tetap utuh karena durasi perpanjangan diakumulasikan dari tanggal kedaluwarsa saat ini.
                        </p>
                    @endif
                </div>

                {{-- Rincian Biaya --}}
                <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-2">
                    <div class="flex justify-between items-baseline">
                        <span class="text-xs font-semibold text-slate-700">Total Biaya Perpanjangan:</span>
                        <div class="text-right">
                            <span class="text-lg font-bold text-slate-900">Rp {{ number_format($vps->renewal_price, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @if($vps->custom_renewal_price)
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-blue-50 text-blue-800 text-[11px] font-semibold rounded border border-blue-200">
                            <span>Harga Khusus Kontrak / Pelanggan Loyal</span>
                        </div>
                    @elseif($vps->order && $vps->order->amount != $vps->renewal_price)
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-slate-100 text-slate-700 text-[11px] font-semibold rounded border border-slate-300">
                            <span>Penyesuaian tarif infrastruktur katalog terkini</span>
                        </div>
                    @endif
                </div>

                {{-- Form Checkout Perpanjangan --}}
                <form action="{{ route('dashboard.vps.renew', $vps->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                        <div class="space-y-2">
                            <label class="flex items-center justify-between p-3 border border-slate-300 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors has-checked:border-slate-900 has-checked:bg-slate-50">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" value="midtrans_snap" checked class="text-black focus:ring-black">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Pembayaran Online Otomatis</div>
                                        <div class="text-[11px] text-slate-500">QRIS, Virtual Account (BCA/Mandiri/BRI/BNI), E-Wallet</div>
                                    </div>
                                </div>
                                <span class="text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">Instan</span>
                            </label>

                            @php
                                $userBalance = auth()->user()->credit_balance ?? 0;
                                $canPayWithBalance = $userBalance >= $vps->renewal_price;
                            @endphp
                            <label class="flex items-center justify-between p-3 border rounded-xl transition-colors {{ $canPayWithBalance ? 'border-slate-300 cursor-pointer hover:bg-slate-50 has-checked:border-slate-900 has-checked:bg-slate-50' : 'border-slate-200 bg-slate-50/50 opacity-60 cursor-not-allowed' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" value="credit" {{ $canPayWithBalance ? '' : 'disabled' }} class="text-black focus:ring-black">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Potong Saldo Akun</div>
                                        <div class="text-[11px] text-slate-500">Saldo saat ini: Rp {{ number_format($userBalance, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                                @if(!$canPayWithBalance)
                                    <span class="text-[11px] font-medium text-red-600">Saldo Tidak Cukup</span>
                                @else
                                    <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">Tersedia</span>
                                @endif
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="renewalModal = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-sm flex items-center gap-2">
                            <span>Lanjutkan Pembayaran</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
