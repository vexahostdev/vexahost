@extends('layouts.admin', ['title' => 'Manajemen Orders', 'headerTitle' => 'Antrean & Manajemen Order', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
@php
    $ordersPayload = $orders->getCollection()->mapWithKeys(function($order) {
        return [$order->id => [
            'id' => $order->id,
            'hostname' => $order->hostname,
            'root_password' => $order->root_password,
            'provider' => $order->provider_label,
            'datacenter' => ucfirst($order->datacenter_location),
            'os' => $order->os,
            'os_label' => $order->os_label,
            'control_panel' => $order->control_panel,
            'stack' => $order->control_panel_label,
            'is_db' => $order->isDatabasePackage(),
            'db_engine' => $order->db_engine,
            'db_manager' => $order->db_manager,
            'is_ai' => $order->isAiPackage(),
            'amount' => number_format($order->amount, 0, ',', '.'),
            'invoice' => $order->invoice->invoice_number ?? ('INV-' . $order->id),
            'customer' => [
                'name' => $order->customer->full_name ?? '-',
                'email' => $order->customer->email ?? '-',
                'phone' => $order->customer->phone ?? '-',
                'username' => $order->customer->username ?? 'user'
            ]
        ]];
    });
@endphp
<script>
    window.ordersPayload = @json($ordersPayload);
</script>
<div class="space-y-6" x-data="{
    provisionModal: false,
    currentOrder: null,
    publicIp: '',
    privateIp: '',
    sshPort: 22,
    rootPassword: '',
    showRootPassword: true,
    appUrl: '',
    ordersData: window.ordersPayload || {},
    openProvision(orderId) {
        this.currentOrder = (this.ordersData && this.ordersData[orderId]) || null;
        this.publicIp = '';
        this.privateIp = '';
        this.sshPort = 22;
        this.rootPassword = '';
        this.showRootPassword = true;
        this.appUrl = '';
        this.provisionModal = true;
    },
    hasPanel() {
        if (!this.currentOrder) return false;
        if (this.currentOrder.is_db) {
            return this.currentOrder.db_manager === 'cloudbeaver';
        }
        return this.currentOrder.control_panel && this.currentOrder.control_panel !== 'none';
    }
}">
    <!-- Top Filter Tabs (No lines under title) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-medium">
            <a href="{{ route('admin.orders') }}" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ !request('status') && !request('channel') ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Semua Order
            </a>

            <a href="{{ route('admin.orders') }}?status=needs_provision" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap flex items-center gap-1.5 {{ request('status') === 'needs_provision' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                <span>Perlu Provisioning</span>
                @php $pendingProvisionCount = $needsProvisionCount ?? \App\Models\Order::whereIn('status', ['paid', 'provisioning'])->whereDoesntHave('vpsInstance')->count(); @endphp
                @if($pendingProvisionCount > 0)
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold {{ request('status') === 'needs_provision' ? 'bg-amber-400 text-black' : 'bg-amber-100 text-amber-900 border border-amber-300' }}">
                        {{ $pendingProvisionCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.orders') }}?status=active" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ request('status') === 'active' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Aktif Running
            </a>

            <a href="{{ route('admin.orders') }}?status=pending" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ request('status') === 'pending' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Menunggu Pembayaran
            </a>

            <a href="{{ route('admin.orders') }}?channel=shopee" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap flex items-center gap-1 {{ request('channel') === 'shopee' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                <span>Shopee Orders</span>
            </a>

            <a href="{{ route('admin.orders') }}?channel=website" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ request('channel') === 'website' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Website Direct
            </a>
        </div>

        <a href="{{ route('admin.shopee') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors self-start sm:self-auto shrink-0">
            <span>+ Order Shopee Baru</span>
        </a>
    </div>

    <!-- Orders Table (No lines under headings) -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">ID Order</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3">Paket & Panel</th>
                        <th class="px-5 py-3">Lokasi & OS</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Nominal</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                #ORD-{{ $order->id }}
                                @if($order->shopee_order_id)
                                    <span class="block text-[11px] text-slate-500 font-mono-code font-normal">{{ $order->shopee_order_id }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-800 flex items-center justify-center font-bold text-xs">
                                        {{ strtoupper(substr($order->customer->full_name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block">{{ $order->customer->full_name ?? 'Pelanggan' }}</span>
                                        <span class="text-slate-500 font-mono-code text-[11px]">&#64;{{ $order->customer->username ?? 'user' }}</span>
                                        @if($order->customer->phone)
                                            <span class="text-[10px] text-slate-400 block font-mono-code">{{ $order->customer->phone }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold border border-slate-300 bg-slate-50 text-slate-900">
                                    {{ ucfirst($order->channel) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-semibold text-slate-900">{{ $order->vpsSpec->name ?? 'Standard' }}</span>
                                    @if($order->isAiPackage())
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                                            AI Combo
                                        </span>
                                    @endif
                                    @if($order->hostname)
                                        <span class="text-[10px] font-mono-code px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold" title="VPS Name">
                                            {{ $order->hostname }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-slate-500 font-medium block">{{ $order->control_panel_label }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">
                                <span class="block font-medium">{{ $order->provider_label }} · {{ ucfirst($order->datacenter_location) }}</span>
                                <span class="text-[11px] text-slate-500">{{ $order->os_label }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($order->status === 'paid')
                                    <span class="px-2.5 py-1 rounded text-[11px] font-bold uppercase border border-emerald-300 bg-emerald-50 text-emerald-800 flex items-center gap-1 w-fit">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Paid · Siap Setup
                                    </span>
                                @elseif($order->status === 'provisioning')
                                    <span class="px-2.5 py-1 rounded text-[11px] font-bold uppercase border border-amber-300 bg-amber-50 text-amber-800 flex items-center gap-1 w-fit">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Provisioning
                                    </span>
                                @elseif($order->status === 'active')
                                    <span class="px-2.5 py-1 rounded text-[11px] font-bold uppercase border border-slate-300 bg-slate-100 text-slate-800 w-fit">
                                        Active
                                    </span>
                                @elseif($order->status === 'pending')
                                    <span class="px-2.5 py-1 rounded text-[11px] font-semibold uppercase border border-amber-200 bg-amber-50 text-amber-700 w-fit">
                                        Menunggu Bayar
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded text-[11px] font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900 w-fit">
                                        {{ $order->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                Rp {{ number_format($order->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(in_array($order->status, ['paid', 'provisioning', 'failed']) && !$order->vpsInstance)
                                        <button type="button"
                                                onclick="openProvisionModal({{ $order->id }})"
                                                @click.stop="openProvision({{ $order->id }})" 
                                                class="px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-2xs flex items-center gap-1 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <span>Setup VPS</span>
                                        </button>
                                        <a href="{{ route('admin.orders.history', $order->id) }}" class="px-2 py-1.5 rounded border border-slate-300 hover:bg-slate-50 text-slate-600 text-xs font-medium" title="Riwayat Order">
                                            Audit
                                        </a>
                                    @elseif($order->status === 'pending')
                                        <form action="{{ route('admin.orders.mark-paid', $order->id) }}" method="POST" data-confirm="Tandai order ini sudah dibayar manual?">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100 text-xs font-semibold" title="Mark Paid Manual">
                                                Mark Paid
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.orders.cancel', $order->id) }}" method="POST" data-confirm="Batalkan order ini?">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs">
                                                Batal
                                            </button>
                                        </form>
                                     @else
                                         @if($order->invoice)
                                             <a href="{{ route('dashboard.invoice.print', $order->invoice->id) }}"
                                                @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $order->invoice->id) }}' })"
                                                class="px-2.5 py-1.5 rounded border border-slate-300 hover:bg-slate-50 text-slate-800 font-medium text-xs cursor-pointer">
                                                 Faktur PDF
                                             </a>
                                         @endif
                                        <a href="{{ route('admin.orders.history', $order->id) }}" class="px-2 py-1.5 rounded border border-slate-300 hover:bg-slate-50 text-slate-600 text-xs font-medium" title="Riwayat Order">
                                            Audit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                Tidak ada data pesanan pada kriteria yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- Provisioning Modal (Hybrid Alpine + Native Fallback) -->
    <div id="adminProvisionModal"
         x-show="provisionModal" 
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" 
         style="display: none;"
         onclick="if(event.target === this) closeProvisionModal()"
         @click.self="provisionModal = false"
         @keydown.escape.window="provisionModal = false">
        <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-xl" 
             @click.stop
             onclick="event.stopPropagation()">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Setup &amp; Aktivasi Server VPS</h3>
                    <p class="text-xs text-slate-500">
                        Input kredensial server untuk order <strong id="modalOrderNumber" class="text-slate-900" x-text="currentOrder ? '#ORD-' + currentOrder.id : ''"></strong>
                    </p>
                </div>
                <button type="button" onclick="closeProvisionModal()" @click="provisionModal = false" class="text-slate-400 hover:text-black text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <!-- Detail Pembeli & Pesanan (Terkunci Sesuai Pesanan Pelanggan) -->
            <div id="modalOrderDetailsCard" x-show="currentOrder" class="mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-2.5">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200/80">
                    <span class="font-bold text-slate-900 uppercase tracking-wider text-[10px]">Spesifikasi &amp; Kredensial Pelanggan</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-slate-700 border border-slate-200">Terkunci</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Server Hostname:</span>
                        <span id="modalHostname" class="font-bold font-mono-code text-slate-900 bg-white px-2 py-1 rounded border border-slate-200 block truncate" x-text="currentOrder?.hostname || '-'"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Distribusi OS:</span>
                        <span id="modalOs" class="font-semibold text-slate-900 bg-white px-2 py-1 rounded border border-slate-200 block truncate" x-text="currentOrder?.os_label || currentOrder?.os || '-'"></span>
                    </div>
                </div>

                <div class="p-2.5 bg-blue-50/60 rounded-lg border border-blue-200 text-slate-700 text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[11px] text-blue-900 leading-tight">Root Password server digenerate upstream dan wajib diinput manual oleh Admin pada form di bawah.</span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[11px]">
                    <div>
                        <span class="text-slate-500">Provider &amp; Datacenter:</span>
                        <span id="modalProviderDatacenter" class="font-medium text-slate-800 block" x-text="(currentOrder?.provider || '') + ' · ' + (currentOrder?.datacenter || '')"></span>
                    </div>
                    <div>
                        <span class="text-slate-500">Control Panel / Stack:</span>
                        <span id="modalStack" class="font-medium text-slate-800 block" x-text="currentOrder?.stack || 'Tanpa Panel'"></span>
                    </div>
                </div>

                <div class="flex justify-between items-center text-[11px] pt-1.5 border-t border-slate-200/80">
                    <span class="text-slate-500">Pelanggan: <strong id="modalCustomer" class="text-slate-800" x-text="currentOrder?.customer?.name || '-'"></strong> (<span class="font-mono-code" x-text="currentOrder?.customer?.phone || '-'"></span>)</span>
                    <span id="modalAmount" class="font-bold text-emerald-600 font-mono-code" x-text="'Lunas · Rp ' + (currentOrder?.amount || '')"></span>
                </div>
            </div>

            <!-- Form Alokasi Jaringan oleh Admin -->
            <form id="modalProvisionForm" :action="currentOrder ? '/admin/orders/' + currentOrder.id + '/provision' : '#'" method="POST" class="space-y-3.5 text-xs">
                @csrf

                <div>
                    <label class="block font-bold text-slate-800 mb-1">Public IPv4 Node <span class="text-rose-600">*</span></label>
                    <input id="modalPublicIp" type="text" name="public_ip" required x-model="publicIp" placeholder="Contoh: 103.150.xxx.xxx"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none focus:ring-1 focus:ring-black">
                    <p class="text-[11px] text-slate-400 mt-0.5">Alamat IP publik yang dialokasikan dari provider datacenter.</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-800 mb-1">Username SSH</label>
                    <input type="text" name="ssh_username" value="root" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none focus:ring-1 focus:ring-black">
                    <p class="text-[11px] text-slate-400 mt-0.5">Default <code>root</code>. Ganti bila supplier memberi user lain (mis. <code>ubuntu</code>).</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-800 mb-1">Password SSH Server <span class="text-rose-600">*</span></label>
                    <div class="relative">
                        <input id="modalRootPassword" :type="showRootPassword ? 'text' : 'password'" name="root_password" required minlength="8" x-model="rootPassword" placeholder="Masukkan password root dari retail (min 8 karakter)"
                               class="w-full px-3 py-2 pr-20 rounded-lg border border-slate-300 font-mono-code focus:outline-none focus:ring-1 focus:ring-black">
                        <button type="button" @click="showRootPassword = !showRootPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-slate-500 hover:text-slate-800 cursor-pointer font-medium">
                            <span x-text="showRootPassword ? 'Sembunyikan' : 'Lihat'">Sembunyikan</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Password root server yang digenerate otomatis oleh provider retail upstream.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">SSH Port</label>
                        <input id="modalSshPort" type="number" name="ssh_port" x-model="sshPort" placeholder="22" value="22"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none focus:ring-1 focus:ring-black">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Private VPC IP (Opsional)</label>
                        <input id="modalPrivateIp" type="text" name="private_ip" x-model="privateIp" placeholder="Contoh: 10.0.1.xxx"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-code focus:outline-none">
                    </div>
                </div>

                <!-- Input App URL: HANYA MUNCUL JIKA ADA PANEL / STACK DAN WAJIB DIISI -->
                <div id="modalPanelWrapper" x-show="hasPanel()" class="transition-all bg-amber-50/60 p-3.5 rounded-lg border border-amber-200 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block font-bold text-slate-900">
                            <span>App URL / Web Panel Link</span>
                            <span class="text-rose-600">*</span>
                        </label>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-200 text-amber-900">Wajib Diisi</span>
                    </div>
                    <input id="modalAppUrl" type="text" name="app_url" x-model="appUrl" :required="hasPanel()" :disabled="!hasPanel()"
                           placeholder="Contoh: https://103.150.xxx.xxx:8000 atau https://panel.domain.com"
                           class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white font-mono-code focus:outline-none focus:ring-1 focus:ring-black text-xs">
                    <p class="text-[11px] text-amber-800 leading-tight">
                        Pesanan ini memilih stack <strong class="text-slate-900" x-text="currentOrder?.stack"></strong>. Wajib cantumkan URL web panel agar pelanggan dapat langsung mengaksesnya dari dashboard.
                    </p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeProvisionModal()" @click="provisionModal = false" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors shadow-sm flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Aktifkan VPS &amp; Kirim Kredensial</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.openProvisionModal = function(orderId) {
    var order = (window.ordersPayload || {})[orderId];
    if (!order) return;

    var modal = document.getElementById('adminProvisionModal');
    if (!modal) return;

    var numElem = document.getElementById('modalOrderNumber');
    if (numElem) numElem.textContent = '#ORD-' + order.id;

    var hostElem = document.getElementById('modalHostname');
    if (hostElem) hostElem.textContent = order.hostname || '-';

    var osElem = document.getElementById('modalOs');
    if (osElem) osElem.textContent = order.os_label || order.os || '-';
    
    var pwInput = document.getElementById('modalRootPassword');
    if (pwInput) pwInput.value = '';

    var pdElem = document.getElementById('modalProviderDatacenter');
    if (pdElem) pdElem.textContent = (order.provider || '') + ' · ' + (order.datacenter || '');

    var stackElem = document.getElementById('modalStack');
    if (stackElem) stackElem.textContent = order.stack || 'Tanpa Panel';

    var custElem = document.getElementById('modalCustomer');
    if (custElem) custElem.textContent = (order.customer && order.customer.name ? order.customer.name : '-') + ' (' + (order.customer && order.customer.phone ? order.customer.phone : '-') + ')';

    var amtElem = document.getElementById('modalAmount');
    if (amtElem) amtElem.textContent = 'Lunas · Rp ' + (order.amount || '');

    var form = document.getElementById('modalProvisionForm');
    if (form) form.action = '/admin/orders/' + order.id + '/provision';

    var pip = document.getElementById('modalPublicIp');
    if (pip) pip.value = '';
    var privIp = document.getElementById('modalPrivateIp');
    if (privIp) privIp.value = '';
    var sshP = document.getElementById('modalSshPort');
    if (sshP) sshP.value = '22';

    var isDb = order.is_db;
    var hasPanel = isDb ? (order.db_manager === 'cloudbeaver') : (order.control_panel && order.control_panel !== 'none');
    var panelWrap = document.getElementById('modalPanelWrapper');
    var appUrlInput = document.getElementById('modalAppUrl');

    if (hasPanel) {
        if (panelWrap) panelWrap.style.display = 'block';
        if (appUrlInput) {
            appUrlInput.disabled = false;
            appUrlInput.required = true;
            appUrlInput.value = '';
        }
    } else {
        if (panelWrap) panelWrap.style.display = 'none';
        if (appUrlInput) {
            appUrlInput.disabled = true;
            appUrlInput.required = false;
            appUrlInput.value = '';
        }
    }

    var detailsCard = document.getElementById('modalOrderDetailsCard');
    if (detailsCard) detailsCard.style.display = 'block';

    modal.style.display = 'flex';
};

window.closeProvisionModal = function() {
    var modal = document.getElementById('adminProvisionModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.closeProvisionModal();
    }
});
</script>
@endsection
