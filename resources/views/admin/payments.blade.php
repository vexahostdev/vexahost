@extends('layouts.admin', ['title' => 'Verifikasi Pembayaran', 'headerTitle' => 'Verifikasi Pembayaran Manual', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    approveModal: false,
    rejectModal: false,
    holdModal: false,
    selectedOrder: null,
    reference: '',
    note: '',
    rejectReason: '',
    
    openApprove(order) {
        this.selectedOrder = order;
        this.reference = '';
        this.note = 'Mutasi DANA Bisnis cocok';
        this.approveModal = true;
    },
    
    openHold(order) {
        this.selectedOrder = order;
        this.note = 'Mutasi belum tampak di DANA Bisnis DESTINARA, menunggu pengecekan berkala';
        this.holdModal = true;
    },
    
    openReject(order) {
        this.selectedOrder = order;
        this.rejectReason = 'Mutasi pembayaran tidak ditemukan pada akun DANA Bisnis DESTINARA';
        this.rejectModal = true;
    }
}">
    {{-- Banner Petunjuk Verifikasi DANA Bisnis --}}
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-lg bg-black text-white flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Instruksi Verifikasi Mutasi DANA Bisnis</h2>
                <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                    Buka aplikasi DANA Bisnis pada merchant <strong>DESTINARA</strong> &rarr; menu <strong>Riwayat Transaksi</strong>.
                    Cocokkan nominal persis sebelum menyetujui pesanan. Sistem otomatis mengirimkan notifikasi email resmi ke pelanggan setelah disetujui atau ditolak.
                </p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-3">
            <div class="px-3.5 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                <span class="text-slate-500 block text-[11px]">Merchant DANA:</span>
                <strong class="text-slate-900 font-mono-code font-bold">DESTINARA</strong>
            </div>
            <div class="px-3.5 py-2 rounded-lg bg-amber-50 border border-amber-200 text-xs">
                <span class="text-amber-700 block text-[11px]">Perlu Verifikasi:</span>
                <strong class="text-amber-900 font-bold">{{ $pendingCount }} Pesanan</strong>
            </div>
        </div>
    </div>

    {{-- Filter Tabs & Search Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-medium pb-1 sm:pb-0">
            <a href="{{ route('admin.payments', ['status' => 'pending']) }}" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap flex items-center gap-1.5 {{ $status === 'pending' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                <span>Menunggu Verifikasi</span>
                @if($pendingCount > 0)
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold {{ $status === 'pending' ? 'bg-white text-black' : 'bg-black text-white' }}">
                        {{ $pendingCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.payments', ['status' => 'paid']) }}" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ $status === 'paid' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Telah Disetujui (Paid)
            </a>

            <a href="{{ route('admin.payments', ['status' => 'cancelled']) }}" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ $status === 'cancelled' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Ditolak / Batal
            </a>

            <a href="{{ route('admin.payments', ['status' => 'all']) }}" 
               class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap {{ $status === 'all' ? 'bg-black text-white font-bold' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50' }}">
                Semua Riwayat
            </a>
        </div>

        <form method="GET" action="{{ route('admin.payments') }}" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Cari invoice / pelanggan / ID..." 
                       class="w-full text-xs pl-8 pr-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-black focus:ring-1 focus:ring-black">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="px-3 py-2 bg-black hover:bg-neutral-800 text-white text-xs font-bold rounded-lg transition-colors">
                Cari
            </button>
        </form>
    </div>

    {{-- Tabel Order & Pembayaran --}}
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Invoice & ID</th>
                        <th class="px-5 py-3.5">Pelanggan</th>
                        <th class="px-5 py-3.5">Paket VPS</th>
                        <th class="px-5 py-3.5">Nominal Mutasi</th>
                        <th class="px-5 py-3.5">Waktu Order</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-mono-code font-bold text-slate-900 text-xs">
                                    {{ $order->invoice->invoice_number ?? ('INV-' . $order->id) }}
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    Order #{{ $order->id }} &bull; {{ strtoupper($order->payment_method ?? 'QRIS') }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900">{{ $order->customer->full_name ?? 'Pelanggan' }}</div>
                                <div class="text-[11px] text-slate-500 font-mono-code mt-0.5">{{ $order->customer->email ?? '-' }}</div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $order->vpsSpec->name ?? 'VPS' }}</div>
                                <div class="text-[11px] text-slate-500 font-mono-code mt-0.5">{{ $order->hostname ?? '-' }}</div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono-code font-bold text-slate-900 text-sm">
                                        Rp {{ number_format($order->amount, 0, ',', '.') }}
                                    </span>
                                    <button type="button" 
                                            @click="navigator.clipboard.writeText('{{ $order->amount }}'); showAlert('Nominal disalin: {{ $order->amount }}')" 
                                            class="p-1 text-slate-400 hover:text-black rounded" title="Salin Nominal">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">DANA: DESTINARA</div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="text-slate-800">{{ $order->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $order->created_at->diffForHumans() }}</div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                @if($order->status === 'paid')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        LUNAS (PAID)
                                    </span>
                                @elseif($order->status === 'cancelled')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                        DITOLAK
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300 inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        MENUNGGU MUTASI
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                @if($order->status === 'pending')
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Tombol Setujui --}}
                                        <button type="button" 
                                                @click="openApprove({{ Js::from($order) }})"
                                                class="px-3 py-1.5 bg-black hover:bg-neutral-800 text-white rounded-lg font-bold text-xs transition-colors flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>Setujui</span>
                                        </button>

                                        {{-- Tombol Tahan / Pending Catatan --}}
                                        <button type="button" 
                                                @click="openHold({{ Js::from($order) }})"
                                                class="px-2.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg font-semibold text-xs transition-colors"
                                                title="Tahan & Catat Keterangan">
                                            <span>Tunda</span>
                                        </button>

                                        {{-- Tombol Tolak --}}
                                        <button type="button" 
                                                @click="openReject({{ Js::from($order) }})"
                                                class="px-2.5 py-1.5 bg-white border border-rose-300 hover:bg-rose-50 text-rose-700 rounded-lg font-semibold text-xs transition-colors"
                                                title="Tolak Pembayaran">
                                            <span>Tolak</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="text-slate-400 text-[11px] italic">
                                        {{ $order->status === 'paid' ? 'Telah diverifikasi' : 'Telah dibatalkan' }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-700">Tidak ada transaksi yang perlu diverifikasi</p>
                                <p class="text-xs text-slate-400 mt-1">Semua pesanan pada filter ini sudah diproses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL 1: SETUJUI PEMBAYARAN --}}
    <div x-show="approveModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" @click="approveModal = false"></div>

            <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-slate-200">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/approve'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-slate-900">Setujui Pembayaran Order</h3>
                            </div>
                            <button type="button" @click="approveModal = false" class="text-slate-400 hover:text-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <template x-if="selectedOrder">
                            <div class="space-y-4 text-xs">
                                <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-1.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Order & Invoice:</span>
                                        <span class="font-bold text-slate-900" x-text="'Order #' + selectedOrder.id + ' (' + (selectedOrder.invoice ? selectedOrder.invoice.invoice_number : '-') + ')'"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Pelanggan:</span>
                                        <span class="font-semibold text-slate-900" x-text="selectedOrder.customer ? selectedOrder.customer.full_name : '-'"></span>
                                    </div>
                                    <div class="flex justify-between items-center border-t border-slate-200 pt-1.5">
                                        <span class="text-slate-700 font-semibold">Nominal yang Harus Dicocokkan:</span>
                                        <span class="font-mono-code font-black text-sm text-emerald-700" x-text="'Rp ' + Number(selectedOrder.amount).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="font-semibold text-slate-700 block">Nomor Referensi Mutasi DANA (Opsional):</label>
                                    <input type="text" name="reference" x-model="reference" placeholder="Contoh: DANA-20260915-XXXXX"
                                           class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-black">
                                </div>

                                <div class="space-y-1.5">
                                    <label class="font-semibold text-slate-700 block">Waktu Transfer di Mutasi, WIB (Opsional):</label>
                                    <input type="datetime-local" name="transferred_at"
                                           class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-black">
                                    <p class="text-[11px] text-slate-500">Isi untuk perpanjangan, agar transfer yang tepat waktu tidak ditolak karena baru diverifikasi setelah batas akhir.</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="font-semibold text-slate-700 block">Catatan Tambahan (Opsional):</label>
                                    <input type="text" name="note" x-model="note" placeholder="Catatan internal verifikasi admin" 
                                           class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-black">
                                </div>

                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-[11px] leading-relaxed">
                                    <strong>Perhatian:</strong> Menyetujui order ini akan mengubah status menjadi <strong>PAID</strong>, menandai invoice sebagai <strong>LUNAS</strong>, dan otomatis mengirim email konfirmasi pembayaran resmi ke pelanggan.
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="bg-slate-50 px-6 py-3.5 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="approveModal = false" 
                                class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-xs font-bold text-white transition-colors">
                            Ya, Setujui &amp; Kirim Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 2: TAHAN / PENDING CATATAN --}}
    <div x-show="holdModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" @click="holdModal = false"></div>

            <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md sm:w-full border border-slate-200">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/hold'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                            <h3 class="text-base font-bold text-slate-900">Tunda / Catat Status Mutasi</h3>
                            <button type="button" @click="holdModal = false" class="text-slate-400 hover:text-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="space-y-4 text-xs">
                            <p class="text-slate-600 leading-relaxed">
                                Order akan tetap berada pada status <strong>Pending</strong>. Catatan ini disimpan pada riwayat audit untuk pemantauan tim admin.
                            </p>

                            <div class="space-y-1.5">
                                <label class="font-semibold text-slate-700 block">Keterangan Penundaan:</label>
                                <textarea name="note" x-model="note" rows="3" required
                                          class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-black"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-3.5 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="holdModal = false" 
                                class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-xs font-bold text-white transition-colors">
                            Simpan Catatan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 3: TOLAK PEMBAYARAN --}}
    <div x-show="rejectModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" @click="rejectModal = false"></div>

            <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-slate-200">
                <form :action="'/admin/payments/' + (selectedOrder ? selectedOrder.id : '') + '/reject'" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-slate-900">Tolak Pembayaran Order</h3>
                            </div>
                            <button type="button" @click="rejectModal = false" class="text-slate-400 hover:text-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="space-y-4 text-xs">
                            <p class="text-slate-600 leading-relaxed">
                                Tindakan ini akan membatalkan pesanan (status <strong>CANCELLED</strong>) dan mengirimkan notifikasi email penolakan resmi ke pelanggan.
                            </p>

                            <div class="space-y-1.5">
                                <label class="font-semibold text-slate-700 block">Pilihan Alasan Cepat:</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" @click="rejectReason = 'Mutasi pembayaran tidak ditemukan pada akun DANA Bisnis DESTINARA'" 
                                            class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] border border-slate-200">
                                        Mutasi Tidak Ditemukan
                                    </button>
                                    <button type="button" @click="rejectReason = 'Nominal pembayaran tidak sesuai dengan tagihan invoice'" 
                                            class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] border border-slate-200">
                                        Nominal Tidak Sesuai
                                    </button>
                                    <button type="button" @click="rejectReason = 'Batas waktu pembayaran telah kedaluwarsa'" 
                                            class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] border border-slate-200">
                                        Waktu Kedaluwarsa
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="font-semibold text-slate-700 block">Alasan Penolakan (Akan tercantum di email pelanggan):</label>
                                <textarea name="reason" x-model="rejectReason" rows="3" required
                                          class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-rose-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-3.5 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="rejectModal = false" 
                                class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-5 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-xs font-bold text-white transition-colors">
                            Ya, Tolak &amp; Batalkan Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
