@extends('layouts.admin', ['title' => 'Proses Order Shopee', 'headerTitle' => 'Proses Order Marketplace Shopee', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    smartPasteOpen: false,
    smartPasteText: '',
    shopeeOrderId: '{{ old('shopee_order_id', '') }}',
    customerName: '{{ old('customer_name', '') }}',
    customerEmail: '{{ old('customer_email', '') }}',
    customerPhone: '{{ old('customer_phone', '') }}',
    vpsSpecId: '{{ old('vps_spec_id', $specs->first()?->id ?? '') }}',
    provider: '{{ old('provider', '') }}',
    datacenterLocation: '{{ old('datacenter_location', 'singapore') }}',
    os: '{{ old('os', 'ubuntu2404') }}',
    controlPanel: '{{ old('control_panel', 'none') }}',
    hostname: '{{ old('hostname', '') }}',
    dbEngine: '{{ old('db_engine', 'postgres') }}',
    dbManager: '{{ old('db_manager', 'cloudbeaver') }}',
    autoProvision: false,
    publicIp: '{{ old('public_ip', '') }}',
    sshPort: '{{ old('ssh_port', '22') }}',
    rootPassword: '{{ old('root_password', '') }}',
    activeMsgTab: 'ready',
    specs: @js($specs),
    operatingSystems: @js($operatingSystems),

    // Modals
    copyModalOpen: false,
    copyModalData: null,
    copyModalTab: 'ready',
    provisionModalOpen: false,
    provisionModalData: null,
    provPublicIp: '',
    provHostname: '',
    provOs: 'ubuntu2404',
    provSshPort: 22,
    provRootPassword: '',
    provAppUrl: '',

    init() {
        this.syncSpecSettings();
    },

    syncSpecSettings() {
        const spec = this.specs.find(s => String(s.id) === String(this.vpsSpecId));
        if (!spec) return;

        const allowed = spec.allowed_providers || ['tencent'];
        if (!this.provider || !allowed.includes(this.provider)) {
            this.provider = allowed[0];
        }

        if (this.provider === 'cloudeka') {
            this.datacenterLocation = 'indonesia';
        }

        const providerOsList = this.operatingSystems[this.provider] || {};
        if (!providerOsList[this.os]) {
            this.os = Object.keys(providerOsList)[0] || 'ubuntu2404';
        }

        if (spec.is_database_package) {
            this.controlPanel = 'managed_database';
        } else if (spec.is_ai_package && spec.default_stack) {
            this.controlPanel = spec.default_stack;
        }
    },

    onProviderChange() {
        if (this.provider === 'cloudeka') {
            this.datacenterLocation = 'indonesia';
        }
        const providerOsList = this.operatingSystems[this.provider] || {};
        if (!providerOsList[this.os]) {
            this.os = Object.keys(providerOsList)[0] || 'ubuntu2404';
        }
    },

    isDatabasePackage() {
        const spec = this.specs.find(s => String(s.id) === String(this.vpsSpecId));
        return (spec && spec.is_database_package) || this.controlPanel === 'managed_database';
    },

    isAiPackage() {
        const spec = this.specs.find(s => String(s.id) === String(this.vpsSpecId));
        return spec && spec.is_ai_package;
    },

    allowedProvidersForCurrentSpec() {
        const spec = this.specs.find(s => String(s.id) === String(this.vpsSpecId));
        return spec ? (spec.allowed_providers || ['tencent']) : ['tencent'];
    },

    parseSmartText() {
        const text = this.smartPasteText;
        if (!text || !text.trim()) {
            showAlert('Silakan tempel teks pesanan atau chat Shopee terlebih dahulu.', { icon: 'warning', title: 'Teks Masih Kosong' });
            return;
        }

        let extractedCount = 0;

        // Shopee Order ID
        const orderIdMatch = text.match(/(?:no\.?\s*pesanan|nomor\s*pesanan|order\s*id|pesanan|id)[:#\s]*([0-9a-zA-Z_-]{10,32})/i)
            || text.match(/\b(2[0-9]{13,16}[a-zA-Z0-9]*)\b/)
            || text.match(/\b([0-9]{14,18})\b/);
        if (orderIdMatch) {
            this.shopeeOrderId = orderIdMatch[1].trim();
            extractedCount++;
        }

        // Email
        const emailMatch = text.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
        if (emailMatch) {
            this.customerEmail = emailMatch[1].trim().toLowerCase();
            extractedCount++;
        }

        // Phone / WhatsApp
        const phoneMatch = text.match(/(?:\+62|62|0)8[1-9][0-9]{7,11}\b/);
        if (phoneMatch) {
            let p = phoneMatch[0].trim();
            if (p.startsWith('0')) p = '62' + p.substring(1);
            if (p.startsWith('+')) p = p.substring(1);
            this.customerPhone = p;
            extractedCount++;
        }

        // Customer Name
        const nameMatch = text.match(/(?:nama|nama\s*pembeli|nama\s*akun|atas\s*nama|user)[:#\s]*([a-zA-Z\s]{3,35})(?:\r?\n|$|,)/i);
        if (nameMatch) {
            const rawName = nameMatch[1].trim();
            if (!rawName.toLowerCase().includes('shopee') && !rawName.toLowerCase().includes('pesanan')) {
                this.customerName = rawName;
                extractedCount++;
            }
        }

        // OS
        const lowerText = text.toLowerCase();
        if (lowerText.includes('windows 2022') || lowerText.includes('win 2022') || lowerText.includes('windows server 2022')) {
            this.os = 'windows2022';
            extractedCount++;
        } else if (lowerText.includes('windows 2019') || lowerText.includes('win 2019')) {
            this.os = 'windows2019';
            extractedCount++;
        } else if (lowerText.includes('windows 2016') || lowerText.includes('win 2016')) {
            this.os = 'windows2016';
            extractedCount++;
        } else if (lowerText.includes('windows') || lowerText.includes('rdp')) {
            this.os = 'windows2022';
            extractedCount++;
        } else if (lowerText.includes('ubuntu 24') || lowerText.includes('ubuntu 24.04')) {
            this.os = 'ubuntu2404';
            extractedCount++;
        } else if (lowerText.includes('ubuntu 22') || lowerText.includes('ubuntu 22.04')) {
            this.os = 'ubuntu2204';
            extractedCount++;
        } else if (lowerText.includes('debian 12')) {
            this.os = 'debian12';
            extractedCount++;
        } else if (lowerText.includes('debian 11')) {
            this.os = 'debian11';
            extractedCount++;
        } else if (lowerText.includes('debian')) {
            this.os = 'debian12';
            extractedCount++;
        } else if (lowerText.includes('rocky')) {
            this.os = 'rocky94';
            extractedCount++;
        } else if (lowerText.includes('centos 7')) {
            this.os = 'centos76';
            extractedCount++;
        } else if (lowerText.includes('centos')) {
            this.os = 'centos_stream9';
            extractedCount++;
        }

        // Control Panel / Stack
        if (lowerText.includes('coolify')) {
            this.controlPanel = 'coolify';
        } else if (lowerText.includes('dokploy')) {
            this.controlPanel = 'dokploy';
        } else if (lowerText.includes('aapanel')) {
            this.controlPanel = 'aapanel';
        } else if (lowerText.includes('cloudpanel')) {
            this.controlPanel = 'cloudpanel';
        } else if (lowerText.includes('docker')) {
            this.controlPanel = 'docker';
        } else if (lowerText.includes('cyberpanel')) {
            this.controlPanel = 'cyberpanel';
        } else if (lowerText.includes('hestiacp')) {
            this.controlPanel = 'hestiacp';
        } else if (lowerText.includes('wordpress') || lowerText.includes('wp')) {
            this.controlPanel = 'wordpress';
        } else if (lowerText.includes('n8n')) {
            this.controlPanel = 'n8n';
        } else if (lowerText.includes('ollama')) {
            this.controlPanel = 'ollama';
        } else if (lowerText.includes('dify')) {
            this.controlPanel = 'dify_ollama';
        }

        // Spec Match
        for (const s of this.specs) {
            const sName = s.name.toLowerCase();
            if (lowerText.includes(sName)) {
                this.vpsSpecId = s.id;
                this.syncSpecSettings();
                extractedCount++;
                break;
            }
        }

        if (extractedCount > 0) {
            showAlert('Data berhasil diekstrak dan diisikan ke formulir.', { icon: 'success', title: 'Ekstraksi Selesai' });
        } else {
            showAlert('Tidak ditemukan data spesifik dari teks yang dimasukkan.', { icon: 'warning', title: 'Data Tidak Ditemukan' });
        }
    },

    copyText(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        navigator.clipboard.writeText(el.innerText || el.textContent);
        showAlert('Template pesan berhasil disalin ke clipboard.', { icon: 'success', title: 'Berhasil Disalin' });
    },

    openCopyModal(data) {
        this.copyModalData = data;
        this.copyModalTab = data.has_instance ? 'ready' : 'queued';
        this.copyModalOpen = true;
    },

    openProvisionModal(data) {
        this.provisionModalData = data;
        this.provPublicIp = '';
        this.provHostname = data.hostname || ('vps-' + data.username);
        this.provOs = data.os_key || 'ubuntu2404';
        this.provSshPort = 22;
        this.provRootPassword = '';
        this.provAppUrl = '';
        this.provisionModalOpen = true;
    }
}">

    <!-- Header Card -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 53 61" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M26.372522 5.4266937c4.902289 0 8.898217 4.6522033 9.085166 10.4757833H17.287569c.186949-5.82358 4.182877-10.4757833 9.084953-10.4757833M51.743379 16.997353c0-.604706-.487007-1.094876-1.087549-1.094876H38.87847c-.28896-7.6892751-5.777492-13.8205815-12.505948-13.8205815-6.728244 0-12.216776 6.1313064-12.505736 13.8205815l-11.7942195.000215c-.5913649.01075-1.0674873.496831-1.0674873 1.094661 0 .02858.00107.05695.0032.08488h-.008323l1.6812616 37.061399c.0002134.103148.00405.207156.011738.311809.00171.02364.00363.04706.00555.07048l.00363.07822.00405.0041c.2554543 2.578923 2.1270784 4.656071 4.6720177 4.751913l.00576.0056H44.796175c.01771.000215.03543.00043.05314.00043.01771 0 .03543-.000215.05314-.00043h.0796l.0017-.0015c2.589329-.0707 4.686743-2.176859 4.908265-4.787585l.0013-.0013.0017-.03503c.0021-.02751.0041-.0548.0058-.0823.0041-.06576.0068-.1313.0079-.196412l1.83449-37.207738h-.0013c.0011-.0187.0015-.03761.0015-.05652" fill="#EE4D2D" fill-rule="evenodd"/>
                        <path d="M35.67174 44.953764c-.333349 2.751051-2.000311 4.954341-4.582384 6.057598-1.437971.614592-3.36871.946386-4.896954.842163-2.384027-.09111-4.623787-.670894-6.688335-1.730742-.737553-.378855-1.837052-1.135276-2.68131-1.843776-.213839-.179005-.239235-.293758-.09774-.494467.0764-.115182.217254-.322983.528622-.779199.45158-.661654.507921-.744602.558713-.822178.14448-.221769.379233-.241109.610785-.05888.02433.01891.02433.01891.04268.03331.03799.02944.03799.02944.12762.09907.0907.0707.14448.112389.166248.12872 2.226529 1.743851 4.819699 2.749547 7.437625 2.850117 3.642304-.04964 6.261511-1.687335 6.730804-4.202004.516031-2.767598-1.656504-5.158274-5.907033-6.490821-1.329344-.416676-4.689518-1.761687-5.309053-2.12507-2.909447-1.707104-4.269736-3.943058-4.076384-6.704854.296216-3.828306 3.850167-6.683579 8.340785-6.702705 2.008208-.0041 4.012147.413238 5.937338 1.224457.681638.28731 1.898727.949608 2.318936 1.263351.242009.177716.289813.384872.151095.60836-.07747.12958-.205515.335017-.475482.763298l-.003.0047c-.355331.564092-.366428.581714-.447952.713657-.140852.214463-.306459.234448-.56042.07328-2.060067-1.383907-4.34379-2.080157-6.855437-2.130442-3.126914.06189-5.470605 1.922856-5.624689 4.45794-.04097 2.289677 1.676352 3.961324 5.385881 5.23585 7.529819 2.419687 10.411309 5.25648 9.869029 9.729248" fill="#FFFFFF"/>
                    </svg>
                    <span>Shopee Engine</span>
                </span>
                <span class="text-xs text-slate-500">Versi 2.0 Multi-Provider</span>
            </div>
            <h2 class="text-base font-bold text-slate-900">Proses Pesanan Shopee dan Kelola Kredensial</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Input data pembeli marketplace Shopee. Sistem membuat akun <code class="font-mono-code font-bold text-slate-900">vx_xxxxxxxx</code>, order lunas, faktur, dan template pesan resmi.
            </p>
        </div>
        <div class="text-right shrink-0">
            <span class="text-xs text-slate-400 block">Akun Toko</span>
            <span class="text-xs font-bold text-slate-900">VexaHost Official Store</span>
        </div>
    </div>

    <!-- Credentials Card on Successful Order (Multi-Template) -->
    @if(session('shopee_credentials'))
        @php
            $c = session('shopee_credentials');
            $cleanPhone = !empty($c['customer_phone']) ? preg_replace('/[^0-9]/', '', $c['customer_phone']) : null;
            if ($cleanPhone && str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            }
        @endphp
        <div class="bg-slate-50 border border-slate-300 rounded-lg p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Pesanan Berhasil Diproses & Kredensial Dibuat</h3>
                    <p class="text-xs text-slate-600 mt-0.5">Pilih template pesan di bawah, salin, dan kirimkan ke pembeli di Shopee atau WhatsApp.</p>
                </div>

                <div class="flex items-center gap-2">
                    @if($cleanPhone)
                        <a :href="'https://wa.me/{{ $cleanPhone }}?text=' + encodeURIComponent(document.getElementById('tmpl-' + activeMsgTab).innerText)"
                           target="_blank"
                           class="px-3.5 py-1.5 border border-slate-300 bg-white hover:bg-slate-100 text-slate-900 font-bold text-xs rounded-lg transition-colors flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span>Kirim via WhatsApp</span>
                        </a>
                    @endif

                    <button @click="copyText('tmpl-' + activeMsgTab)" class="px-3.5 py-1.5 bg-black hover:bg-neutral-800 text-white font-bold text-xs rounded-lg transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Salin Template Aktif</span>
                    </button>
                </div>
            </div>

            <!-- Tab Headers -->
            <div class="flex items-center gap-1 border-b border-slate-200 text-xs">
                <button @click="activeMsgTab = 'ready'"
                        :class="activeMsgTab === 'ready' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-3 py-2 transition-colors">
                    Template 1: Kredensial Siap Pakai
                </button>
                <button @click="activeMsgTab = 'queued'"
                        :class="activeMsgTab === 'queued' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-3 py-2 transition-colors">
                    Template 2: Server Dalam Antrean
                </button>
                <button @click="activeMsgTab = 'review'"
                        :class="activeMsgTab === 'review' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-3 py-2 transition-colors">
                    Template 3: Konfirmasi & Ulasan
                </button>
            </div>

            <!-- Tab 1: Kredensial Siap Pakai -->
            <div x-show="activeMsgTab === 'ready'" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="tmpl-ready">Halo Kak {{ $c['customer_name'] }}!
Terima kasih telah memesan Cloud VPS di VexaHost (No. Pesanan: {{ $c['shopee_order_id'] }}).

Berikut detail akun dan akses dashboard VPS Anda:
--------------------------------------------------
URL Dashboard : {{ $c['login_url'] }}
Username      : {{ $c['username'] }}
Password      : {{ $c['password'] }}
Paket VPS     : {{ $c['package'] }} ({{ $c['spec_specs'] }})
Provider / DC : {{ $c['provider'] }} / {{ $c['datacenter'] }}
Sistem Operasi: {{ $c['os'] }}
Control Panel : {{ $c['control_panel'] }}
@if(!empty($c['is_provisioned']))
Hostname      : {{ $c['hostname'] }}
IP Publik     : {{ $c['public_ip'] }}
Port Akses    : {{ $c['ssh_port'] }}
User SSH      : {{ $c['ssh_username'] ?? 'root' }}
Password SSH  : {{ $c['root_password'] }}
@if(!empty($c['app_url']))
Akses Panel   : {{ $c['app_url'] }}
@endif
@if(!empty($c['db_engine']))
Database      : {{ $c['db_engine'] }} (Port {{ $c['ssh_port'] }})
@endif
@endif
--------------------------------------------------

Silakan login ke dashboard untuk mengecek status dan mengelola server Anda.
Jika butuh panduan setup atau konfigurasi, tim kami siap membantu melalui tiket support di dashboard.

Salam hangat,
Tim VexaHost Cloud</div>

            <!-- Tab 2: Server Dalam Antrean -->
            <div x-show="activeMsgTab === 'queued'" style="display: none;" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="tmpl-queued">Halo Kak {{ $c['customer_name'] }}!
Terima kasih telah memesan Cloud VPS di VexaHost (No. Pesanan: {{ $c['shopee_order_id'] }}).

Pesanan Anda telah kami terima dan server saat ini sedang dalam proses antrean konfigurasi datacenter (maksimal 1x24 jam, umumnya lebih cepat).

Detail akses akun dashboard Anda:
--------------------------------------------------
URL Dashboard : {{ $c['login_url'] }}
Username      : {{ $c['username'] }}
Password      : {{ $c['password'] }}
Paket VPS     : {{ $c['package'] }} ({{ $c['spec_specs'] }})
Provider / DC : {{ $c['provider'] }} / {{ $c['datacenter'] }}
Sistem Operasi: {{ $c['os'] }}
--------------------------------------------------

Setelah proses alokasi selesai, IP publik dan kredensial root server akan otomatis tampil di dashboard Anda.
Mohon ditunggu sebentar ya Kak. Terima kasih atas kesabaran Anda.

Salam hangat,
Tim VexaHost Cloud</div>

            <!-- Tab 3: Konfirmasi & Ulasan Bintang 5 -->
            <div x-show="activeMsgTab === 'review'" style="display: none;" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="tmpl-review">Halo Kak {{ $c['customer_name'] }}!
Server Cloud VPS untuk No. Pesanan Shopee {{ $c['shopee_order_id'] }} telah selesai dikonfigurasi dan aktif berjalan normal.

Jika Anda telah selesai memeriksa dan mencoba akses server, kami mohon bantuannya untuk:
1. Membuka aplikasi Shopee dan menekan tombol "Pesanan Diterima".
2. Memberikan ulasan bintang 5 untuk mengaktifkan garansi pergantian dan dukungan bantuan prioritas selama 30 hari penuh.

Jika ada kendala teknis atau pertanyaan, silakan hubungi kami kapan saja melalui tiket bantuan dashboard.
Terima kasih telah mempercayakan kebutuhan server Anda di VexaHost!</div>
        </div>
    @endif

    <!-- Smart Paste / Raw Text Extractor Box -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-3">
        <div class="flex items-center justify-between cursor-pointer" @click="smartPasteOpen = !smartPasteOpen">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Ekstrak Cepat dari Chat Shopee (Smart Paste)</h3>
                <p class="text-xs text-slate-500">Tempelkan teks rincian pesanan dari chat atau catatan pembeli untuk mengisi form secara instan.</p>
            </div>
            <button type="button" class="text-xs font-bold text-slate-700 hover:text-slate-900 border border-slate-300 px-2.5 py-1 rounded">
                <span x-text="smartPasteOpen ? 'Sembunyikan' : 'Buka Kotak Paste'"></span>
            </button>
        </div>

        <div x-show="smartPasteOpen" class="space-y-3 pt-2">
            <textarea x-model="smartPasteText" rows="3" placeholder="Contoh teks chat:&#10;No Pesanan: 240915SHP88123, Email: budi@gmail.com, WA: 08123456789, Paket: Standard, OS: Windows 2022"
                      class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code text-xs focus:outline-none bg-white"></textarea>
            <div class="flex justify-end">
                <button type="button" @click="parseSmartText()" class="px-4 py-2 bg-black hover:bg-neutral-800 text-white font-bold text-xs rounded-lg transition-colors">
                    Ekstrak ke Formulir
                </button>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h3 class="text-base font-bold text-slate-900 mb-4">Form Transaksi Shopee</h3>

        <form action="{{ route('admin.shopee.process') }}" method="POST" class="space-y-5 text-xs">
            @csrf

            <!-- Section 1: Buyer Info -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Shopee Order ID *</label>
                    <input type="text" name="shopee_order_id" required x-model="shopeeOrderId" placeholder="Contoh: 240912SHP88123"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Nama Pembeli *</label>
                    <input type="text" name="customer_name" required x-model="customerName" placeholder="Budi Santoso"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Email Pembeli *</label>
                    <input type="email" name="customer_email" required x-model="customerEmail" placeholder="budi@gmail.com"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">No HP / WA (Opsional)</label>
                    <input type="text" name="customer_phone" x-model="customerPhone" placeholder="087812345678"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>
            </div>

            <!-- Section 2: VPS Specs & Provider -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-100">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Paket VPS *</label>
                    <select name="vps_spec_id" required x-model="vpsSpecId" @change="syncSpecSettings()" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                        @foreach($specs as $spec)
                            <option value="{{ $spec->id }}">
                                {{ $spec->name }} ({{ $spec->cpu }}c/{{ $spec->ram }}G) — Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Provider Cloud *</label>
                    <select name="provider" x-model="provider" @change="onProviderChange()" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white font-medium">
                        <template x-for="p in allowedProvidersForCurrentSpec()" :key="p">
                            <option :value="p" x-text="p === 'cloudeka' ? 'Cloudeka by Lintasarta' : 'Tencent Cloud'"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Lokasi Datacenter *</label>
                    <select name="datacenter_location" required x-model="datacenterLocation" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                        <template x-if="provider === 'tencent'">
                            <option value="singapore">Singapore</option>
                        </template>
                        <option value="indonesia">Indonesia (Cyber Jakarta)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Sistem Operasi *</label>
                    <select name="os" required x-model="os" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white font-mono-code text-[11px]">
                        <template x-for="(label, key) in (operatingSystems[provider] || {})" :key="key">
                            <option :value="key" x-text="label"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Section 2B: Panel & App Stack -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-100">
                <div class="sm:col-span-2">
                    <label class="block font-medium text-slate-700 mb-1">Control Panel / Aplikasi Stack *</label>
                    <select name="control_panel" required x-model="controlPanel" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                        @foreach($stackLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Hostname Kustom (Opsional)</label>
                    <input type="text" name="hostname" x-model="hostname" placeholder="vps-custom-name"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none">
                </div>

                <div class="flex items-end">
                    <template x-if="isAiPackage()">
                        <div class="p-2 bg-slate-50 border border-slate-200 rounded-lg text-[11px] text-slate-600 w-full">
                            <span class="font-bold text-slate-900 block">Paket AI Combo</span>
                            Panduan dan tautan web GUI akan otomatis disertakan pada instance.
                        </div>
                    </template>
                    <template x-if="!isAiPackage() && !isDatabasePackage()">
                        <div class="p-2 text-slate-400 text-[11px] w-full">
                            Paket reguler cloud server.
                        </div>
                    </template>
                </div>
            </div>

            <!-- Conditional Section for Managed Database -->
            <div x-show="isDatabasePackage()" class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-3">
                <div class="font-bold text-slate-900 text-xs">Konfigurasi Managed Database</div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Database Engine *</label>
                        <select name="db_engine" x-model="dbEngine" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                            <option value="postgres">PostgreSQL (Port 5432)</option>
                            <option value="mysql">MySQL (Port 3306)</option>
                            <option value="redis">Redis In-Memory (Port 6379)</option>
                            <option value="mongodb">MongoDB Document (Port 27017)</option>
                            <option value="vector">Vector Qdrant (Port 6333)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Database Manager GUI *</label>
                        <select name="db_manager" x-model="dbManager" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                            <option value="cloudbeaver">CloudBeaver Web GUI (Port 8080)</option>
                            <option value="cli_only">CLI Only (Tanpa Web GUI)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 3: Auto-provision checkbox & IP -->
            <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-3">
                <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-900 text-xs">
                    <input type="checkbox" name="auto_provision" value="1" x-model="autoProvision" class="rounded border-slate-300 text-black focus:ring-black">
                    <span>Langsung alokasikan IP Publik dan aktifkan status Running sekarang</span>
                </label>

                <div x-show="autoProvision" class="grid sm:grid-cols-3 gap-3 pt-2">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Alokasi IP Publik Node *</label>
                        <input type="text" name="public_ip" x-model="publicIp" placeholder="Contoh: 103.150.12.88 atau 139.180.205.50"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Port SSH / RDP (Default 22)</label>
                        <input type="number" name="ssh_port" x-model="sshPort" placeholder="22"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Username SSH (Default root)</label>
                        <input type="text" name="ssh_username" value="{{ old('ssh_username', 'root') }}" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Password SSH Kustom (Opsional)</label>
                        <input type="text" name="root_password" x-model="rootPassword" placeholder="Biarkan kosong untuk auto-generate"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>

                    <div class="sm:col-span-3" x-show="controlPanel !== 'none'">
                        <label class="block font-medium text-slate-700 mb-1">Link Control Panel (Opsional)</label>
                        <input type="text" name="app_url" value="{{ old('app_url') }}" placeholder="http://103.150.12.88:8000 atau https://panel.domain.com"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                        <p class="mt-1 text-slate-400">Link yang tampil di dashboard pelanggan. Bisa diisi nanti di Admin &gt; Cloud Instances.</p>
                    </div>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors">
                    Proses Order Shopee
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Shopee Orders Table -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Riwayat Pesanan Shopee</h3>
                <p class="text-xs text-slate-500">Daftar transaksi marketplace yang telah masuk ke sistem</p>
            </div>

            <!-- Search and Status Filter -->
            <form action="{{ route('admin.shopee') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari Order ID, Nama, IP..."
                       class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono-code focus:outline-none w-48 sm:w-60">

                <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none bg-white">
                    <option value="">Semua Status</option>
                    <option value="needs_provision" {{ request('status') === 'needs_provision' ? 'selected' : '' }}>Menunggu IP (Paid)</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif Running</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                </select>

                <button type="submit" class="px-3 py-1.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors">
                    Filter
                </button>

                @if(request('q') || request('status'))
                    <a href="{{ route('admin.shopee') }}" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">No. Pesanan Shopee</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Paket & Provider</th>
                        <th class="px-5 py-3">IP Node / Hostname</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentShopeeOrders as $so)
                        @php
                            $instance = $so->vpsInstance;
                            $cleanRowPhone = !empty($so->customer->phone) ? preg_replace('/[^0-9]/', '', $so->customer->phone) : '';
                            if ($cleanRowPhone && str_starts_with($cleanRowPhone, '0')) {
                                $cleanRowPhone = '62' . substr($cleanRowPhone, 1);
                            }
                            $rowPayload = [
                                'id' => $so->id,
                                'shopee_order_id' => $so->shopee_order_id,
                                'customer_name' => $so->customer->full_name ?? 'Pelanggan Shopee',
                                'customer_email' => $so->customer->email ?? '',
                                'customer_phone' => $cleanRowPhone,
                                'username' => $so->customer->username ?? '',
                                'package' => $so->vpsSpec->name ?? 'Standard',
                                'provider' => $so->provider_label,
                                'datacenter' => ucfirst($so->datacenter_location),
                                'os' => $so->os_label,
                                'os_key' => $so->os,
                                'control_panel' => $so->control_panel_label,
                                // Sama dengan aturan AdminController::provision: paket ber-panel wajib link panel.
                                'needs_panel_url' => $so->control_panel !== 'none' && !($so->isDatabasePackage() && $so->db_manager === 'cli_only'),
                                'status' => $so->status,
                                'has_instance' => (bool)$instance,
                                'hostname' => $instance?->hostname ?? ($so->hostname ?? ('vps-' . ($so->customer->username ?? 'node'))),
                                'public_ip' => $instance?->public_ip ?? '',
                                'ssh_port' => $instance?->ssh_port ?? 22,
                                'ssh_username' => $instance?->ssh_user ?? 'root',
                                'login_url' => route('login'),
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                {{ $so->shopee_order_id }}
                                <span class="block text-[11px] text-slate-500 font-mono-code font-normal">#ORD-{{ $so->id }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-900 block">{{ $so->customer->full_name }}</span>
                                <span class="text-slate-500 font-mono-code text-[11px]">&#64;{{ $so->customer->username }}</span>
                                @if($so->customer->phone)
                                    <span class="text-slate-400 font-mono-code text-[11px] block">{{ $so->customer->phone }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-medium text-slate-900 block">{{ $so->vpsSpec->name ?? 'Standard' }}</span>
                                <span class="text-slate-500 text-[11px] block">{{ $so->provider_label }} · {{ ucfirst($so->datacenter_location) }}</span>
                                <span class="text-slate-400 font-mono-code text-[10px] block">{{ $so->os_label }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code">
                                @if($instance && $instance->public_ip)
                                    <span class="font-bold text-slate-900 block">{{ $instance->public_ip }}</span>
                                    <span class="text-slate-500 text-[11px] block">{{ $instance->hostname }}</span>
                                @else
                                    <span class="text-slate-400 italic">Belum dialokasikan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if($so->status === 'paid' && !$instance)
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase border border-slate-300 bg-slate-100 text-slate-800">
                                        Menunggu IP
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                                        {{ $so->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 font-mono-code">
                                {{ $so->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(in_array($so->status, ['paid', 'failed']) && !$instance)
                                        <button type="button" @click="openProvisionModal({{ json_encode($rowPayload) }})"
                                                class="px-2.5 py-1.5 rounded bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors">
                                            Provision
                                        </button>
                                    @endif

                                    <button type="button" @click="openCopyModal({{ json_encode($rowPayload) }})"
                                            class="px-2.5 py-1.5 rounded border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-medium text-xs transition-colors">
                                        Salin Pesan
                                    </button>

                                     @if($so->invoice)
                                         <a href="{{ route('dashboard.invoice.print', $so->invoice->id) }}"
                                            @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $so->invoice->id) }}' })"
                                            class="px-2 py-1.5 text-slate-600 hover:text-slate-900 font-medium text-xs underline cursor-pointer">
                                             Faktur
                                         </a>
                                     @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                                Belum ada riwayat pesanan Shopee pada kriteria ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recentShopeeOrders->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $recentShopeeOrders->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Salin Pesan Riwayat (Multi-Template) -->
    <div x-show="copyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-xl w-full space-y-4" @click.away="copyModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Salin Template Pesan Pembeli</h3>
                    <p class="text-xs text-slate-500" x-text="copyModalData ? 'No. Pesanan: ' + copyModalData.shopee_order_id + ' (' + copyModalData.customer_name + ')' : ''"></p>
                </div>
                <button type="button" @click="copyModalOpen = false" class="text-slate-400 hover:text-slate-700 text-sm font-bold">Tutup</button>
            </div>

            <!-- Tab Headers -->
            <div class="flex items-center gap-2 border-b border-slate-200 text-xs">
                <button type="button" @click="copyModalTab = 'ready'"
                        :class="copyModalTab === 'ready' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-2.5 py-1.5 transition-colors">
                    Akses Dashboard & Server
                </button>
                <button type="button" @click="copyModalTab = 'queued'"
                        :class="copyModalTab === 'queued' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-2.5 py-1.5 transition-colors">
                    Server Sedang Disiapkan
                </button>
                <button type="button" @click="copyModalTab = 'review'"
                        :class="copyModalTab === 'review' ? 'border-b-2 border-black font-bold text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                        class="px-2.5 py-1.5 transition-colors">
                    Ulasan & Konfirmasi
                </button>
            </div>

            <!-- Tab Content 1 -->
            <div x-show="copyModalTab === 'ready'" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="modal-tmpl-ready">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Terima kasih telah memesan Cloud VPS di VexaHost (No. Pesanan: <span x-text="copyModalData?.shopee_order_id"></span>).

Berikut detail akun dan akses dashboard VPS Anda:
--------------------------------------------------
URL Dashboard : <span x-text="copyModalData?.login_url"></span>
Username      : <span x-text="copyModalData?.username"></span>
Password      : (Gunakan password akun VexaHost Anda)
Paket VPS     : <span x-text="copyModalData?.package"></span>
Provider / DC : <span x-text="copyModalData?.provider"></span> / <span x-text="copyModalData?.datacenter"></span>
Sistem Operasi: <span x-text="copyModalData?.os"></span>
Control Panel : <span x-text="copyModalData?.control_panel"></span>
Hostname      : <span x-text="copyModalData?.hostname"></span>
IP Publik     : <span x-text="copyModalData?.public_ip || '(Dalam proses alokasi)'"></span>
Port Akses    : <span x-text="copyModalData?.ssh_port || '22'"></span>
User SSH      : <span x-text="copyModalData?.ssh_username || 'root'"></span>
--------------------------------------------------

Silakan login ke dashboard untuk mengecek status dan mengelola server Anda.
Jika butuh panduan setup atau konfigurasi, tim kami siap membantu melalui tiket support di dashboard.

Salam hangat,
Tim VexaHost Cloud</div>

            <!-- Tab Content 2 -->
            <div x-show="copyModalTab === 'queued'" style="display: none;" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="modal-tmpl-queued">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Terima kasih telah memesan Cloud VPS di VexaHost (No. Pesanan: <span x-text="copyModalData?.shopee_order_id"></span>).

Pesanan Anda telah kami terima dan server saat ini sedang dalam proses antrean konfigurasi datacenter (maksimal 1x24 jam, umumnya lebih cepat).

Detail akses akun dashboard Anda:
--------------------------------------------------
URL Dashboard : <span x-text="copyModalData?.login_url"></span>
Username      : <span x-text="copyModalData?.username"></span>
Password      : (Gunakan password akun VexaHost Anda)
Paket VPS     : <span x-text="copyModalData?.package"></span>
Provider / DC : <span x-text="copyModalData?.provider"></span> / <span x-text="copyModalData?.datacenter"></span>
Sistem Operasi: <span x-text="copyModalData?.os"></span>
--------------------------------------------------

Setelah proses alokasi selesai, IP publik dan kredensial root server akan otomatis tampil di dashboard Anda.
Mohon ditunggu sebentar ya Kak. Terima kasih atas kesabaran Anda.

Salam hangat,
Tim VexaHost Cloud</div>

            <!-- Tab Content 3 -->
            <div x-show="copyModalTab === 'review'" style="display: none;" class="bg-black text-white p-4 rounded-lg font-mono-code text-xs leading-relaxed select-all" id="modal-tmpl-review">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Server Cloud VPS untuk No. Pesanan Shopee <span x-text="copyModalData?.shopee_order_id"></span> telah aktif dan berjalan normal.

Jika Anda telah selesai memeriksa dan mencoba akses server, kami mohon bantuannya untuk:
1. Membuka aplikasi Shopee dan menekan tombol "Pesanan Diterima".
2. Memberikan ulasan bintang 5 untuk mengaktifkan garansi pergantian dan dukungan bantuan prioritas selama 30 hari penuh.

Jika ada kendala teknis atau pertanyaan, silakan hubungi kami kapan saja melalui tiket bantuan dashboard.
Terima kasih telah mempercayakan kebutuhan server Anda di VexaHost!</div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-between pt-2">
                <template x-if="copyModalData?.customer_phone">
                    <a :href="'https://wa.me/' + copyModalData.customer_phone + '?text=' + encodeURIComponent(document.getElementById('modal-tmpl-' + copyModalTab).innerText)"
                       target="_blank"
                       class="px-3.5 py-1.5 border border-slate-300 bg-white hover:bg-slate-100 text-slate-900 font-bold text-xs rounded-lg transition-colors flex items-center gap-1.5">
                        <span>Kirim via WhatsApp</span>
                    </a>
                </template>
                <div class="flex-1" x-show="!copyModalData?.customer_phone"></div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="copyText('modal-tmpl-' + copyModalTab)" class="px-4 py-2 bg-black hover:bg-neutral-800 text-white font-bold text-xs rounded-lg transition-colors">
                        Salin Pesan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Provision Cepat -->
    <div x-show="provisionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md w-full" @click.away="provisionModalOpen = false">
            <h3 class="text-base font-bold text-slate-900 mb-1">Provisioning Cepat Server Shopee</h3>
            <p class="text-xs text-slate-500 mb-3">
                Alokasikan IP Publik dan hostname untuk pesanan <strong class="font-mono-code" x-text="provisionModalData ? provisionModalData.shopee_order_id : ''"></strong>.
            </p>

            <div x-show="provisionModalData" class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1">
                <div class="flex justify-between"><span class="text-slate-500">Pelanggan:</span><span class="font-medium text-slate-800" x-text="provisionModalData?.customer_name"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Paket & Provider:</span><span class="font-medium text-slate-800" x-text="(provisionModalData?.package || '') + ' · ' + (provisionModalData?.provider || '')"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Stack:</span><span class="font-medium text-slate-800" x-text="provisionModalData?.control_panel"></span></div>
            </div>

            <form :action="provisionModalData ? '/admin/orders/' + provisionModalData.id + '/provision' : '#'" method="POST" class="space-y-3.5 text-xs">
                @csrf

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Alokasi IP Publik Node *</label>
                    <input type="text" name="public_ip" required x-model="provPublicIp" placeholder="103.150.12.88 atau 139.180.xxx.xxx"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Hostname Server *</label>
                    <input type="text" name="hostname" required x-model="provHostname" placeholder="vps-customer-node"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Sistem Operasi *</label>
                    <input type="text" name="os" required x-model="provOs"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                </div>

                <div x-show="provisionModalData?.needs_panel_url">
                    <label class="block font-medium text-slate-700 mb-1">Link Control Panel *</label>
                    <input type="text" name="app_url" x-model="provAppUrl" :required="!!provisionModalData?.needs_panel_url" :disabled="!provisionModalData?.needs_panel_url"
                           placeholder="http://103.150.12.88:8000 atau https://panel.domain.com"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    <p class="mt-1 text-slate-400">Link ini yang tampil di dashboard pelanggan. Wajib diawali http:// atau https://.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Port SSH/RDP</label>
                        <input type="number" name="ssh_port" x-model="provSshPort" placeholder="22"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Username SSH</label>
                        <input type="text" name="ssh_username" value="root" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Password SSH <span class="text-rose-600">*</span></label>
                    <input type="text" name="root_password" required minlength="8" x-model="provRootPassword" placeholder="Password dari retail"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none bg-white">
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="provisionModalOpen = false" class="px-3.5 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors">
                        Mulai Provisioning
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
