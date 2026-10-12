@extends('layouts.admin', ['title' => 'Fulfillment', 'headerTitle' => 'Papan Kerja Fulfillment', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    purchaseModalOpen: false,
    deliverModalOpen: false,
    target: { id: null, label: '', needsUrl: false },
    copied: null,
    openPurchase(id, label) { this.target = { id: id, label: label, needsUrl: false }; this.purchaseModalOpen = true; },
    openDeliver(id, label, needsUrl) { this.target = { id: id, label: label, needsUrl: needsUrl }; this.deliverModalOpen = true; },
    copy(id) {
        const el = document.getElementById('handover-' + id);
        if (!el) return;
        navigator.clipboard.writeText(el.value).then(() => { this.copied = id; setTimeout(() => this.copied = null, 2000); });
    }
}">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

    @if($errors->any())
        <div class="bg-white p-5 rounded-lg border border-red-200">
            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Sedang Dikerjakan</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $workingCount }}</span>
                <span class="text-xs text-slate-500">Order Dibayar</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Melewati Ambang</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $overdueCount > 0 ? 'text-red-600' : 'text-slate-900' }} font-mono-code">{{ $overdueCount }}</span>
                <span class="text-xs text-slate-500">Lebih dari {{ \App\Services\FulfillmentService::humanMinutes($slaMinutes) }}</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Diserahkan Hari Ini</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono-code">{{ $deliveredTodayCount }}</span>
                <span class="text-xs text-emerald-600 font-medium">Layanan</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Ambang Waktu Tanggap</span>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-extrabold text-slate-900 font-mono-code">{{ \App\Services\FulfillmentService::humanMinutes($slaMinutes) }}</span>
                <a href="{{ route('admin.settings.index', ['tab' => 'notification']) }}" class="text-xs text-slate-500 underline underline-offset-2">Ubah</a>
            </div>
        </div>
    </div>

    <!-- Papan -->
    <div class="overflow-x-auto pb-2">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 lg:min-w-[1100px]">
            @foreach($columns as $columnKey => $columnLabel)
                @php $items = $board[$columnKey]; @endphp
                <div class="bg-slate-50 rounded-lg border border-slate-200 flex flex-col min-h-[12rem]">
                    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">{{ $columnLabel }}</span>
                        <span class="text-xs font-bold font-mono-code {{ $items->count() > 0 ? 'bg-black text-white' : 'bg-slate-200 text-slate-600' }} px-2 py-0.5 rounded">{{ $items->count() }}</span>
                    </div>

                    <div class="p-3 space-y-3 flex-1">
                        @forelse($items as $order)
                            @php
                                $overdue = $fulfillment->isOverdue($order);
                                $waited = $fulfillment->minutesSincePaid($order);
                                $label = '#' . $order->id . ' · ' . ($order->vpsSpec?->name ?? 'Paket');
                            @endphp
                            <div class="bg-white p-4 rounded-lg border {{ $overdue ? 'border-red-300' : 'border-slate-200' }}">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="min-w-0">
                                        <span class="font-bold text-slate-900 text-sm block truncate">{{ $order->vpsSpec?->name ?? 'Paket' }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono-code">#ORD-{{ str_pad($order->id, 3, '0', STR_PAD_LEFT) }}</span>
                                    </div>
                                    @if($overdue)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-800 border border-red-200 shrink-0">Lewat</span>
                                    @endif
                                </div>

                                <div class="text-[11px] text-slate-600 space-y-0.5 mb-3">
                                    <div class="truncate font-medium text-slate-800">
                                        {{ $order->customer?->full_name ?? '-' }}
                                        @if($order->customer?->phone)
                                            <span class="text-slate-400 font-normal">({{ $order->customer->phone }})</span>
                                        @endif
                                    </div>
                                    <div class="font-mono-code text-slate-500 truncate">{{ $order->hostname ?? '-' }}</div>
                                    <div class="text-slate-500">{{ $order->provider_label }} · {{ ucfirst($order->datacenter_location) }} · {{ $order->os_label ?? $order->os }}</div>
                                    <div class="text-slate-500">{{ $order->control_panel_label }}</div>
                                    @if($order->isDatabasePackage())
                                        <div class="text-[10px] bg-slate-100 rounded px-1.5 py-0.5 text-slate-700 font-mono-code w-fit">
                                            DB: {{ strtoupper($order->db_engine ?? 'PG') }} · Mgr: {{ $order->db_manager }}
                                        </div>
                                    @endif
                                    <div class="font-mono-code text-slate-900 font-semibold">Rp {{ number_format((float) $order->amount, 0, ',', '.') }}</div>
                                    @if($waited !== null && $columnKey !== 'delivered')
                                        <div class="{{ $overdue ? 'text-red-700 font-semibold' : 'text-slate-500' }}">
                                            Menunggu {{ \App\Services\FulfillmentService::humanMinutes($waited) }}
                                        </div>
                                    @endif
                                    @if($order->status === 'failed')
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">Provisioning Gagal</span>
                                    @endif
                                </div>

                                {{-- ===== Aksi per kolom ===== --}}
                                @if($columnKey === 'awaiting_payment')
                                    <div class="flex flex-wrap gap-1">
                                        <form action="{{ route('admin.orders.mark-paid', $order->id) }}" method="POST"
                                              onsubmit="return confirm('Tandai order ini lunas secara manual?');">
                                            @csrf
                                            <input type="hidden" name="note" value="Ditandai lunas dari papan fulfillment.">
                                            <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">Tandai Lunas</button>
                                        </form>
                                        <form action="{{ route('admin.orders.cancel', $order->id) }}" method="POST"
                                              onsubmit="return confirm('Batalkan order ini?');">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-red-50 text-red-700 border border-red-200">Batalkan</button>
                                        </form>
                                    </div>
                                @endif

                                @if($columnKey === 'ready')
                                    <button type="button" @click="openPurchase({{ $order->id }}, @js($label))"
                                            class="w-full px-2.5 py-1.5 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                        Catat Pembelian Supplier
                                    </button>
                                @endif

                                @if($columnKey === 'purchased')
                                    @php $purchase = $order->supplierPurchases->sortByDesc('purchased_at')->first(); @endphp
                                    @if($purchase)
                                        <div class="rounded border border-slate-200 bg-slate-50 p-2 mb-2 text-[11px]">
                                            <div class="font-mono-code text-slate-900 truncate">{{ $purchase->supplier_order_no }}</div>
                                            <div class="text-slate-500">Biaya Rp {{ number_format((float) $purchase->actual_cost, 0, ',', '.') }}</div>
                                        </div>
                                    @endif
                                    <form action="{{ route('admin.fulfillment.stage', $order->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="stage" value="setup">
                                        <button type="submit" class="w-full px-2.5 py-1.5 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">Mulai Setup</button>
                                    </form>
                                @endif

                                @if($columnKey === 'setup')
                                    @php
                                        $checklist = $fulfillment->checklistFor($order);
                                        $progress = $fulfillment->progressFor($order);
                                        $needsUrl = $order->control_panel !== 'none'
                                            && !($order->isDatabasePackage() && $order->db_manager === 'cli_only');
                                    @endphp
                                    <div class="mb-2">
                                        <div class="flex items-center justify-between text-[11px] mb-1">
                                            <span class="text-slate-500">Daftar periksa</span>
                                            <span class="font-mono-code font-semibold text-slate-900">{{ $progress }}%</span>
                                        </div>
                                        <div class="h-1.5 rounded bg-slate-100 overflow-hidden">
                                            <div class="h-1.5 bg-black" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 mb-3">
                                        @foreach($checklist as $item)
                                            <form action="{{ route('admin.fulfillment.step', [$order->id, $item['key']]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full flex items-start gap-2 text-left px-2 py-1 rounded hover:bg-slate-50">
                                                    <span class="w-3.5 h-3.5 mt-0.5 rounded border shrink-0 flex items-center justify-center {{ $item['done'] ? 'bg-black border-black' : 'border-slate-300 bg-white' }}">
                                                        @if($item['done'])
                                                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                    </span>
                                                    <span class="text-[11px] {{ $item['done'] ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $item['label'] }}</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                    @if($progress < 100)
                                        <p class="text-[10px] text-amber-700 mb-2">Masih ada langkah yang belum dicentang.</p>
                                    @endif
                                    <div class="flex gap-1">
                                        <button type="button" @click="openDeliver({{ $order->id }}, @js($label), {{ $needsUrl ? 'true' : 'false' }})"
                                                class="flex-1 px-2.5 py-1.5 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                            Kirim Kredensial
                                        </button>
                                        <form action="{{ route('admin.fulfillment.stage', $order->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="stage" value="purchased">
                                            <button type="submit" title="Kembali ke tahap sebelumnya"
                                                    class="px-2.5 py-1.5 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">Kembali</button>
                                        </form>
                                    </div>
                                @endif

                                @if($columnKey === 'delivered')
                                    <div class="text-[11px] text-slate-500 mb-2">
                                        Diserahkan
                                        <span class="font-mono-code text-slate-700">{{ ($order->delivered_at ?? $order->starts_at)?->timezone('Asia/Jakarta')->format('d M H:i') ?? '-' }}</span>
                                        @if($order->paid_at && $order->delivered_at)
                                            · {{ \App\Services\FulfillmentService::humanMinutes($fulfillment->minutesSincePaid($order)) }}
                                        @endif
                                    </div>
                                    @php $handover = $fulfillment->handoverText($order, $handoverTemplate); @endphp
                                    @if($handover)
                                        <textarea id="handover-{{ $order->id }}" readonly rows="3"
                                                  class="w-full px-2 py-1.5 rounded border border-slate-200 bg-slate-50 text-[10px] text-slate-600 font-mono-code mb-1.5">{{ $handover }}</textarea>
                                        <button type="button" @click="copy({{ $order->id }})"
                                                class="w-full px-2.5 py-1.5 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                            <span x-show="copied !== {{ $order->id }}">Salin Teks Serah Terima</span>
                                            <span x-show="copied === {{ $order->id }}" x-cloak>Tersalin</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-[11px] text-slate-400 text-center py-6">Kosong</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Modal Catat Pembelian -->
    <div x-show="purchaseModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="purchaseModalOpen = false">

        <div @click.away="purchaseModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Catat Pembelian Supplier</h3>
                <p class="text-xs text-slate-500">Untuk <span class="font-semibold text-slate-800" x-text="target.label"></span></p>
            </div>

            <form :action="'/admin/fulfillment/' + target.id + '/purchase'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Nomor Pesanan Supplier</label>
                    <input type="text" name="supplier_order_no" required maxlength="120" class="{{ $inputClass }} font-mono-code">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                    <input type="number" name="actual_cost" required min="0" step="1" class="{{ $inputClass }} font-mono-code">
                    <p class="text-[11px] text-slate-500 mt-1">Harga yang benar-benar dibayar, termasuk bila ada promo atau selisih kurs.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Tanggal Beli</label>
                        <input type="datetime-local" name="purchased_at" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Aktif di Supplier s/d</label>
                        <input type="datetime-local" name="supplier_expires_at" class="{{ $inputClass }}">
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 -mt-2">Kosongkan tanggal untuk memakai sekarang dan masa aktif satu bulan.</p>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="purchaseModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">Batal</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">Simpan Pembelian</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kirim Kredensial (memakai endpoint provisioning yang sudah ada) -->
    <div x-show="deliverModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="deliverModalOpen = false">

        <div @click.away="deliverModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Kirim Kredensial ke Pelanggan</h3>
                <p class="text-xs text-slate-500">
                    Untuk <span class="font-semibold text-slate-800" x-text="target.label"></span>.
                    Order menjadi aktif dan surel kredensial terkirim otomatis.
                </p>
            </div>

            <form :action="'/admin/orders/' + target.id + '/provision'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">IP Publik</label>
                    <input type="text" name="public_ip" required placeholder="103.150.xxx.xxx" class="{{ $inputClass }} font-mono-code">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Username SSH</label>
                    <input type="text" name="ssh_username" value="root" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false" class="{{ $inputClass }} font-mono-code">
                    <p class="text-[10px] text-slate-400 mt-1">Default <code>root</code>. Ganti bila supplier memberi user lain (mis. <code>ubuntu</code>).</p>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Password SSH Server <span class="text-rose-600">*</span></label>
                    <input type="text" name="root_password" required minlength="8" placeholder="Password dari retail (min 8 karakter)" class="{{ $inputClass }} font-mono-code">
                    <p class="text-[10px] text-slate-400 mt-1">Kredensial login yang diterbitkan oleh provider retail.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Port SSH</label>
                        <input type="number" name="ssh_port" value="22" min="1" max="65535" class="{{ $inputClass }} font-mono-code">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">IP Privat</label>
                        <input type="text" name="private_ip" placeholder="Opsional" class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>
                <div x-show="target.needsUrl">
                    <label class="{{ $labelClass }}">URL Aplikasi / Panel</label>
                    <input type="text" name="app_url" :required="target.needsUrl" :disabled="!target.needsUrl"
                           placeholder="https://panel.domain.com" class="{{ $inputClass }} font-mono-code">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="deliverModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">Batal</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">Kirim & Aktifkan</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
